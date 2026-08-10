<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use App\Imports\McuRegistrationImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ListMcuRegistrations Page
 *
 * Provides:
 *  - Tabbed filtering by status (All / Registered / In Progress / Completed)
 *  - Requirement #4: Bulk Excel Import via HeaderAction
 */
class ListMcuRegistrations extends ListRecords
{
    protected static string $resource = McuRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Register Patient')
                ->icon('heroicon-o-plus'),

            /**
             * Requirement #4: Bulk Excel Import
             *
             * Opens a modal with a file upload and optional organization
             * selector. Uses Laravel Excel (maatwebsite/excel) to process
             * the uploaded file with McuRegistrationImport.
             *
             * The import class handles:
             *  - Patient find-or-create within the organization
             *  - Registration creation with auto-generated barcode
             *  - Station sync from comma-separated station_ids column
             *  - Extra columns → custom_fields JSON
             */
            Actions\Action::make('import_registrations')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Bulk Import MCU Registrations')
                ->modalDescription('Upload an Excel or CSV file with patient registration data. Expected columns: patient_name, patient_nik, employee_id, gender, department, job_title, station_ids (comma-separated).')
                ->modalSubmitActionLabel('Start Import')
                ->form([
                    Forms\Components\Select::make('organization_id')
                        ->label('Default Organization')
                        ->required()
                        ->searchable()
                        ->options(fn () => \App\Models\Organization::query()
                            ->where('org_type', 'CORPORATE')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                        )
                        ->helperText('Organization to assign if not specified per row in the Excel file.'),

                    Forms\Components\FileUpload::make('import_file')
                        ->label('Excel / CSV File')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->maxSize(10240) // 10MB max
                        ->disk('local')
                        ->directory('imports')
                        ->helperText('Max 10MB. Supported: .xlsx, .xls, .csv'),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/' . $data['import_file']);

                    $import = new McuRegistrationImport(
                        defaultOrganizationId: (int) $data['organization_id'],
                        registeredByUserId: auth()->id(),
                    );

                    try {
                        Excel::import($import, $filePath);

                        $successMsg = "Import completed: {$import->importedCount} registrations created.";

                        if ($import->skippedCount > 0) {
                            $successMsg .= " ({$import->skippedCount} rows skipped.)";
                        }

                        $failures = $import->failures();
                        if ($failures->isNotEmpty()) {
                            $errorRows = $failures->map(fn ($f) =>
                                "Row {$f->row()}: " . implode(', ', $f->errors())
                            )->implode("\n");

                            Notification::make()
                                ->title('Import completed with warnings')
                                ->body($successMsg . "\n\nValidation errors:\n" . $errorRows)
                                ->warning()
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Import Successful')
                                ->body($successMsg)
                                ->success()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    } finally {
                        // Clean up uploaded file
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }),
        ];
    }

    /**
     * Status tabs — quick dashboard-style filtering.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-list-bullet'),

            'registered' => Tab::make('Registered')
                ->icon('heroicon-o-clipboard-document')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'REGISTERED'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'REGISTERED')->count()),

            'in_progress' => Tab::make('In Progress')
                ->icon('heroicon-o-arrow-right-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'IN_PROGRESS'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'IN_PROGRESS')->count()),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'COMPLETED'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'COMPLETED')->count()),
        ];
    }
}
