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
 * ExaminationItem Model
 *
 * An atomic test or procedure in the MCU catalog (e.g., Hemoglobin, Chest X-Ray).
 * Each item belongs to one station and optionally to a vendor organization.
 *
 * Abnormality computation:
 *   For numeric items: result is abnormal if outside [normal_min, normal_max].
 *   For text items: no automatic flagging — requires `remarks` or manual is_abnormal.
 *
 * @property int          $id
 * @property int          $station_id
 * @property int|null     $vendor_org_id
 * @property string       $item_code
 * @property string       $name
 * @property float|null   $normal_min
 * @property float|null   $normal_max
 * @property string|null  $normal_text
 * @property string       $input_type    numeric | text
 * @property string|null  $unit
 * @property bool         $is_active
 */
class ExaminationItem extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'examination_items';

    protected $fillable = [
        'station_id',
        'vendor_org_id',
        'item_code',
        'name',
        'normal_min',
        'normal_max',
        'normal_text',
        'input_type',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'normal_min' => 'float',
            'normal_max' => 'float',
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
                fn (string $eventName) => "ExaminationItem [{$this->item_code}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The station where this item is performed.
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    /**
     * The vendor organization (clinic/lab) that performs this item.
     * Null for Snaptyx in-house procedures.
     */
    public function vendorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'vendor_org_id');
    }

    /**
     * MCU packages that include this item.
     */
    public function mcuPackages(): BelongsToMany
    {
        return $this->belongsToMany(McuPackage::class, 'package_items', 'item_id', 'package_id');
    }

    /**
     * All results recorded for this item across registrations.
     */
    public function results(): HasMany
    {
        return $this->hasMany(McuResult::class, 'item_id');
    }

    // ── Domain Methods ────────────────────────────────────────────────────

    /**
     * Determines if a given numeric value falls outside the normal range.
     * Returns null for text-type items (cannot auto-determine abnormality).
     */
    public function isValueAbnormal(string|float|null $value): ?bool
    {
        if ($this->input_type !== 'numeric' || $value === null) {
            return null;
        }

        $numeric = (float) $value;

        if ($this->normal_min !== null && $numeric < $this->normal_min) {
            return true;
        }

        if ($this->normal_max !== null && $numeric > $this->normal_max) {
            return true;
        }

        return false;
    }

    /**
     * Returns a formatted normal range string for display:
     * e.g., "12.0 – 17.5 g/dL" or "< 200 mg/dL" or "Negative"
     */
    public function getNormalRangeDisplayAttribute(): string
    {
        if ($this->input_type === 'text') {
            return $this->normal_text ?? 'N/A';
        }

        $unit = $this->unit ? " {$this->unit}" : '';

        if ($this->normal_min !== null && $this->normal_max !== null) {
            return "{$this->normal_min} – {$this->normal_max}{$unit}";
        }

        if ($this->normal_min !== null) {
            return "> {$this->normal_min}{$unit}";
        }

        if ($this->normal_max !== null) {
            return "< {$this->normal_max}{$unit}";
        }

        return 'Not defined';
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNumeric(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('input_type', 'numeric');
    }
}
