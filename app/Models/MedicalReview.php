<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MedicalReview Model
 *
 * The final medical fitness opinion for one MCU registration.
 * This record has a strict 1:1 relationship with McuRegistration.
 *
 * Workflow:
 *  1. GenerateMedicalDraftJob creates this record with review_status = PENDING.
 *  2. On Gemini response: updates ai_summary, ai_recommended_status → AI_DRAFT_READY.
 *  3. Doctor opens the Filament review page, sees AI draft, adds conclusion → REVIEWED.
 *
 * The `final_status` field is the legally binding fitness determination.
 *
 * @property int              $id
 * @property int              $registration_id
 * @property string|null      $ai_summary
 * @property string|null      $ai_recommended_status
 * @property string|null      $doctor_conclusion
 * @property string|null      $doctor_notes
 * @property string|null      $final_status
 * @property int|null         $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 * @property string           $review_status    PENDING | AI_DRAFT_READY | REVIEWED
 */
class MedicalReview extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'medical_reviews';

    protected $fillable = [
        'registration_id',
        'ai_summary',
        'ai_recommended_status',
        'doctor_conclusion',
        'doctor_notes',
        'final_status',
        'reviewed_by',
        'reviewed_at',
        'review_status',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'review_status',
                'final_status',
                'ai_recommended_status',
                'doctor_conclusion',
                'reviewed_by',
                'reviewed_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "MedicalReview for registration [{$this->registration_id}] was {$eventName}"
            );
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The MCU registration this review belongs to.
     * Always eager-load: registration.patient, registration.project
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(McuRegistration::class, 'registration_id');
    }

    /**
     * The doctor who performed the review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Reviews awaiting AI draft (job dispatched but not yet complete).
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('review_status', 'PENDING');
    }

    /**
     * Reviews where AI draft is ready — doctor's action required.
     */
    public function scopeAiDraftReady(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('review_status', 'AI_DRAFT_READY');
    }

    /**
     * Reviews that the doctor has finalized.
     */
    public function scopeReviewed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('review_status', 'REVIEWED');
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Badge color for final_status in Filament tables.
     */
    public function getFinalStatusColorAttribute(): string
    {
        return match ($this->final_status) {
            'FIT TO WORK'      => 'success',
            'FIT WITH NOTE'    => 'warning',
            'TEMPORARY UNFIT'  => 'danger',
            'UNFIT'            => 'gray',
            default            => 'gray',
        };
    }

    /**
     * Badge color for review_status workflow state.
     */
    public function getReviewStatusColorAttribute(): string
    {
        return match ($this->review_status) {
            'PENDING'         => 'gray',
            'AI_DRAFT_READY'  => 'info',
            'REVIEWED'        => 'success',
            default           => 'gray',
        };
    }

    /**
     * True if the doctor can still edit this review (not yet finalized).
     */
    public function getIsEditableAttribute(): bool
    {
        return $this->review_status !== 'REVIEWED';
    }

    // ── Domain Methods ────────────────────────────────────────────────────

    /**
     * Stamp the doctor's approval and transition to REVIEWED status.
     * Call this instead of direct attribute manipulation to ensure
     * consistency.
     *
     * @param  int    $doctorUserId
     * @param  string $finalStatus   One of the final_status enum values
     * @return bool
     */
    public function approve(int $doctorUserId, string $finalStatus): bool
    {
        return $this->update([
            'final_status'   => $finalStatus,
            'reviewed_by'    => $doctorUserId,
            'reviewed_at'    => now(),
            'review_status'  => 'REVIEWED',
        ]);
    }
}
