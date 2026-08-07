<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EditMcuRegistration Page
 *
 * Same webcam photo processing logic as CreateMcuRegistration.
 * If a new photo is captured (base64 string), the old photo is
 * deleted from storage and replaced with the new one.
 */
class EditMcuRegistration extends EditRecord
{
    protected static string $resource = McuRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }

    /**
     * Process webcam photo before saving edits.
     * If a new base64 photo is provided, delete the old one and save the new.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $photoData = $data['employee_photo'] ?? null;

        if (!$photoData || !str_starts_with($photoData, 'data:image/')) {
            // No new webcam capture — keep existing photo
            // If empty string, preserve the existing record's photo
            if ($photoData === '' || $photoData === null) {
                $data['employee_photo'] = $this->record->employee_photo;
            }
            return $data;
        }

        // Delete old photo if it exists
        $oldPhoto = $this->record->employee_photo;
        if ($oldPhoto && Storage::disk('public')->exists($oldPhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        // Decode and save new photo
        $base64Payload = substr($photoData, strpos($photoData, ',') + 1);
        $decodedImage = base64_decode($base64Payload, strict: true);

        if ($decodedImage === false) {
            $data['employee_photo'] = $this->record->employee_photo;
            return $data;
        }

        $filename = 'employee-photos/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($filename, $decodedImage);

        $data['employee_photo'] = $filename;

        return $data;
    }
}
