<?php

declare(strict_types=1);

namespace App\Filament\Resources\Icd10CodeResource\Pages;

use App\Filament\Resources\Icd10CodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIcd10Code extends EditRecord
{
    protected static string $resource = Icd10CodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
