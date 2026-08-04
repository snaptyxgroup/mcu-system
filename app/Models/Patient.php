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
 * Patient Model
 *
 * Represents an employee of a corporate client undergoing MCU.
 * One patient may have multiple registrations across different projects
 * (annual MCU cycles, pre-employment, periodic check-ups etc.)
 *
 * Key design notes:
 *  - `nik` and `employee_id` are unique WITHIN an organization, not globally.
 *  - `custom_attributes` is a JSON bag — cast to `array` for easy Laravel access.
 *  - `job_risk_level` directly feeds the Gemini AI risk-profiling prompt.
 *
 * @property int              $id
 * @property int              $organization_id
 * @property string|null      $nik
 * @property string|null      $employee_id
 * @property string           $name
 * @property \Carbon\Carbon|null $dob
 * @property string|null      $gender          MALE | FEMALE
 * @property string|null      $department
 * @property string|null      $job_title
 * @property string           $job_risk_level  LOW | MEDIUM | HIGH | EXTREME
 * @property array|null       $custom_attributes
 */
class Patient extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'patients';

    protected $fillable = [
        'organization_id',
        'nik',
        'employee_id',
        'name',
        'dob',
        'gender',
        'department',
        'job_title',
        'job_risk_level',
        'custom_attributes',
    ];

    protected function casts(): array
    {
        return [
            // JSON field → PHP array (supports dot-notation access)
            'custom_attributes' => 'array',

            // Carbon date (no time component)
            'dob'        => 'date',

            'deleted_at' => 'datetime',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'nik', 'employee_id', 'department',
                'job_title', 'job_risk_level', 'gender', 'dob',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "Patient [{$this->name}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The corporate organization this patient works for.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * All MCU registrations for this patient across all projects.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'patient_id');
    }

    /**
     * Latest registration (eager-loadable, useful for dashboards).
     */
    public function latestRegistration(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'patient_id')
                    ->latest('created_at')
                    ->limit(1);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Scope: filter by job risk level(s).
     * Usage: Patient::highRisk()->get()
     */
    public function scopeHighRisk(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('job_risk_level', ['HIGH', 'EXTREME']);
    }

    /**
     * Scope: search patients by name, NIK, or employee_id.
     * Used by Filament's searchable select to avoid loading all patients.
     */
    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('nik', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%");
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Computed age from date of birth using Carbon.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    /**
     * Full identifier for display in forms: "Budi Santoso (EMP-001)"
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];

        if ($this->employee_id) {
            $parts[] = "({$this->employee_id})";
        }

        return implode(' ', $parts);
    }

    /**
     * Risk level badge color for Filament tables.
     */
    public function getRiskColorAttribute(): string
    {
        return match ($this->job_risk_level) {
            'LOW'     => 'success',
            'MEDIUM'  => 'warning',
            'HIGH'    => 'danger',
            'EXTREME' => 'gray',   // custom badge
            default   => 'gray',
        };
    }
}
