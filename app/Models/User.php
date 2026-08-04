<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * User Model
 *
 * Extends Laravel's Authenticatable with:
 *  - Spatie RBAC (roles: super_admin, org_admin, doctor, nurse, lab_tech, receptionist)
 *  - Spatie Activitylog for full audit trail
 *  - Organization membership (nullable = platform-level admin)
 *  - Station assignments via pivot
 *
 * @property int              $id
 * @property int|null         $organization_id
 * @property string           $name
 * @property string           $email
 * @property bool             $is_active
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    // ── Fillable ──────────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'is_active',
    ];

    // ── Hidden ────────────────────────────────────────────────────────────

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'organization_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "User [{$this->email}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The organization this user belongs to (null for super-admins).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Stations this user is assigned to work at (via schedule pivot).
     */
    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'user_stations', 'user_id', 'station_id')
                    ->withPivot('assigned_date')
                    ->withTimestamps()
                    ->orderByPivot('assigned_date', 'desc');
    }

    /**
     * MCU results entered by this user (lab tech / nurse).
     */
    public function mcuResults(): HasMany
    {
        return $this->hasMany(McuResult::class, 'input_by');
    }

    /**
     * Medical reviews conducted by this user (doctor).
     */
    public function medicalReviews(): HasMany
    {
        return $this->hasMany(MedicalReview::class, 'reviewed_by');
    }

    /**
     * MCU registrations created by this user (receptionist).
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(McuRegistration::class, 'registered_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Scope: only active users.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: doctors only (for medical review assignment).
     */
    public function scopeDoctors(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->role('doctor');
    }
}
