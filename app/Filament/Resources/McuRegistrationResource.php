<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\McuRegistrationResource\Pages;
use App\Models\McuRegistration;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Station;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

/**
 * McuRegistrationResource
 *
 * Filament v3 Resource for managing MCU patient registrations.
 *
 * Implements all 5 business requirements:
 *  1. Company/Organization searchable Select (BelongsTo)
 *  2. Dynamic Stations via CheckboxList (M2M pivot)
 *  3. Custom Fields via KeyValue (JSON column)
 *  4. Bulk Excel Import (HeaderAction on List page)
 *  5. Webcam Photo Capture (custom ViewField)
 *
 * Form flow:
 *  a) Select Organization → b) Search Patient (filtered by org) →
 *  c) Assign Stations → d) Fill custom fields → e) Capture photo →
 *  f) Confirm Barcode → g) Submit
 */
class McuRegistrationResource extends Resource
{
    protected static ?string $model = McuRegistration::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = 'MCU Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $modelLabel = 'MCU Registration';

    protected static ?string $pluralModelLabel = 'MCU Registrations';

    // ── Form ──────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 1: Company / Organization Selection
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Company / Organization')
                ->description('Select the corporate client for this MCU registration.')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([

                    /**
                     * Requirement #1: Organization BelongsTo Select
                     *
                     * Uses searchable Select with server-side search to avoid
                     * preloading all organizations into memory.
                     * `->live(onBlur: true)` triggers Patient list refresh when
                     * focus leaves the field (cPanel-safe: no request per keystroke).
                     */
                    Forms\Components\Select::make('organization_id')
                        ->label('Organization / Company')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->relationship(
                            name: 'organization',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->where('org_type', 'CORPORATE')
                                ->orderBy('name')
                        )
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set) {
                            // Clear dependent fields when organization changes
                            $set('patient_id', null);
                        })
                        ->columnSpan(1)
                        ->helperText('Only CORPORATE type organizations are shown.'),

