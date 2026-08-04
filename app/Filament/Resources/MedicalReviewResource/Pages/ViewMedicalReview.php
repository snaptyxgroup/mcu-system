<?php

declare(strict_types=1);

namespace App\Filament\Resources\MedicalReviewResource\Pages;

use App\Filament\Resources\MedicalReviewResource;
use App\Models\MedicalReview;
use Filament\Actions;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewMedicalReview Page
 *
 * Rich read-only view of a completed medical review.
 * Intended for:
 *  - Post-review audit by org_admin / super_admin
 *  - Patient result sharing (future feature hook)
 */
class ViewMedicalReview extends ViewRecord
{
    protected static string $resource = MedicalReviewResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn () => $record->review_status !== 'REVIEWED'),

            Actions\Action::make('approve')
                ->label('Approve & Sign')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () =>
                    $record->review_status === 'AI_DRAFT_READY'
                    && $record->doctor_conclusion !== null
                    && $record->final_status !== null
                )
                ->action(function () use ($record) {
                    $record->approve(auth()->id(), $record->final_status);

                    \Filament\Notifications\Notification::make()
                        ->title('Medical review signed and finalized.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['review_status', 'reviewed_at', 'reviewed_by']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Section::make('Review Status')
                ->columns(3)
                ->schema([
                    BadgeEntry::make('review_status')
                        ->label('Workflow Status')
                        ->colors([
                            'gray'    => 'PENDING',
                            'info'    => 'AI_DRAFT_READY',
                            'success' => 'REVIEWED',
                        ]),

                    BadgeEntry::make('ai_recommended_status')
                        ->label('AI Recommendation')
                        ->colors([
                            'success' => 'FIT TO WORK',
                            'warning' => 'FIT WITH NOTE',
                            'danger'  => 'TEMPORARY UNFIT',
                            'gray'    => 'UNFIT',
                        ])
                        ->placeholder('—'),

                    BadgeEntry::make('final_status')
                        ->label('Final Status')
                        ->colors([
                            'success' => 'FIT TO WORK',
                            'warning' => 'FIT WITH NOTE',
                            'danger'  => 'TEMPORARY UNFIT',
                            'gray'    => 'UNFIT',
                        ])
                        ->placeholder('Pending Doctor Review'),
                ]),

            Section::make('Patient & Registration Context')
                ->columns(3)
                ->schema([
                    TextEntry::make('registration.patient.name')->label('Patient'),
                    TextEntry::make('registration.patient.employee_id')->label('Employee ID'),
                    TextEntry::make('registration.barcode_code')->label('Barcode')->fontFamily('mono'),
                    TextEntry::make('registration.patient.department')->label('Department'),
                    TextEntry::make('registration.patient.job_title')->label('Job Title'),
                    BadgeEntry::make('registration.patient.job_risk_level')
                        ->label('Risk Level')
                        ->colors([
                            'success' => 'LOW',
                            'warning' => 'MEDIUM',
                            'danger'  => 'HIGH',
                            'gray'    => 'EXTREME',
                        ]),
                ]),

            Section::make('🤖 AI Copilot Summary')
                ->icon('heroicon-o-cpu-chip')
                ->schema([
                    TextEntry::make('ai_summary')
                        ->label('')
                        ->prose()
                        ->markdown()
                        ->columnSpanFull(),
                ]),

            Section::make('🩺 Doctor\'s Assessment')
                ->icon('heroicon-o-pencil-square')
                ->columns(1)
                ->schema([
                    TextEntry::make('doctor_conclusion')
                        ->label('Clinical Conclusion')
                        ->prose()
                        ->placeholder('Not yet provided.'),

                    TextEntry::make('doctor_notes')
                        ->label('Doctor\'s Notes & Recommendations')
                        ->prose()
                        ->placeholder('No additional notes.'),
                ]),

            Section::make('Sign-off Details')
                ->icon('heroicon-o-identification')
                ->columns(2)
                ->schema([
                    TextEntry::make('reviewer.name')
                        ->label('Reviewed By')
                        ->placeholder('Not yet reviewed'),

                    TextEntry::make('reviewed_at')
                        ->label('Reviewed At')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Not yet reviewed'),
                ]),
        ]);
    }
}
