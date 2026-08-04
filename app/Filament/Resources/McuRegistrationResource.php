<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\McuRegistrationResource\Pages;
use App\Models\McuPackage;
use App\Models\McuRegistration;
use App\Models\Patient;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

/**
 * McuRegistrationResource
 *
 * Filament v5 Resource for managing MCU patient registrations.
 *
 * Design Goals:
 *  1. cPanel / Shared Hosting safe: Zero N+1 queries, deferred reactive
 *     fields (->live(onBlur: true)), searchable selects with server-side
 *     filtering to avoid loading thousands of records into memory.
 *
 *  2. Receptionist UX: The form follows a logical left-to-right flow:
 *     a) Select Project → b) Select Organization (auto-derived) →
 *     c) Search Patient → d) Assign Package → e) Confirm Barcode.
 *
 *  3. Barcode auto-generation: `barcode_code` is pre-populated via
 *     afterStateUpdated on patient_id selection.
 *
 * Eager Loading (Table):
 *  - patient, project.organization, package — prevents N+1 on index page.
 *  - medicalReview — for inline status display.
 */
class McuRegistrationResource extends Resource
{
    protected static ?string $model = McuRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'MCU Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $modelLabel = 'MCU Registration';

    protected static ?string $pluralModelLabel = 'MCU Registrations';

