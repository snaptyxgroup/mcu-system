<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\McuRegistration;
use App\Models\McuResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MedicalAiService
 *
 * Encapsulates all communication with the Google Gemini AI API for
 * generating medical draft summaries and fitness recommendations.
 *
 * Responsibility boundaries (Single Responsibility Principle):
 *  - This service ONLY handles prompt construction and API communication.
 *  - It does NOT write to the database — that is the job of GenerateMedicalDraftJob.
 *  - It does NOT know about queues or Filament — framework-agnostic.
 *
 * Prompt Engineering Strategy:
 *  - System instruction: defines the AI's role as a medical assistant.
 *  - User prompt: structured JSON of patient context + abnormal results.
 *  - Response schema: enforces a strict JSON output structure so parsing is
 *    deterministic and never fails with a markdown code block.
 *
 * cPanel / Shared Hosting Constraints:
 *  - Timeout set to 60s (Gemini Flash is fast; cPanel often limits to 90s).
 *  - Retry logic: 2 attempts with 3s delay.
 *  - No streaming — synchronous request/response.
 */
class MedicalAiService
{
    /**
     * The Gemini model to use. gemini-1.5-flash is recommended for
     * structured JSON tasks — faster and cheaper than Pro.
     */
    private const MODEL = 'gemini-1.5-flash-latest';

    /**
     * Base URL for the Gemini generateContent API endpoint.
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key')
            ?? throw new \RuntimeException('GEMINI_API_KEY is not configured in services.gemini.api_key');
    }

    // ── Public Interface ──────────────────────────────────────────────────

    /**
     * Generate a medical draft summary for a completed MCU registration.
     *
     * Returns a structured array with keys:
     *  - summary (string)              → Formatted markdown medical summary
     *  - recommended_status (string)   → One of: FIT TO WORK / FIT WITH NOTE /
     *                                     TEMPORARY UNFIT / UNFIT
     *  - key_findings (array)          → List of notable findings
     *  - follow_up_notes (string)      → Recommended follow-up actions
     *
     * @param  McuRegistration $registration  Must be COMPLETED with results eager-loaded
     * @return array<string, mixed>
     * @throws \Exception If Gemini API returns a non-200 response after retries.
     */
    public function generateDraft(McuRegistration $registration): array
    {
        $prompt = $this->buildPrompt($registration);

        $response = $this->callGeminiApi($prompt);

        return $this->parseResponse($response, $registration->id);
    }

    // ── Prompt Construction ───────────────────────────────────────────────

