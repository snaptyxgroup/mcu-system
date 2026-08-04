<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mcu_registrations_table
 *
 * The central transaction record. A registration links one patient to one
 * project + package pair and generates a unique barcode for physical
 * check-in tracking at each station.
 *
 * Status lifecycle:
 *   REGISTERED → patient is booked, no results yet
 *   IN_PROGRESS → at least one station has submitted results
 *   COMPLETED → all stations in the package have submitted results
 *               → triggers GenerateMedicalDraftJob
 *
 * `completed_at` is stamped by the observer / model event when status
 * transitions to COMPLETED (not just `updated_at` which can be noisy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcu_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->restrictOnDelete()
                  ->comment('The patient being registered for MCU');

            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->restrictOnDelete()
                  ->comment('The project (MCU engagement) this registration belongs to');

            $table->foreignId('package_id')
                  ->constrained('mcu_packages')
                  ->restrictOnDelete()
                  ->comment('The MCU package assigned to this patient');

            $table->string('barcode_code', 100)
                  ->unique()
                  ->comment('Physical barcode printed on the patient\'s wristband/sheet');

            $table->enum('status', ['REGISTERED', 'IN_PROGRESS', 'COMPLETED'])
                  ->default('REGISTERED')
                  ->index();

            $table->timestamp('completed_at')
                  ->nullable()
                  ->comment('Stamped when status transitions to COMPLETED');

            // The staff member who registered this patient
            $table->foreignId('registered_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Fast lookup by barcode at scanning stations
            $table->index('barcode_code');

            // Dashboard filter: registrations per project + status
            $table->index(['project_id', 'status'], 'idx_registrations_project_status');

            // Prevent a patient from being registered twice in the same project
            $table->unique(['patient_id', 'project_id'], 'uq_patient_project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcu_registrations');
    }
};
