<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Icd10Code Model
 *
 * Catalog of International Classification of Diseases 10th Revision (ICD-10)
 * codes for medical examination diagnosis mapping.
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name_en
 * @property string|null $name_id
 * @property string|null $category
 * @property bool        $is_active
 */
class Icd10Code extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'icd10_codes';

    protected $fillable = [
        'code',
        'name_en',
        'name_id',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