    /**
     * Builds the complete Gemini API request payload.
     *
     * The prompt is intentionally structured as a medical brief:
     *  SECTION 1: Patient Risk Profile
     *  SECTION 2: Abnormal Results (with normal-range context)
     *  SECTION 3: All Results Summary (for completeness)
     *  SECTION 4: Output Instructions
     */
    private function buildPrompt(McuRegistration $registration): array
    {
        $patient = $registration->patient;
        $abnormalResults = $registration->abnormalResults;
        $allResults = $registration->results->load('item:id,name,item_code,unit,input_type,normal_min,normal_max,normal_text');

        // ── Patient Risk Profile ──────────────────────────────────────────
        $patientContext = [
            'age'            => $patient->age,
            'gender'         => $patient->gender,
            'job_title'      => $patient->job_title,
            'department'     => $patient->department,
            'job_risk_level' => $patient->job_risk_level,
            'custom_context' => $patient->custom_attributes,
        ];

        // ── Abnormal Results (core AI input) ──────────────────────────────
        $abnormalData = $abnormalResults->map(function (McuResult $result) {
            $item = $result->item;

            return [
                'test_code'    => $item->item_code,
                'test_name'    => $item->name,
                'result'       => $result->result_value,
                'unit'         => $item->unit,
                'normal_range' => $item->normal_range_display,
                'remarks'      => $result->remarks,
            ];
        })->values()->toArray();

        // ── All Results (for context) ─────────────────────────────────────
        $allResultsData = $allResults->map(function (McuResult $result) {
            $item = $result->item;

            return [
                'test_code'   => $item->item_code,
                'test_name'   => $item->name,
                'result'      => $result->result_value,
                'unit'        => $item->unit,
                'is_abnormal' => $result->is_abnormal,
            ];
        })->values()->toArray();

        // ── Construct User Prompt ─────────────────────────────────────────
        $userPromptText = <<<PROMPT
        You are a senior occupational health physician AI assistant for the Snaptyx MCU System.

        Your task is to analyze the following Medical Check-Up (MCU) results and generate a structured clinical summary. 
        Your output will be reviewed and approved by a licensed doctor before being shown to the patient.

        ---
        ## PATIENT RISK PROFILE
        ```json
        {$this->jsonEncode($patientContext)}
        ```

        ---
        ## ABNORMAL FINDINGS ({$abnormalResults->count()} items requiring attention)
        ```json
        {$this->jsonEncode($abnormalData)}
        ```

        ---
        ## COMPLETE MCU RESULTS OVERVIEW
        ```json
        {$this->jsonEncode($allResultsData)}
        ```

        ---
        ## OUTPUT INSTRUCTIONS
        Return a valid JSON object (no markdown code blocks) with EXACTLY these fields:
        {
          "summary": "<Detailed clinical summary in Indonesian medical language. 2-4 paragraphs.>",
          "recommended_status": "<One of: FIT TO WORK | FIT WITH NOTE | TEMPORARY UNFIT | UNFIT>",
          "key_findings": ["<finding 1>", "<finding 2>"],
          "follow_up_notes": "<Specific follow-up actions, referrals, or work restrictions>",
          "risk_assessment": "<Brief assessment of occupational risk given job_risk_level>"
        }

        Consider the patient's job_risk_level when recommending status:
        - EXTREME risk jobs (mining, offshore) require stricter fitness standards.
        - Borderline results in HIGH/EXTREME risk = recommend TEMPORARY UNFIT or FIT WITH NOTE.
        - LOW risk = borderline values may still qualify as FIT TO WORK with a note.
        PROMPT;

        // ── Gemini API Payload ─────────────────────────────────────────────
        return [
            'system_instruction' => [
                'parts' => [[
                    'text' => 'You are an expert occupational health physician assistant. ' .
                              'Always respond with valid JSON only. Never use markdown. ' .
                              'Be precise, evidence-based, and use clinical terminology appropriate for doctor review.',
                ]],
            ],
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $userPromptText]],
            ]],
            'generationConfig' => [
                'temperature'      => 0.3,   // Low temp for consistent clinical output
                'topK'             => 40,
                'topP'             => 0.95,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',   // Force JSON output mode
            ],
            'safetySettings' => [
                // Healthcare content — disable overly conservative safety blocks
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];
    }

    // ── API Communication ─────────────────────────────────────────────────

    /**
     * Calls the Gemini generateContent endpoint with retry logic.
     * Designed to work within cPanel's timeout constraints.
     */
    private function callGeminiApi(array $payload): Response
    {
        $url = sprintf(
            '%s/%s:generateContent?key=%s',
            self::API_BASE,
            self::MODEL,
            $this->apiKey
        );

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout(60)        // cPanel usually allows 60–90s max execution
            ->retry(2, 3000)     // 2 attempts, 3 second delay between retries
            ->post($url, $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown Gemini API error');
            $status = $response->status();

            Log::error('Gemini API request failed', [
                'status'  => $status,
                'error'   => $error,
                'model'   => self::MODEL,
            ]);

            throw new \RuntimeException(
                "Gemini API error [{$status}]: {$error}"
            );
        }

        return $response;
    }

    // ── Response Parsing ──────────────────────────────────────────────────

    /**
     * Parses the Gemini API response and extracts structured data.
     * If JSON parsing fails, returns a graceful fallback rather than
     * crashing the job — a failed draft is better than no draft.
     */
    private function parseResponse(Response $response, int $registrationId): array
    {
        $rawText = $response->json(
            'candidates.0.content.parts.0.text',
            ''
        );

        // Attempt to strip any accidental markdown fencing
        $cleanedText = trim(preg_replace('/^```json\s*|\s*```$/m', '', $rawText));

        try {
            $parsed = json_decode($cleanedText, true, 512, JSON_THROW_ON_ERROR);

            // Validate required keys exist in the parsed response
            $this->validateParsedKeys($parsed);

            Log::info("MedicalAiService: Successfully generated draft for registration #{$registrationId}");

            return $parsed;

        } catch (\JsonException $e) {
            Log::error('MedicalAiService: JSON parse failed', [
                'registration_id' => $registrationId,
                'raw_text'        => substr($rawText, 0, 500),
                'error'           => $e->getMessage(),
            ]);

            // Return a structured fallback so the medical_review row is still created
            return $this->buildFallbackResponse("Gemini returned unparseable JSON: " . $e->getMessage());

        } catch (\InvalidArgumentException $e) {
            Log::warning('MedicalAiService: Response missing required keys', [
                'registration_id' => $registrationId,
                'error'           => $e->getMessage(),
            ]);

            return $this->buildFallbackResponse($e->getMessage());
        }
    }

    /**
     * Validates that the parsed JSON contains all required keys.
     *
     * @throws \InvalidArgumentException
     */
    private function validateParsedKeys(array $parsed): void
    {
        $required = ['summary', 'recommended_status', 'key_findings', 'follow_up_notes'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $parsed)) {
                throw new \InvalidArgumentException(
                    "Gemini response missing required key: [{$key}]"
                );
            }
        }

        $validStatuses = ['FIT TO WORK', 'FIT WITH NOTE', 'TEMPORARY UNFIT', 'UNFIT'];

        if (!in_array($parsed['recommended_status'], $validStatuses, true)) {
            throw new \InvalidArgumentException(
                "Invalid recommended_status value: [{$parsed['recommended_status']}]. " .
                "Must be one of: " . implode(', ', $validStatuses)
            );
        }
    }

    /**
     * Returns a safe fallback response when the AI fails.
     * This ensures the medical_review record is always created,
     * even on AI failure — the doctor can write the summary manually.
     */
    private function buildFallbackResponse(string $errorDetail): array
    {
        return [
            'summary'              => "**AI Summary tidak tersedia.**\n\nTerjadi kesalahan teknis saat membuat ringkasan AI. Dokter diminta untuk mengisi ringkasan secara manual.\n\nDetail error: {$errorDetail}",
            'recommended_status'   => null,
            'key_findings'         => ['AI generation failed — manual review required'],
            'follow_up_notes'      => 'Please review all examination results manually.',
            'risk_assessment'      => null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function jsonEncode(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
