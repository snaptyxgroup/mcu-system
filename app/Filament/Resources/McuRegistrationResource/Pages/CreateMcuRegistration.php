<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * CreateMcuRegistration Page
 *
 * Handles the creation of a new MCU registration.
 * The `mutateFormDataBeforeCreate` hook enriches the form data with
 * the currently authenticated user's ID before persisting.
 */
class CreateMcuRegistration extends CreateRecord
{
    protected static string $resource = McuRegistrationResource::class;

    protected function getRedirectUrl(): string
    {
        // After creation, go to the view page to see patient details
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * Inject `registered_by` from the authenticated session before saving.
     * This ensures the field is always set even if the form's Hidden component
     * is somehow bypassed.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registered_by'] = auth()->id();

        return $data;
    }
}
