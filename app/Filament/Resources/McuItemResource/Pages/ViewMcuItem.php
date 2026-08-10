<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuItemResource\Pages;

use App\Filament\Resources\McuItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMcuItem extends ViewRecord
{
    protected static string $resource = McuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
