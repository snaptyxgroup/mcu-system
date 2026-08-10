<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuItemResource\Pages;

use App\Filament\Resources\McuItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMcuItems extends ListRecords
{
    protected static string $resource = McuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add MCU Item')
                ->icon('heroicon-o-plus'),
        ];
    }
}
