<?php

declare(strict_types=1);

namespace App\Filament\Resources\MedicalReviewResource\Pages;

use App\Filament\Resources\MedicalReviewResource;
use App\Models\MedicalReview;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * ListMedicalReviews Page
 *
 * The doctor's primary worklist. Uses tabs to separate:
 *  - "Action Required" → AI_DRAFT_READY (highest priority, shown first)
 *  - "Pending AI"      → PENDING (AI job is still running)
 *  - "Finalized"       → REVIEWED
 *
 * POLLING STRATEGY (cPanel safe):
 * `->poll('15s')` is applied ONLY on the "Pending AI" and "Action Required"
 * tabs where real-time updates matter (waiting for AI job completion).
 * The "Finalized" tab is static — no polling.
 *
 * This avoids a blanket poll that would hammer the cPanel MySQL connection
 * pool with constant queries even when the doctor is on a static view.
 */
class ListMedicalReviews extends ListRecords
{
    protected static string $resource = MedicalReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No "Create" button — reviews are created by GenerateMedicalDraftJob
        ];
    }

    public function getTabs(): array
    {
        $pendingCount   = MedicalReview::pending()->count();
        $draftReadyCount = MedicalReview::aiDraftReady()->count();
        $reviewedCount  = MedicalReview::reviewed()->count();

        return [

            /**
             * "Action Required" tab — doctor's highest priority queue.
             * Polls every 15 seconds because new AI drafts arrive constantly
             * during an active MCU session.
             */
            'action_required' => Tab::make('🔴 Action Required')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('review_status', 'AI_DRAFT_READY')
                )
                ->badge($draftReadyCount > 0 ? $draftReadyCount : null)
                ->badgeColor('danger'),

            /**
             * "Pending AI" tab — waiting for Gemini to respond.
             * Also polls to detect when AI_DRAFT_READY transitions occur.
             */
            'pending_ai' => Tab::make('⏳ Pending AI')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('review_status', 'PENDING')
                )
                ->badge($pendingCount > 0 ? $pendingCount : null)
                ->badgeColor('warning'),

            /**
             * "Finalized" tab — completed reviews.
             * No polling needed — data is static once REVIEWED.
             */
            'finalized' => Tab::make('✅ Finalized')
                ->modifyQueryUsing(fn (Builder $q) =>
                    $q->where('review_status', 'REVIEWED')
                )
                ->badge($reviewedCount)
                ->badgeColor('success'),

            'all' => Tab::make('All')
                ->icon('heroicon-o-list-bullet'),
        ];
    }

    /**
     * Apply polling conditionally based on the active tab.
     * Only the "live" tabs get the 15-second poll refresh.
     *
     * Filament v5 does not expose a direct per-tab poll API, so we use
     * the `getPollingInterval()` override and check the active tab key.
     */
    public function getPollingInterval(): ?string
    {
        $activeTab = $this->activeTab ?? 'all';

        // Poll only when waiting for AI updates
        if (in_array($activeTab, ['pending_ai', 'action_required'], true)) {
            return '15s';
        }

        return null; // No polling for finalized/all tabs
    }
}
