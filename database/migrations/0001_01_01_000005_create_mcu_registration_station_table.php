<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mcu_registration_station_table
 *
 * Many-to-many pivot table between mcu_registrations and stations.
 * Tracks which stations a patient must visit and their progress
 * through each checkpoint.
 *
 * Pivot data:
 *  - `checked_in_at`  — timestamp when patient arrives at station
 *  - `checked_out_at` — timestamp when station examination completes
 *  - `status`         — PENDING → IN_PROGRESS → DONE
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcu_registration_station', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mcu_registration_id')
                  ->constrained('mcu_registrations')
                  ->cascadeOnDelete()
                  ->comment('The registration this station assignment belongs to');

            $table->foreignId('station_id')
                  ->constrained('stations')
                  ->cascadeOnDelete()
                  ->comment('The station the patient must visit');

            $table->timestamp('checked_in_at')
                  ->nullable()
                  ->comment('When the patient arrives at this station');

            $table->timestamp('checked_out_at')
                  ->nullable()
                  ->comment('When the station examination completes');

            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'DONE'])
                  ->default('PENDING')
                  ->comment('Progress status at this station');

            $table->timestamps();

            // A registration can only be assigned to a station once
            $table->unique(
                ['mcu_registration_id', 'station_id'],
                'uq_registration_station'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcu_registration_station');
    }
};
