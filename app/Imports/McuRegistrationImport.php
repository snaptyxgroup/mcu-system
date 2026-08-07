<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\McuRegistration;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

/**
 * McuRegistrationImport
 *
 * Bulk import MCU registrations from an Excel/CSV file.
 * Processes each row to:
 *  1. Find or create the Patient (by NIK or employee_id within the org)
 *  2. Create the McuRegistration with auto-generated barcode
 *  3. Sync assigned stations from a comma-separated station_ids column
 *
 * Expected Excel columns (heading row):
 * | organization_id | patient_name | patient_nik | employee_id | gender | department | job_title | station_ids |
 *
 * `station_ids` should be comma-separated (e.g., "1,3,5" for stations 1, 3, 5).
 *
 * Usage:
 *   Excel::import(new McuRegistrationImport($orgId, $userId), $filePath);
 */
class McuRegistrationImport implements ToCollection, WithHeadingRow, WithValidation, WithBatchInserts, SkipsOnFailure
{
    use SkipsFailures;

    /**
     * Track import statistics for the notification.
     */
    public int $importedCount = 0;
    public int $skippedCount = 0;

    public function __construct(
        protected ?int $defaultOrganizationId = null,
        protected ?int $registeredByUserId = null,
    ) {}

    /**
     * Process each row from the Excel file.
     * Uses ToCollection (instead of ToModel) to handle the M2M station sync
     * which requires the registration ID to exist first.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $orgId = $row['organization_id'] ?? $this->defaultOrganizationId;

            if (!$orgId || !Organization::find($orgId)) {
                $this->skippedCount++;
                continue;
            }

            // Find or create the patient within the organization
            $patient = Patient::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'nik'             => $row['patient_nik'] ?? null,
                ],
                [
                    'name'           => $row['patient_name'] ?? 'Unknown',
                    'employee_id'    => $row['employee_id'] ?? null,
                    'gender'         => $this->normalizeGender($row['gender'] ?? null),
                    'department'     => $row['department'] ?? null,
                    'job_title'      => $row['job_title'] ?? null,
                    'job_risk_level' => $row['job_risk_level'] ?? 'LOW',
                ]
            );

            // Create the MCU registration
            $registration = McuRegistration::create([
                'organization_id' => $orgId,
                'patient_id'      => $patient->id,
                'barcode_code'    => McuRegistration::generateBarcode(),
                'status'          => 'REGISTERED',
                'registered_by'   => $this->registeredByUserId,
                'custom_fields'   => $this->extractCustomFields($row),
            ]);

            // Sync stations if provided (comma-separated IDs)
            $stationIds = $this->parseStationIds($row['station_ids'] ?? '');
            if (!empty($stationIds)) {
                $registration->stations()->sync($stationIds);
            }

            $this->importedCount++;
        }
    }

    /**
     * Validation rules for each row.
     */
    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_nik'  => ['nullable', 'string', 'max:20'],
            'employee_id'  => ['nullable', 'string', 'max:50'],
            'gender'       => ['nullable', 'string'],
            'department'   => ['nullable', 'string', 'max:150'],
            'job_title'    => ['nullable', 'string', 'max:150'],
            'station_ids'  => ['nullable', 'string'],
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Normalize gender input (M/F/Male/Female/Laki/Perempuan → MALE/FEMALE).
     */
    protected function normalizeGender(?string $gender): ?string
    {
        if (!$gender) {
            return null;
        }

        $gender = strtoupper(trim($gender));

        return match (true) {
            in_array($gender, ['M', 'MALE', 'LAKI-LAKI', 'LAKI', 'L']) => 'MALE',
            in_array($gender, ['F', 'FEMALE', 'PEREMPUAN', 'P'])       => 'FEMALE',
            default                                                     => null,
        };
    }

    /**
     * Parse comma-separated station IDs into an array of integers.
     */
    protected function parseStationIds(string $stationIds): array
    {
        if (empty(trim($stationIds))) {
            return [];
        }

        return collect(explode(',', $stationIds))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Extract any extra columns as custom_fields JSON.
     * Columns not in the known list are treated as custom fields.
     */
    protected function extractCustomFields(Collection|array $row): ?array
    {
        $knownColumns = [
            'organization_id', 'patient_name', 'patient_nik',
            'employee_id', 'gender', 'department', 'job_title',
            'job_risk_level', 'station_ids',
        ];

        $customFields = [];

        foreach ($row as $key => $value) {
            if (!in_array($key, $knownColumns) && $value !== null && $value !== '') {
                $customFields[$key] = $value;
            }
        }

        return !empty($customFields) ? $customFields : null;
    }
}
