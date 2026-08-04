<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * McuPackage Model
 *
 * A curated collection of examination items bundled for a specific
 * corporate organization. Examples:
 *  - "Basic MCU - PT Maju Jaya"   → 15 items (blood panel + CXR)
 *  - "Executive MCU - PT ABC"     → 40 items (full comprehensive)
 *
 * @property int         $id
 * @property int         $organization_id
 * @property string      $name
 * @property string|null $description
 * @property bool        $is_active
 */
class McuPackage extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'mcu_packages';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'deleted_at' => 'datetime',
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
                fn (string $eventName) => "McuPackage [{$this->name}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The corporate organization this package belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Examination items included in this package.
     * Uses the `package_items` pivot table.
     */
    public function examinationItems(): BelongsToMany
    {
        return $this->belongsToMany(
            ExaminationItem::class,
            'package_items',
            'package_id',
            'item_id'
        )->withoutTimestamps();
    }

    /**
     * MCU registrations that use this package.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'package_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(\Illuminate\Database\Eloquent\Builder $query, int $organizationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    // ── Computed Attributes ───────────────────────────────────────────────

    /**
     * Total number of examination items in this package.
     * Use with ->withCount('examinationItems') to avoid N+1.
     */
    public function getItemCountAttribute(): int
    {
        return $this->examinationItems()->count();
    }
}
