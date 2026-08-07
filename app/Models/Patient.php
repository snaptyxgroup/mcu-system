<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient Model
 *
 * Represents an employee of a corporate client undergoing MCU.
 *
 * @property int              $id
 * @property int              $organization_id
 * @property string|null      $nik
 * @property string|null      $employee_id
 * @property string           $name
 * @property \Carbon\Carbon|null $dob
 * @property string|null      $gender
 * @property string|null      $department
 * @property string|null      $job_title
 * @property string           $job_risk_level
 * @property array|null       $custom_attributes
 */
class Patient extends Model
{
    use HasFactory, SoftDeletes;

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
            'custom_attributes' => 'array',
            'dob'               => 'date',
            'deleted_at'        => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'patient_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('nik', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%");
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];
        if ($this->employee_id) {
            $parts[] = "({$this->employee_id})";
        }
        return implode(' ', $parts);
    }
}
