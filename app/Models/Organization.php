<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Organization Model
 *
 * The root tenant anchor for the MCU system. Every corporate client,
 * clinic partner, hospital partner, and internal Snaptyx department
 * is represented as an Organization.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $org_type      CORPORATE | CLINIC_LAB | HOSPITAL | INTERNAL
 * @property string|null $pic_name
 * @property string|null $contact_number
 * @property string|null $address
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // ── Table & Fillable ──────────────────────────────────────────────────

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'org_type',
        'pic_name',
        'contact_number',
        'address',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    /**
     * Configure what fields are logged.
     * We log all changes to facilitate the full audit trail required
     * by healthcare regulations (e.g., PERMENKES 269/2008 in Indonesia).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "Organization [{$this->name}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * Users who belong to this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    /**
     * Projects (MCU engagements) owned by this corporate organization.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'organization_id');
    }

    /**
     * MCU packages designed for this organization.
     */
    public function mcuPackages(): HasMany
    {
        return $this->hasMany(McuPackage::class, 'organization_id');
    }

    /**
     * Patients who are employees of this organization.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'organization_id');
    }

    /**
     * Examination items for which this org acts as vendor/lab.
     */
    public function vendorItems(): HasMany
    {
        return $this->hasMany(ExaminationItem::class, 'vendor_org_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Scope: only corporate client organizations.
     */
    public function scopeCorporate(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('org_type', 'CORPORATE');
    }

    /**
     * Scope: only vendor organizations (clinic labs or hospitals).
     */
    public function scopeVendors(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('org_type', ['CLINIC_LAB', 'HOSPITAL']);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Human-readable label for the org_type enum.
     */
    public function getOrgTypeLabelAttribute(): string
    {
        return match ($this->org_type) {
            'CORPORATE'  => 'Corporate Client',
            'CLINIC_LAB' => 'Clinic / Laboratory',
            'HOSPITAL'   => 'Hospital',
            'INTERNAL'   => 'Snaptyx Internal',
            default      => $this->org_type,
        };
    }
}
