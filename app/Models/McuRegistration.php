<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * McuRegistration Model
 *
 * The central transaction record of the MCU system.
 * Links one patient to one organization, generates a unique physical
 * barcode, assigns stations, and tracks the lifecycle from
 * REGISTERED → IN_PROGRESS → COMPLETED.
 *
 * Key features:
 *  - `organization()` BelongsTo — direct company link for cascading selects
 *  - `stations()` BelongsToMany — dynamic station assignment via pivot
 *  - `custom_fields` JSON — company-specific demographic data
 *  - `employee_photo` — webcam-captured photo file path
 *
 * @property int              $id
 * @property int              $organization_id
 * @property int              $patient_id
 * @property string           $barcode_code
 * @property string           $status
 * @property array|null       $custom_fields
 * @property string|null      $employee_photo
 * @property \Carbon\Carbon|null $completed_at
 * @property int|null         $registered_by
 */
class McuRegistration extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mcu_registrations';

    protected $fillable = [
        'organization_id',
        'patient_id',
        'barcode_code',
        'status',
        'custom_fields',
        'employee_photo',
        'completed_at',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'completed_at'  => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The corporate organization (Company) this registration belongs to.
     * Used for the searchable Select in the Filament form.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * The patient undergoing the MCU.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * The staff member (receptionist) who created this registration.
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Stations (Stasiun Pemeriksaan) assigned to this registration.
     * Many-to-Many via the `mcu_registration_station` pivot table.
     *
     * Pivot columns: checked_in_at, checked_out_at, status
     */
    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(
            Station::class,
            'mcu_registration_station',
            'mcu_registration_id',
            'station_id'
        )
        ->withPivot(['checked_in_at', 'checked_out_at', 'status'])
        ->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'COMPLETED');
    }

    public function scopeInProgress(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'IN_PROGRESS');
    }

    public function scopeForOrganization(\Illuminate\Database\Eloquent\Builder $query, int $orgId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', $orgId);
    }

    // ── Domain Helpers ────────────────────────────────────────────────────

    /**
     * Generates a unique barcode string in format: MCU-YYYYMMDD-XXXXXX
     */
    public static function generateBarcode(): string
    {
        do {
            $code = 'MCU-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('barcode_code', $code)->exists());

        return $code;
    }

    /**
     * Badge color for the status column in Filament tables.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'REGISTERED'  => 'info',
            'IN_PROGRESS' => 'warning',
            'COMPLETED'   => 'success',
            default       => 'gray',
        };
    }
}
