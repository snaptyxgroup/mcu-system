<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * McuItem Model
 *
 * Individual test / examination parameter item (e.g. Hemoglobin, Chest X-Ray, ECG).
 * Optionally belongs to an examination Station.
 *
 * @property int         $id
 * @property int|null    $station_id
 * @property string      $code
 * @property string      $name
 * @property string|null $category
 * @property string|null $unit
 * @property string|null $normal_reference_male
 * @property string|null $normal_reference_female
 * @property float       $price
 * @property bool        $is_active
 */
class McuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mcu_items';

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'category',
        'unit',
        'normal_reference_male',
        'normal_reference_female',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'is_active'  => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
