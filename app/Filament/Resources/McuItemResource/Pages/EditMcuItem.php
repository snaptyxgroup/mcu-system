<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuItemResource\Pages;

use App\Filament\Resources\McuItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMcuItem extends EditRecord
{
    protected static string $resource = McuItemResource::class;

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
