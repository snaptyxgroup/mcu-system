<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_organization_and_custom_fields_to_mcu_registrations_table
 *
 * Extends the `mcu_registrations` table with three new columns:
 *
 * 1. `organization_id` — Direct BelongsTo link to the corporate client.
 *    Previously, the organization was only accessible through
 *    Registration → Project → Organization. This FK enables:
 *    - Direct searchable Select in the Filament form
 *    - Faster org-scoped queries without joining projects
 *    - Cascading form filters (Org → Project → Patient → Package)
 *
 * 2. `custom_fields` — Schema-less JSON bag for company-specific
 *    demographic data that varies between corporate clients
 *    (e.g., shift_type, blood_type, marital_status, smoker).
 *    Cast to `array` in the Model; rendered via Filament KeyValue.
 *
 * 3. `employee_photo` — File path to the webcam-captured employee photo.
 *    Stored in `storage/app/public/employee-photos/`.
 *    Used for ID badge printing and visual verification at stations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcu_registrations', function (Blueprint $table) {
            $table->foreignId('organization_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('organizations')
                  ->nullOnDelete()
                  ->comment('Direct link to the corporate client organization');

            $table->json('custom_fields')
                  ->nullable()
                  ->after('status')
                  ->comment('Company-specific demographic data (schema-less JSON)');

            $table->string('employee_photo', 500)
                  ->nullable()
                  ->after('custom_fields')
                  ->comment('File path to webcam-captured employee photo');
        });
    }

    public function down(): void
    {
        Schema::table('mcu_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['custom_fields', 'employee_photo']);
        });
    }
};
