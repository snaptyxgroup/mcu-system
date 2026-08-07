<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 * @property array|null  $registration_field_template
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'org_type',
        'pic_name',
        'contact_number',
        'address',
        'registration_field_template',
    ];

    protected function casts(): array
    {
        return [
            'registration_field_template' => 'array',
            'deleted_at'                  => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * Patients who are employees of this organization.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'organization_id');
    }

    /**
     * MCU registrations for this organization.
     */
    public function mcuRegistrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'organization_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeCorporate(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('org_type', 'CORPORATE');
    }

    public function scopeVendors(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('org_type', ['CLINIC_LAB', 'HOSPITAL']);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

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
