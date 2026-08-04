<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use HasFactory, LogsActivity;

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

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "Station [{$this->name}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * Users currently assigned to this station (via daily scheduling pivot).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_stations', 'station_id', 'user_id')
                    ->withPivot('assigned_date')
                    ->withTimestamps();
    }

    /**
     * Examination items performed at this station.
     */
    public function examinationItems(): HasMany
    {
        return $this->hasMany(ExaminationItem::class, 'station_id');
    }

    /**
     * Active examination items at this station (used in forms to avoid
     * loading soft-deleted/inactive items into dropdowns).
     */
    public function activeExaminationItems(): HasMany
    {
        return $this->hasMany(ExaminationItem::class, 'station_id')
                    ->where('is_active', true)
                    ->orderBy('name');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    // ── Default ordering ──────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Always return stations in their defined sequence order
        static::addGlobalScope('ordered', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy('sequence_order');
        });
    }
}
