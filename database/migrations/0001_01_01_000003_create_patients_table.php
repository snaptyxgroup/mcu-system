<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_patients_table
 *
 * Patients are the employees of corporate clients undergoing MCU.
 * Key design decisions:
 *
 * 1. `nik` (National ID) + `employee_id` are unique *within* an organization,
 *    not globally — different companies can have employee IDs that collide.
 *
 * 2. `custom_attributes` (JSON) is a schema-less extension point for
 *    org-specific fields (e.g., shift_type, work_location, last_mcu_date).
 *    Cast to array in the Model; never queried with WHERE so no index needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete()
                  ->comment('The corporate employer of this patient');

            $table->string('nik', 20)->nullable()->comment('Nomor Induk Kependudukan (National ID)');
            $table->string('employee_id', 50)->nullable();
            $table->string('name', 255)->index();
            $table->date('dob')->nullable()->comment('Date of Birth');
            $table->enum('gender', ['MALE', 'FEMALE'])->nullable();
            $table->string('department', 150)->nullable()->index();
            $table->string('job_title', 150)->nullable();

            $table->enum('job_risk_level', ['LOW', 'MEDIUM', 'HIGH', 'EXTREME'])
                  ->default('LOW')
                  ->index();

            $table->json('custom_attributes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'nik'], 'uq_patient_org_nik');
            $table->unique(['organization_id', 'employee_id'], 'uq_patient_org_emp');
            $table->index(['organization_id', 'name'], 'idx_patients_org_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
