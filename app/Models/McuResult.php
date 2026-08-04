<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * McuResult Model
 *
 * Stores the recorded value for a single examination item within one
 * MCU registration. This is the most write-heavy table in the system —
 * optimised accordingly.
 *
 * Abnormality logic:
 *  - Set by the application layer when saving via `ExaminationItem::isValueAbnormal()`
 *  - Stored as a pre-computed boolean to avoid expensive joins at query time
 *  - Indexing on (registration_id, is_abnormal) enables fast AI prompt building
 *
 * @property int         $id
 * @property int         $registration_id
 * @property int         $item_id
 * @property string|null $result_value
 * @property bool        $is_abnormal
 * @property string|null $remarks
 * @property int|null    $input_by
 */
class McuResult extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'mcu_results';

    protected $fillable = [
        'registration_id',
        'item_id',
        'result_value',
        'is_abnormal',
        'remarks',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'is_abnormal' => 'boolean',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['result_value', 'is_abnormal', 'remarks'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "McuResult for item [{$this->item_id}] on registration [{$this->registration_id}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The registration this result belongs to.
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(McuRegistration::class, 'registration_id');
    }

    /**
     * The examination item (test) this result is for.
     * Eager-loaded with select to minimize memory footprint on cPanel.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ExaminationItem::class, 'item_id');
    }

    /**
     * The user (nurse/lab tech) who entered this result.
     */
    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeAbnormal(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_abnormal', true);
    }

    public function scopeForRegistration(\Illuminate\Database\Eloquent\Builder $query, int $registrationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('registration_id', $registrationId);
    }

    // ── Domain Helpers ────────────────────────────────────────────────────

    /**
     * Auto-compute `is_abnormal` from the associated item's normal range
     * and set it before saving. Call this in the controller/form action
     * before calling save() or upsert().
     */
    public function computeAndSetAbnormal(): void
    {
        if ($this->item !== null) {
            $result = $this->item->isValueAbnormal($this->result_value);
            $this->is_abnormal = $result ?? false;
        }
    }
}
