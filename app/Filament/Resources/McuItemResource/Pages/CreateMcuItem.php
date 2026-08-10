<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuItemResource\Pages;

use App\Filament\Resources\McuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMcuItem extends CreateRecord
{
    protected static string $resource = McuItemResource::class;
}
