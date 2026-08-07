<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Station Model
 *
 * A physical or logical checkpoint in the MCU patient flow.
 * Stations are ordered by `sequence_order` which defines the
 * expected patient journey (e.g., 1=Registration → 2=Blood Draw
 * → 3=Radiology → 4=Doctor → 5=Done).
 *
 * @property int         $id
 * @property string      $name
 * @property int         $sequence_order
 * @property string|null $description
 * @property bool        $is_active
 */
class Station extends Model
{
    use HasFactory;

    protected $table = 'stations';

    protected $fillable = [
        'name',
        'sequence_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'is_active'      => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * MCU registrations assigned to this station.
     */
    public function mcuRegistrations(): BelongsToMany
    {
        return $this->belongsToMany(
            McuRegistration::class,
            'mcu_registration_station',
            'station_id',
            'mcu_registration_id'
        )
        ->withPivot(['checked_in_at', 'checked_out_at', 'status'])
        ->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    // ── Default ordering ──────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy('sequence_order');
        });
    }
}
