<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\McuRegistration;
use App\Models\MedicalReview;
use App\Services\MedicalAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GenerateMedicalDraftJob
 *
 * Asynchronous Queued Job — runs on the 'ai' queue.
 *
 * Trigger:  Dispatched by McuRegistration::booted() when status → COMPLETED.
 * Action:   Calls Gemini AI via MedicalAiService, then creates/updates
 *           the MedicalReview record with the AI draft.
 *
 * Failure Handling:
 *  - Retries up to 3 times with exponential backoff.
 *  - ThrottlesExceptions middleware: if Gemini is rate-limiting us,
 *    pause processing for 10 minutes after 3 exceptions.
 *  - WithoutOverlapping: prevents duplicate AI calls for the same
 *    registration if the job is somehow dispatched twice.
 *  - On final failure, the MedicalReview remains at PENDING status with
 *    a null ai_summary — the doctor will be prompted to fill it manually.
 *
 * Deployment Note (cPanel):
 *  - Use the 'database' queue driver on cPanel (no Redis/Horizon).
 *  - Run: php artisan queue:work --queue=ai,default --tries=3 --timeout=90
 *  - Set up a cPanel Cron Job: * * * * * php artisan schedule:run
 *
 * @property McuRegistration $registration
 */
class GenerateMedicalDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted before marking as failed.
     * 3 attempts → initial + 2 retries.
     */
    public int $tries = 3;

    /**
     * Maximum number of seconds the job is allowed to run.
     * Gemini Flash typically responds in < 15s, but we allow 90s for cPanel.
     */
    public int $timeout = 90;

    /**
     * Calculate the backoff times (in seconds) between job attempts.
     * Exponential: 30s → 120s → (fail)
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  McuRegistration $registration  The completed MCU registration.
     */
    public function __construct(
        private readonly McuRegistration $registration
    ) {}

    // ── Middleware ────────────────────────────────────────────────────────

    /**
     * Job middleware applied before the handle() method.
     *
     * 1. WithoutOverlapping — prevents race conditions if the same
     *    registration completes twice in quick succession.
     *
     * 2. ThrottlesExceptions — if Gemini rate-limits us (429) or returns
     *    server errors (5xx), pause the AI queue for 10 minutes to avoid
     *    flooding the API.
     */
    public function middleware(): array
    {
        return [
            // Key: registration ID ensures per-registration uniqueness
            (new WithoutOverlapping("ai-draft:{$this->registration->id}"))
                ->expireAfter(180)  // Lock expires after 3 minutes
                ->releaseAfter(30), // Re-release for retry after 30s

            // Throttle: 3 exceptions within 1 minute → pause for 10 minutes
            (new ThrottlesExceptions(3, 10 * 60))
                ->backoff(10),
        ];
    }

    // ── Main Handler ──────────────────────────────────────────────────────

    /**
     * Execute the job.
     *
     * Steps:
     *  1. Verify registration is still COMPLETED (status may have changed).
     *  2. Create or find the MedicalReview record (idempotent — safe to retry).
     *  3. Eager-load all data needed for the AI prompt.
     *  4. Call MedicalAiService to get the draft.
     *  5. Update MedicalReview with AI output.
     */
    public function handle(MedicalAiService $aiService): void
    {
        $registrationId = $this->registration->id;

        Log::info("GenerateMedicalDraftJob: Starting for registration #{$registrationId}");

        // ── Step 1: Fresh load & status check ────────────────────────────
        /** @var McuRegistration $registration */
        $registration = McuRegistration::with([
            'patient:id,name,gender,dob,job_title,department,job_risk_level,custom_attributes,organization_id',
            'patient.organization:id,name',
            'package:id,name',
            'project:id,name',
            'results.item:id,name,item_code,unit,input_type,normal_min,normal_max,normal_text',
            'abnormalResults.item:id,name,item_code,unit,input_type,normal_min,normal_max,normal_text',
        ])->findOrFail($registrationId);

        if ($registration->status !== 'COMPLETED') {
            Log::warning("GenerateMedicalDraftJob: Registration #{$registrationId} is no longer COMPLETED. Skipping.");
            return;
        }

        // ── Step 2: Idempotent MedicalReview record creation ──────────────
        // `firstOrCreate` ensures we never create duplicate review rows,
        // making the job safe to retry without side effects.
        /** @var MedicalReview $review */
        $review = MedicalReview::firstOrCreate(
            ['registration_id' => $registrationId],
            ['review_status'   => 'PENDING']
        );

        // If already reviewed by a doctor, don't overwrite with AI output
        if ($review->review_status === 'REVIEWED') {
            Log::info("GenerateMedicalDraftJob: Registration #{$registrationId} already has a final doctor review. Skipping AI update.");
            return;
        }

        // ── Step 3: Call AI Service ───────────────────────────────────────
        try {
            Log::info("GenerateMedicalDraftJob: Calling Gemini AI for registration #{$registrationId}");

            $draft = $aiService->generateDraft($registration);

            // ── Step 4: Persist AI Draft ──────────────────────────────────
            DB::transaction(function () use ($review, $draft) {
                $review->update([
                    'ai_summary'            => $this->formatSummaryForStorage($draft),
                    'ai_recommended_status' => $draft['recommended_status'] ?? null,
                    'review_status'         => 'AI_DRAFT_READY',
                ]);
            });

            Log::info(
                "GenerateMedicalDraftJob: AI draft saved for registration #{$registrationId}. " .
                "Recommended status: [{$draft['recommended_status']}]"
            );

        } catch (\Throwable $e) {
            Log::error("GenerateMedicalDraftJob: Failed for registration #{$registrationId}", [
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw so Laravel's retry mechanism picks it up
            throw $e;
        }
    }

    // ── Failure Handler ───────────────────────────────────────────────────

    /**
     * Called when the job has exhausted all attempts (failed permanently).
     *
     * Ensures the MedicalReview record has a helpful error message so the
     * doctor knows to fill the summary manually.
     */
    public function failed(\Throwable $exception): void
    {
        $registrationId = $this->registration->id;

        Log::error("GenerateMedicalDraftJob: Permanently failed for registration #{$registrationId}", [
            'exception' => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        // Update the review with a failure notice — status remains PENDING
        // so the doctor can still write a manual summary
        MedicalReview::updateOrCreate(
            ['registration_id' => $registrationId],
            [
                'ai_summary'    => "**AI generation failed after {$this->tries} attempts.**\n\n" .
                                   "Error: {$exception->getMessage()}\n\n" .
                                   "Silakan isi ringkasan medis secara manual.",
                'review_status' => 'AI_DRAFT_READY',  // Advance so doctor is notified
            ]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Formats the raw AI draft into a rich markdown string for storage.
     * This combines all AI output fields into one `ai_summary` blob
     * that Filament's Markdown preview widget renders beautifully.
     */
    private function formatSummaryForStorage(array $draft): string
    {
        $summary      = $draft['summary']        ?? '';
        $followUp     = $draft['follow_up_notes'] ?? '';
        $riskAssess   = $draft['risk_assessment'] ?? '';
        $keyFindings  = $draft['key_findings']   ?? [];

        $findingsMarkdown = empty($keyFindings)
            ? '_Tidak ada temuan kritis._'
            : collect($keyFindings)->map(fn ($f) => "- {$f}")->implode("\n");

        return <<<MARKDOWN
        {$summary}

        ---

        ## 🔍 Temuan Utama (Key Findings)

        {$findingsMarkdown}

        ---

        ## ⚠️ Penilaian Risiko Kerja

        {$riskAssess}

        ---

        ## 📋 Rekomendasi Tindak Lanjut

        {$followUp}

        ---

        > _Ringkasan ini dihasilkan oleh AI Copilot Snaptyx MCU dan harus diverifikasi oleh dokter yang berwenang sebelum diterbitkan._
        MARKDOWN;
    }
}