                    /**
                     * Organization info placeholder — shows PIC and contact
                     * for the selected organization without a separate query page.
                     */
                    Forms\Components\Placeholder::make('organization_info')
                        ->label('Organization Details')
                        ->content(function (Get $get): HtmlString {
                            $orgId = $get('organization_id');
                            if (!$orgId) {
                                return new HtmlString(
                                    '<em class="text-gray-400">Select an organization to see details.</em>'
                                );
                            }

                            $org = Organization::find($orgId, ['id', 'name', 'pic_name', 'contact_number', 'address']);
                            if (!$org) {
                                return new HtmlString(
                                    '<em class="text-red-400">Organization not found.</em>'
                                );
                            }

                            return new HtmlString(
                                "<div class='text-sm space-y-1'>
                                    <div><strong>PIC:</strong> " . e($org->pic_name ?? 'N/A') . "</div>
                                    <div><strong>Contact:</strong> " . e($org->contact_number ?? 'N/A') . "</div>
                                    <div><strong>Address:</strong> " . e($org->address ?? 'N/A') . "</div>
                                </div>"
                            );
                        })
                        ->columnSpan(1),
                ]),

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 2: Patient Selection
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Patient')
                ->description('Search the patient by name, NIK, or employee ID.')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([

                    /**
                     * Patient searchable select — scoped to the selected
                     * organization so patients from other companies are
                     * never visible. Uses server-side search with a limit
                     * of 30 to keep memory usage minimal.
                     */
                    Forms\Components\Select::make('patient_id')
                        ->label('Patient')
                        ->required()
                        ->searchable()
                        ->preload(false)
                        ->getSearchResultsUsing(function (string $search, Get $get): array {
                            $organizationId = $get('organization_id');

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
                        ->disabledOn('edit')
                        ->columnSpan(1),

                    /**
                     * Patient detail card — shows critical patient info
                     * after selection without a page navigation.
                     */
                    Forms\Components\Placeholder::make('patient_info')
                        ->label('Patient Details')
                        ->content(function (Get $get): HtmlString {
                            $patientId = $get('patient_id');

                            if (!$patientId) {
                                return new HtmlString(
                                    '<em class="text-gray-400">Select a patient to see details.</em>'
                                );
                            }

                            $p = Patient::find($patientId, [
                                'id', 'name', 'dob', 'gender', 'job_title',
                                'department', 'job_risk_level', 'nik',
                            ]);

                            if (!$p) {
                                return new HtmlString(
                                    '<em class="text-red-400">Patient not found.</em>'
                                );
                            }

                            $age = $p->age ? "{$p->age} years" : 'N/A';
                            $gender = $p->gender ?? 'N/A';

                            return new HtmlString(
                                "<div class='text-sm space-y-1'>
                                    <div><strong>NIK:</strong> " . e($p->nik ?? 'N/A') . "</div>
                                    <div><strong>Age / Gender:</strong> {$age} / {$gender}</div>
                                    <div><strong>Department:</strong> " . e($p->department ?? 'N/A') . "</div>
                                    <div><strong>Job Title:</strong> " . e($p->job_title ?? 'N/A') . "</div>
                                    <div><strong>Risk Level:</strong> <span class='font-semibold'>{$p->job_risk_level}</span></div>
                                </div>"
                            );
                        })
                        ->columnSpan(1),
                ]),

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 3: Barcode & Status
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Registration Details')
                ->description('Barcode and registration status.')
                ->icon('heroicon-o-qr-code')
                ->columns(2)
                ->schema([

                    Forms\Components\TextInput::make('barcode_code')
                        ->label('Barcode Code')
                        ->required()
                        ->unique(
                            table: McuRegistration::class,
                            column: 'barcode_code',
                            ignoreRecord: true
                        )
                        ->maxLength(100)
                        ->placeholder('Auto-generated on patient selection')
                        ->hint('Scan physical barcode or use auto-generated code.')
                        ->suffixAction(
                            Actions\Action::make('regenerate_barcode')
                                ->label('Regenerate')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function (Set $set) {
                                    $set('barcode_code', McuRegistration::generateBarcode());
                                })
                        )
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->options([
                            'REGISTERED'  => '📋 Registered',
                            'IN_PROGRESS' => '🔬 In Progress',
                            'COMPLETED'   => '✅ Completed',
                        ])
                        ->default('REGISTERED')
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('registered_by')
                        ->default(fn () => auth()->id()),
                ]),

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 4: Dynamic Stations (Stasiun Pemeriksaan)
            // ║ Requirement #2: Many-to-Many via CheckboxList
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Stations (Stasiun Pemeriksaan)')
                ->description('Assign the examination stations this patient must visit.')
                ->icon('heroicon-o-map-pin')
                ->schema([

                    /**
                     * Requirement #2: CheckboxList for M2M station assignment.
                     *
                     * Uses `->relationship()` which auto-handles the pivot
                     * sync on save. Only active stations are shown, ordered
                     * by their sequence_order (via Station's global scope).
                     *
                     * The `->descriptions()` method shows the station
                     * description text below each checkbox for context.
                     */
                    Forms\Components\CheckboxList::make('stations')
                        ->label('Select Stations')
                        ->relationship(
                            name: 'stations',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->where('is_active', true)
                        )
                        ->descriptions(
                            Station::query()
                                ->where('is_active', true)
                                ->pluck('description', 'id')
                                ->toArray()
                        )
                        ->columns(3)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->required()
                        ->helperText('Check all stations the patient needs to visit. Use "Select All" for full MCU packages.'),
                ]),

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 5: Customizable Registration Data (JSON)
            // ║ Requirement #3: custom_fields JSON via KeyValue
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Custom Registration Fields')
                ->description('Additional demographic data specific to the selected company. Add key-value pairs as needed.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->collapsible()
                ->collapsed(fn (Get $get) => empty($get('custom_fields')))
                ->schema([

                    /**
                     * Requirement #3: Dynamic JSON fields via KeyValue.
                     *
                     * This provides a flexible key-value editor where admins
                     * can add any company-specific fields like:
                     *   - blood_type → O+
                     *   - marital_status → Married
                     *   - shift_type → Night Shift
                     *   - smoker → Yes
                     *
                     * The data is stored as JSON in the `custom_fields` column
                     * and cast to `array` in the Eloquent model.
                     *
                     * If the Organization has a `registration_field_template`
                     * defined, those keys are pre-populated as hints.
                     */
                    Forms\Components\KeyValue::make('custom_fields')
                        ->label('Custom Fields')
                        ->keyLabel('Field Name')
                        ->valueLabel('Value')
                        ->keyPlaceholder('e.g., blood_type')
                        ->valuePlaceholder('e.g., O+')
                        ->addActionLabel('Add Custom Field')
                        ->reorderable()
                        ->columnSpanFull(),

                    /**
                     * Dynamic hint: if the selected organization has a
                     * registration_field_template, show it as guidance.
                     */
                    Forms\Components\Placeholder::make('template_hint')
                        ->label('')
                        ->content(function (Get $get): HtmlString {
                            $orgId = $get('organization_id');
                            if (!$orgId) {
                                return new HtmlString('');
                            }

                            $org = Organization::find($orgId, ['id', 'registration_field_template']);
                            $template = $org?->registration_field_template;

                            if (empty($template)) {
                                return new HtmlString(
                                    '<p class="text-xs text-gray-400">No template defined for this organization. You can add any custom fields above.</p>'
                                );
                            }

                            $fields = collect($template)
                                ->map(fn ($f) => "<li><code>{$f['key']}</code> — {$f['label']}</li>")
                                ->implode('');

                            return new HtmlString(
                                "<div class='text-xs text-gray-500 dark:text-gray-400'>
                                    <p class='font-medium mb-1'>Suggested fields for this organization:</p>
                                    <ul class='list-disc pl-4 space-y-0.5'>{$fields}</ul>
                                </div>"
                            );
                        })
                        ->columnSpanFull(),
                ]),

            // ╔══════════════════════════════════════════════════════════════
            // ║ SECTION 6: Webcam Capture for Employee Photo
            // ║ Requirement #5: Custom ViewField with HTML5 video/canvas
            // ╚══════════════════════════════════════════════════════════════
            Section::make('Employee Photo')
                ->description('Capture a live photo of the employee using the device webcam.')
                ->icon('heroicon-o-camera')
                ->collapsible()
                ->schema([

                    /**
                     * Requirement #5: Webcam capture via ViewField.
                     *
                     * The Blade view (resources/views/forms/components/webcam-capture.blade.php)
                     * handles the HTML5 video/canvas webcam flow and writes
                     * the base64 JPEG string into the Livewire state via
                     * `$wire.set(statePath, base64String)`.
                     *
                     * The base64 string is decoded and saved as a file in
                     * `mutateFormDataBeforeCreate` / `mutateFormDataBeforeSave`
                     * in the Page classes (CreateMcuRegistration / EditMcuRegistration).
                     */
                    Forms\Components\ViewField::make('employee_photo')
                        ->view('forms.components.webcam-capture'),
                ]),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->with([
                    'organization:id,name',
                    'patient:id,name,employee_id,department,job_risk_level',
                    'stations:id,name',
                ])
                ->latest('created_at')
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

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->description(fn (McuRegistration $r) =>
                        "[{$r->patient?->employee_id}] {$r->patient?->department}"
                    )
                    ->searchable(['patients.name', 'patients.employee_id'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.job_risk_level')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'LOW'     => 'success',
                        'MEDIUM'  => 'warning',
                        'HIGH'    => 'danger',
                        'EXTREME' => 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('stations.name')
                    ->label('Stations')
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'REGISTERED'  => 'info',
                        'IN_PROGRESS' => 'warning',
                        'COMPLETED'   => 'success',
                        default       => 'gray',
                    }),

                Tables\Columns\ImageColumn::make('employee_photo')
                    ->label('Photo')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=N+A&background=e2e8f0&color=64748b')
                    ->toggleable(),

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

                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make(),
            ])

            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),

                /**
                 * Quick status advancement — single-click UX for
                 * receptionists/nurses to advance status without
                 * opening the full edit form.
                 */
                Actions\Action::make('advance_status')
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
                            $updates = ['status' => $nextStatus];

                            if ($nextStatus === 'COMPLETED') {
                                $updates['completed_at'] = now();
                            }

                            $record->update($updates);

                            Notification::make()
                                ->title("Status updated to {$nextStatus}")
                                ->success()
                                ->send();
                        }
                    }),
            ])

            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    // ── Relations & Pages ─────────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMcuRegistrations::route('/'),
            'create' => Pages\CreateMcuRegistration::route('/create'),
            'view'   => Pages\ViewMcuRegistration::route('/{record}'),
            'edit'   => Pages\EditMcuRegistration::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
