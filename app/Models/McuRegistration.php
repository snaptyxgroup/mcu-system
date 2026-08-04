<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\GenerateMedicalDraftJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * McuRegistration Model
 *
 * The central transaction record of the MCU system.
 * Links one patient to one project + package, generates a unique
 * physical barcode, and tracks the lifecycle from REGISTERED → COMPLETED.
 *
 * Status Lifecycle & AI Trigger:
 *  - REGISTERED   → Patient booked, no exam results yet.
 *  - IN_PROGRESS  → At least one station has submitted results.
 *  - COMPLETED    → All stations done → dispatches GenerateMedicalDraftJob.
 *
 * The model observer handles the AI dispatch on status change.
 *
 * @property int              $id
 * @property int              $patient_id
 * @property int              $project_id
 * @property int              $package_id
 * @property string           $barcode_code
 * @property string           $status          REGISTERED | IN_PROGRESS | COMPLETED
 * @property \Carbon\Carbon|null $completed_at
 * @property int|null         $registered_by
 */
class McuRegistration extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'mcu_registrations';

    protected $fillable = [
        'patient_id',
        'project_id',
        'package_id',
        'barcode_code',
        'status',
        'completed_at',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    // ── Spatie Activitylog ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'barcode_code', 'completed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "McuRegistration [{$this->barcode_code}] was {$eventName}"
            );
    }

    // ── Model Observer / Events ───────────────────────────────────────────

    protected static function booted(): void
    {
        /**
         * When a registration status transitions to COMPLETED:
         * 1. Stamp `completed_at` with the current timestamp.
         * 2. Dispatch the AI copilot job asynchronously.
         *
         * Using `updated` event with dirty-check is more reliable than
         * `saving` for async dispatch because the record is already
         * persisted to the DB before the job reads it.
         */
        static::updated(function (McuRegistration $registration) {
            if (
                $registration->wasChanged('status')
                && $registration->status === 'COMPLETED'
            ) {
                // Stamp completion time if not already set
                if ($registration->completed_at === null) {
                    $registration->updateQuietly(['completed_at' => now()]);
                }

                // Dispatch AI generation job onto the 'ai' queue
                // (configure queue worker for 'ai' queue in Horizon/supervisor)
                GenerateMedicalDraftJob::dispatch($registration)
                    ->onQueue('ai')
                    ->delay(now()->addSeconds(5)); // brief delay so all results are committed
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The patient undergoing the MCU.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * The project (MCU engagement) this registration falls under.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * The MCU package assigned to this patient.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(McuPackage::class, 'package_id');
    }

    /**
     * The staff member (receptionist) who created this registration.
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * All examination results recorded against this registration.
     * Eager-load with results.item to build the AI prompt.
     */
    public function results(): HasMany
    {
        return $this->hasMany(McuResult::class, 'registration_id');
    }

    /**
     * Only the abnormal results — used to build the AI prompt efficiently.
     */
    public function abnormalResults(): HasMany
    {
        return $this->hasMany(McuResult::class, 'registration_id')
                    ->where('is_abnormal', true)
                    ->with('item:id,name,item_code,normal_min,normal_max,unit,input_type');
    }

    /**
     * The 1:1 medical review record (created by GenerateMedicalDraftJob).
     */
    public function medicalReview(): HasOne
    {
        return $this->hasOne(MedicalReview::class, 'registration_id');
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

    public function scopeForProject(\Illuminate\Database\Eloquent\Builder $query, int $projectId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('project_id', $projectId);
    }

    // ── Domain Helpers ────────────────────────────────────────────────────

    /**
     * Generates a unique barcode string in format: MCU-YYYYMMDD-XXXX
     * Call this before creating a new registration.
     */
    public static function generateBarcode(): string
    {
        do {
            $code = 'MCU-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('barcode_code', $code)->exists());

        return $code;
    }

    /**
     * Checks whether all items in the assigned package have results.
     * Used to determine if status can be auto-advanced to COMPLETED.
     */
    public function isFullyExamined(): bool
    {
        $packageItemIds = $this->package
            ->examinationItems()
            ->pluck('examination_items.id');

        $submittedItemIds = $this->results()->pluck('item_id');

        return $packageItemIds->diff($submittedItemIds)->isEmpty();
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
