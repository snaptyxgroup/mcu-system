<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * EditMcuRegistration Page
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
