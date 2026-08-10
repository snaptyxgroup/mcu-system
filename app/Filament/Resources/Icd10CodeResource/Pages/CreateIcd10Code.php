<?php

declare(strict_types=1);

namespace App\Filament\Resources\Icd10CodeResource\Pages;

use App\Filament\Resources\Icd10CodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIcd10Code extends CreateRecord
{
    protected static string $resource = Icd10CodeResource::class;
}
