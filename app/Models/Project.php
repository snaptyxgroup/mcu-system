<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Project Model
 *
 * Represents one MCU engagement between Snaptyx and a corporate client.
 * A project defines the date window during which patient registrations
 * and examinations are considered valid.
 *
 * @property int              $id
 * @property int              $organization_id
 * @property string           $name
 * @property \Carbon\Carbon   $start_date
 * @property \Carbon\Carbon   $end_date
 * @property string|null      $description
 * @property string           $status         DRAFT | ACTIVE | CLOSED
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Project extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'projects';

    protected $fillable = [
        'organization_id',
        'name',
        'start_date',
        'end_date',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
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
                fn (string $eventName) => "Project [{$this->name}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The corporate organization that commissioned this project.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * All MCU registrations under this project.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'project_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForOrganization(\Illuminate\Database\Eloquent\Builder $query, int $organizationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Returns a human-readable duration label: "Jan 15 – Feb 28, 2025"
     */
    public function getDurationLabelAttribute(): string
    {
        return $this->start_date->format('M d') . ' – ' . $this->end_date->format('M d, Y');
    }

    /**
     * Derived: is the project currently within its date window?
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        $today = now()->startOfDay();

        return $this->status === 'ACTIVE'
            && $this->start_date->lte($today)
            && $this->end_date->gte($today);
    }
}
