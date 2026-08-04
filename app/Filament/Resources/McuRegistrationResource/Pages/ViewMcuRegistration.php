<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use App\Models\McuResult;
use Filament\Actions;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewMcuRegistration Page
 *
 * The detailed view of a single registration, showing:
 *  - Patient & project metadata
 *  - All examination results (normal + abnormal) with color coding
 *  - Medical review status with AI recommendation
 *
 * Uses Filament's Infolist API for rich, read-only display.
 */
class ViewMcuRegistration extends ViewRecord
{
    protected static string $resource = McuRegistrationResource::class;

    /**
     * Eager load all relations needed for the view page.
     * Prevents N+1 when rendering the result list repeater.
     */
    protected function resolveRecord(int|string $key): \App\Models\McuRegistration
    {
        return \App\Models\McuRegistration::with([
            'patient.organization',
            'project.organization',
            'package.examinationItems',
            'results.item.station',
            'results.inputBy:id,name',
            'medicalReview.reviewer:id,name',
        ])->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('go_to_review')
                ->label('Open Medical Review')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->url(fn () =>
                    \App\Filament\Resources\MedicalReviewResource::getUrl('view', [
                        'record' => $this->getRecord()->medicalReview?->id,
                    ])
                )
                ->visible(fn () => $this->getRecord()->medicalReview !== null),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Section::make('Registration Overview')
                ->icon('heroicon-o-clipboard-document-list')
                ->columns(3)
                ->schema([
                    TextEntry::make('barcode_code')
                        ->label('Barcode')
                        ->fontFamily('mono')
                        ->copyable(),

                    BadgeEntry::make('status')
                        ->label('Status')
                        ->colors([
                            'info'    => 'REGISTERED',
                            'warning' => 'IN_PROGRESS',
                            'success' => 'COMPLETED',
                        ]),

                    TextEntry::make('completed_at')
                        ->label('Completed At')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not yet completed'),
                ]),

            Section::make('Patient Information')
                ->icon('heroicon-o-user')
                ->columns(3)
                ->schema([
                    TextEntry::make('patient.name')->label('Name'),
                    TextEntry::make('patient.employee_id')->label('Employee ID'),
                    TextEntry::make('patient.nik')->label('NIK'),
                    TextEntry::make('patient.dob')->label('Date of Birth')->date('d M Y'),
                    TextEntry::make('patient.gender')->label('Gender'),
                    TextEntry::make('patient.age')->label('Age')->suffix(' years'),
                    TextEntry::make('patient.department')->label('Department'),
                    TextEntry::make('patient.job_title')->label('Job Title'),
                    BadgeEntry::make('patient.job_risk_level')
                        ->label('Risk Level')
                        ->colors([
                            'success' => 'LOW',
                            'warning' => 'MEDIUM',
                            'danger'  => 'HIGH',
                            'gray'    => 'EXTREME',
                        ]),
                ]),

            Section::make('Project & Package')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([
                    TextEntry::make('project.name')->label('Project'),
                    TextEntry::make('project.organization.name')->label('Client'),
                    TextEntry::make('package.name')->label('MCU Package'),
                    TextEntry::make('registeredBy.name')->label('Registered By'),
                ]),

            Section::make('Examination Results')
                ->icon('heroicon-o-beaker')
                ->schema([
                    RepeatableEntry::make('results')
                        ->label('')
                        ->schema([
                            Grid::make(5)->schema([
                                TextEntry::make('item.station.name')
                                    ->label('Station')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('item.name')
                                    ->label('Test Name'),

                                TextEntry::make('result_value')
                                    ->label('Result')
                                    ->weight(fn (McuResult $r) =>
                                        $r->is_abnormal ? 'bold' : 'normal'
                                    )
                                    ->color(fn (McuResult $r) =>
                                        $r->is_abnormal ? 'danger' : 'success'
                                    )
                                    ->suffix(fn (McuResult $r) =>
                                        $r->item?->unit ? " {$r->item->unit}" : ''
                                    ),

                                TextEntry::make('item.normal_range_display')
                                    ->label('Normal Range'),

                                BadgeEntry::make('is_abnormal')
                                    ->label('Flag')
                                    ->formatStateUsing(fn (bool $state) =>
                                        $state ? '⚠️ Abnormal' : '✓ Normal'
                                    )
                                    ->colors([
                                        'danger'  => true,
                                        'success' => false,
                                    ]),
                            ]),
                        ]),
                ]),

            Section::make('AI Medical Review')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn () => $this->getRecord()->medicalReview !== null)
                ->schema([
                    BadgeEntry::make('medicalReview.review_status')
                        ->label('Review Status')
                        ->colors([
                            'gray'    => 'PENDING',
                            'info'    => 'AI_DRAFT_READY',
                            'success' => 'REVIEWED',
                        ]),

                    BadgeEntry::make('medicalReview.final_status')
                        ->label('Final Fitness Status')
                        ->colors([
                            'success' => 'FIT TO WORK',
                            'warning' => 'FIT WITH NOTE',
                            'danger'  => 'TEMPORARY UNFIT',
                            'gray'    => 'UNFIT',
                        ]),

                    TextEntry::make('medicalReview.reviewer.name')
                        ->label('Reviewed By')
                        ->placeholder('Awaiting doctor review'),

                    TextEntry::make('medicalReview.reviewed_at')
                        ->label('Reviewed At')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not yet reviewed'),
                ]),
        ]);
    }
}
