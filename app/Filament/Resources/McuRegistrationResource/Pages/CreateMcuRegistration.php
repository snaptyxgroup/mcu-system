<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CreateMcuRegistration Page
 *
 * Handles creation of new MCU registrations.
 *
 * Webcam Photo (Requirement #5) — Livewire v4 Logic:
 * -------------------------------------------------
 * The webcam Blade component captures a JPEG image as a base64 data URL
 * string (e.g., "data:image/jpeg;base64,/9j/4AAQ...") and writes it
 * to the Livewire form state via `$wire.set('data.employee_photo', base64)`.
 *
 * In `mutateFormDataBeforeCreate()`, we:
 *  1. Detect the base64 prefix ("data:image/jpeg;base64,")
 *  2. Decode the base64 payload into raw binary
 *  3. Generate a unique filename: employee-photos/{uuid}.jpg
 *  4. Save to the `public` disk (storage/app/public/employee-photos/)
 *  5. Replace the base64 string with the file path in form data
 *
 * The file is then accessible via `Storage::url($path)` for display
 * in tables, ID badges, and station check-in screens.
 */
class CreateMcuRegistration extends CreateRecord
{
    protected static string $resource = McuRegistrationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * Mutate form data before persisting.
     * - Inject `registered_by` from the auth session
     * - Decode webcam base64 photo → save to storage → store file path
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registered_by'] = auth()->id();

        // Process webcam base64 capture
        $data = $this->processWebcamPhoto($data);

        return $data;
    }

    /**
     * Decode base64 webcam capture and save as a file.
     */
    protected function processWebcamPhoto(array $data): array
    {
        $photoData = $data['employee_photo'] ?? null;

        if (!$photoData || !str_starts_with($photoData, 'data:image/')) {
            // Not a base64 capture — could be null or already a file path
            // If it's an empty string, set to null
            if (empty($photoData)) {
                $data['employee_photo'] = null;
            }
            return $data;
        }

        // Extract the base64 payload after the comma
        // Format: "data:image/jpeg;base64,/9j/4AAQ..."
        $base64Payload = substr($photoData, strpos($photoData, ',') + 1);
        $decodedImage = base64_decode($base64Payload, strict: true);

        if ($decodedImage === false) {
            $data['employee_photo'] = null;
            return $data;
        }

        // Generate unique filename and save
        $filename = 'employee-photos/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($filename, $decodedImage);

        // Store the relative path (accessible via Storage::url($filename))
        $data['employee_photo'] = $filename;

        return $data;
    }
}