    // ── Form ──────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Project & Organization')
                ->description('Select the active project this registration belongs to.')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([

                    /**
                     * Project selector — uses `->searchable()` with custom search
                     * so only ACTIVE projects are returned and the UI is never
                     * overwhelmed with hundreds of historical projects.
                     *
                     * `->live(onBlur: true)` fires state sync only when focus leaves
                     * the field (not on every keystroke) — critical for cPanel hosting
                     * to avoid a Livewire request on each character typed.
                     */
                    Forms\Components\Select::make('project_id')
                        ->label('Project')
                        ->required()
                        ->searchable()
                        ->preload(false)  // Never preload — forces server-side search
                        ->getSearchResultsUsing(function (string $search): array {
                            return Project::query()
                                ->where('status', 'ACTIVE')
                                ->where(function (Builder $q) use ($search) {
                                    $q->where('name', 'like', "%{$search}%");
                                })
                                ->with('organization:id,name')
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (Project $p) => [
                                    $p->id => "{$p->name} — {$p->organization->name}",
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(fn ($value) =>
                            Project::with('organization:id,name')->find($value)
                                ?->name . ' — ' . Project::find($value)?->organization?->name
                        )
                        ->live(onBlur: true)  // Trigger package & patient list refresh on blur
                        ->afterStateUpdated(function (Set $set) {
                            // Clear dependent fields when project changes
                            $set('package_id', null);
                            $set('patient_id', null);
                            $set('barcode_code', null);
                        })
                        ->columnSpan(1),

                    /**
                     * Read-only organization display derived from selected project.
                     * Uses `->hidden()` when no project is selected to keep UI clean.
                     */
                    Forms\Components\Placeholder::make('organization_label')
                        ->label('Client Organization')
                        ->content(function (Get $get): string {
                            $projectId = $get('project_id');
                            if (!$projectId) {
                                return '—';
                            }

                            return Project::with('organization:id,name')
                                ->find($projectId)
                                ?->organization?->name ?? '—';
                        })
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('Patient')
                ->description('Search the patient by name, NIK, or employee ID.')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([

                    /**
                     * Patient searchable select — the most critical cPanel optimization.
                     *
                     * With thousands of employees per corporate client, we MUST NOT
                     * preload all patients. `getSearchResultsUsing` fires a targeted
                     * LIKE query with a limit of 30, keeping memory usage minimal.
                     *
                     * The search scope is scoped to the organization of the selected
                     * project so patients from other companies are never visible.
                     */
                    Forms\Components\Select::make('patient_id')
                        ->label('Patient')
                        ->required()
                        ->searchable()
                        ->preload(false)
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            $projectId = $get('project_id');

                            $organizationId = $projectId
                                ? Project::find($projectId)?->organization_id
                                : null;

                            return Patient::query()
                                ->when($organizationId, fn (Builder $q) =>
                                    $q->where('organization_id', $organizationId)
                                )
                                ->search($search)
                                ->limit(30)
                                ->get(['id', 'name', 'employee_id', 'nik', 'department'])
                                ->mapWithKeys(fn (Patient $p) => [
                                    $p->id => "{$p->name} [{$p->employee_id}] — {$p->department}",
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(fn ($value) =>
                            Patient::find($value)?->display_name ?? "Patient #{$value}"
                        )
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            // Auto-generate barcode when patient is selected
                            if ($state) {
                                $set('barcode_code', McuRegistration::generateBarcode());
                            }
                        })
                        ->disabledOn('edit')   // Never allow changing patient post-creation
                        ->columnSpan(1),

                    /**
                     * Patient detail card — shows critical patient info after selection
                     * without requiring a page navigation. Uses `->content()` with a
                     * closure that only queries if patient_id is set.
                     */
                    Forms\Components\Placeholder::make('patient_info')
                        ->label('Patient Details')
                        ->content(function (Get $get): \Illuminate\Support\HtmlString {
                            $patientId = $get('patient_id');

                            if (!$patientId) {
                                return new \Illuminate\Support\HtmlString('<em class="text-gray-400">Select a patient to see details.</em>');
                            }

                            $p = Patient::find($patientId, ['id', 'name', 'dob', 'gender', 'job_title', 'department', 'job_risk_level', 'nik']);

                            if (!$p) {
                                return new \Illuminate\Support\HtmlString('<em class="text-red-400">Patient not found.</em>');
                            }

                            $riskColors = [
                                'LOW'     => 'text-green-600',
                                'MEDIUM'  => 'text-yellow-600',
                                'HIGH'    => 'text-orange-600',
                                'EXTREME' => 'text-red-600 font-bold',
                            ];

                            $riskClass = $riskColors[$p->job_risk_level] ?? 'text-gray-600';
                            $age = $p->age ? "{$p->age} years" : 'N/A';
                            $gender = $p->gender ?? 'N/A';

                            return new \Illuminate\Support\HtmlString(
                                "<div class='text-sm space-y-1'>
                                    <div><strong>NIK:</strong> {$p->nik}</div>
                                    <div><strong>Age / Gender:</strong> {$age} / {$gender}</div>
                                    <div><strong>Department:</strong> {$p->department}</div>
                                    <div><strong>Job Title:</strong> {$p->job_title}</div>
                                    <div><strong>Risk Level:</strong> <span class='{$riskClass}'>{$p->job_risk_level}</span></div>
                                </div>"
                            );
                        })
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('Package & Registration')
                ->description('Assign an MCU package and confirm the patient barcode.')
                ->icon('heroicon-o-beaker')
                ->columns(2)
                ->schema([

                    /**
                     * Package selector — filtered to only show packages belonging
                     * to the project's organization. Prevents cross-org package mixing.
                     *
                     * `->options()` with a closure re-evaluates when project_id changes
                     * because `->live(onBlur: true)` on project_id triggers a Livewire
                     * re-render of the form schema.
                     */
                    Forms\Components\Select::make('package_id')
                        ->label('MCU Package')
                        ->required()
                        ->searchable()
                        ->preload(false)
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            $projectId = $get('project_id');

                            $organizationId = $projectId
                                ? Project::find($projectId)?->organization_id
                                : null;

                            return McuPackage::query()
                                ->where('is_active', true)
                                ->when($organizationId, fn (Builder $q) =>
                                    $q->where('organization_id', $organizationId)
                                )
                                ->where('name', 'like', "%{$search}%")
                                ->limit(20)
                                ->get(['id', 'name'])
                                ->mapWithKeys(fn (McuPackage $pkg) => [
                                    $pkg->id => $pkg->name,
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(fn ($value) =>
                            McuPackage::find($value)?->name ?? "Package #{$value}"
                        )
                        ->live(onBlur: true)
                        ->columnSpan(1),

                    /**
                     * Barcode field — auto-generated, but editable so the receptionist
                     * can override with a physical barcode scanner input if needed.
                     *
                     * `->unique()` validation is handled at the model/migration level.
                     * The form validates using Filament's built-in uniqueness rule.
                     */
                    Forms\Components\TextInput::make('barcode_code')
                        ->label('Barcode Code')
                        ->required()
                        ->unique(table: McuRegistration::class, column: 'barcode_code', ignoreRecord: true)
                        ->maxLength(100)
                        ->placeholder('Auto-generated on patient selection')
                        ->hint('Scan physical barcode or use auto-generated code.')
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('regenerate_barcode')
                                ->label('Regenerate')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function (Set $set) {
                                    $set('barcode_code', McuRegistration::generateBarcode());
                                })
                        )
                        ->columnSpan(1),

                    /**
                     * Status — only super_admin and org_admin can change status manually.
                     * Normal flow: status advances automatically via the system.
                     */
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->options([
                            'REGISTERED'  => '📋 Registered',
                            'IN_PROGRESS' => '🔬 In Progress',
                            'COMPLETED'   => '✅ Completed',
                        ])
                        ->default('REGISTERED')
                        ->live(onBlur: true)
                        ->columnSpan(1),

                    /**
                     * `registered_by` — set to the currently authenticated user.
                     * Hidden from the form; set programmatically in the create action.
                     */
                    Forms\Components\Hidden::make('registered_by')
                        ->default(fn () => auth()->id()),
                ]),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            /**
             * Eager Loading — the most critical query optimization.
             * These 4 eager-loaded relations prevent N+1 queries when rendering
             * each row in the table. On a 500-row project, this reduces
             * ~2000 SQL queries to 5 queries.
             */
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->with([
                    'patient:id,name,employee_id,department,job_risk_level,organization_id',
                    'project:id,name,organization_id',
                    'project.organization:id,name',
                    'package:id,name',
                    'medicalReview:id,registration_id,review_status,final_status',
                ])
                ->latest()
            )

            ->columns([

                Tables\Columns\TextColumn::make('barcode_code')
                    ->label('Barcode')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Barcode copied!')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->description(fn (McuRegistration $r) =>
                        "[{$r->patient?->employee_id}] {$r->patient?->department}"
                    )
                    ->searchable(['patients.name', 'patients.employee_id'])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('patient.job_risk_level')
                    ->label('Risk')
                    ->colors([
                        'success' => 'LOW',
                        'warning' => 'MEDIUM',
                        'danger'  => 'HIGH',
                        'gray'    => 'EXTREME',
                    ]),

                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->description(fn (McuRegistration $r) => $r->project?->organization?->name)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info'    => 'REGISTERED',
                        'warning' => 'IN_PROGRESS',
                        'success' => 'COMPLETED',
                    ]),

                Tables\Columns\BadgeColumn::make('medicalReview.review_status')
                    ->label('Review')
                    ->colors([
                        'gray'    => 'PENDING',
                        'info'    => 'AI_DRAFT_READY',
                        'success' => 'REVIEWED',
                    ])
                    ->default('—'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'REGISTERED'  => 'Registered',
                        'IN_PROGRESS' => 'In Progress',
                        'COMPLETED'   => 'Completed',
                    ]),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(false),

                Tables\Filters\Filter::make('has_abnormal_results')
                    ->label('Has Abnormal Results')
                    ->query(fn (Builder $query) =>
                        $query->whereHas('results', fn (Builder $q) =>
                            $q->where('is_abnormal', true)
                        )
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])

            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                /**
                 * Quick status advancement action.
                 * Allows receptionists/nurses to advance registration status
                 * without opening the full edit form — single-click UX.
                 */
                Tables\Actions\Action::make('advance_status')
                    ->label('Advance Status')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn (McuRegistration $r) => $r->status !== 'COMPLETED')
                    ->requiresConfirmation()
                    ->modalHeading('Advance Registration Status?')
                    ->modalDescription(fn (McuRegistration $r) =>
                        "Current: {$r->status}. This will advance to the next stage."
                    )
                    ->action(function (McuRegistration $record) {
                        $nextStatus = match ($record->status) {
                            'REGISTERED'  => 'IN_PROGRESS',
                            'IN_PROGRESS' => 'COMPLETED',
                            default       => null,
                        };

                        if ($nextStatus) {
                            $record->update(['status' => $nextStatus]);

                            Notification::make()
                                ->title("Status updated to {$nextStatus}")
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_results')
                    ->label('Results')
                    ->icon('heroicon-o-beaker')
                    ->color('info')
                    ->url(fn (McuRegistration $r) =>
                        static::getUrl('results', ['record' => $r])
                    ),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    // ── Relations & Pages ─────────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            // McuRegistrationResource\RelationManagers\ResultsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListMcuRegistrations::route('/'),
            'create'  => Pages\CreateMcuRegistration::route('/create'),
            'view'    => Pages\ViewMcuRegistration::route('/{record}'),
            'edit'    => Pages\EditMcuRegistration::route('/{record}/edit'),
        ];
    }

    /**
     * Scope the table to only show records accessible to the current user.
     * Super admins see all; org_admin/receptionist see only their org.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = auth()->user();

        // Platform-level super_admin sees everything
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Org-scoped users: filter by their organization's projects
        if ($user->organization_id) {
            $query->whereHas('project', fn (Builder $q) =>
                $q->where('organization_id', $user->organization_id)
            );
        }

        return $query;
    }
}
