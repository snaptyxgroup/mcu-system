<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mcu_registrations_table
 *
 * The central transaction record. A registration links one patient to one
 * organization and generates a unique barcode for physical check-in
 * tracking at each station.
 *
 * New fields vs. original design:
 *  - `organization_id` — direct BelongsTo for cascading form selects
 *  - `custom_fields`   — JSON bag for company-specific demographic data
 *  - `employee_photo`  — file path to webcam-captured photo
 *
 * Status lifecycle:
 *   REGISTERED → IN_PROGRESS → COMPLETED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcu_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->restrictOnDelete()
                  ->comment('Direct link to the corporate client organization');

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->restrictOnDelete()
                  ->comment('The patient being registered for MCU');

            $table->string('barcode_code', 100)
                  ->unique()
                  ->comment('Physical barcode printed on the patient\'s wristband/sheet');

            $table->enum('status', ['REGISTERED', 'IN_PROGRESS', 'COMPLETED'])
                  ->default('REGISTERED')
                  ->index();

            // Company-specific demographic data (schema-less JSON)
            $table->json('custom_fields')
                  ->nullable()
                  ->comment('Company-specific demographic data rendered via KeyValue');

            // Webcam-captured employee photo path
            $table->string('employee_photo', 500)
                  ->nullable()
                  ->comment('File path to webcam-captured employee photo in storage');

            $table->timestamp('completed_at')
                  ->nullable()
                  ->comment('Stamped when status transitions to COMPLETED');

            $table->foreignId('registered_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Fast lookup by barcode at scanning stations
            $table->index('barcode_code');

            // Dashboard filter: registrations per org + status
            $table->index(['organization_id', 'status'], 'idx_registrations_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcu_registrations');
    }
};
