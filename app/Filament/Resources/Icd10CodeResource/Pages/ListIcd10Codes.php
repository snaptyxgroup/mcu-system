<?php

declare(strict_types=1);

namespace App\Filament\Resources\Icd10CodeResource\Pages;

use App\Filament\Resources\Icd10CodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIcd10Codes extends ListRecords
{
    protected static string $resource = Icd10CodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add ICD-10 Code')
                ->icon('heroicon-o-plus'),
        ];
    }
}
