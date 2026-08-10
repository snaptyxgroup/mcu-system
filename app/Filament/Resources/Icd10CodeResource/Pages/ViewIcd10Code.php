<?php

declare(strict_types=1);

namespace App\Filament\Resources\Icd10CodeResource\Pages;

use App\Filament\Resources\Icd10CodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIcd10Code extends ViewRecord
{
    protected static string $resource = Icd10CodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
