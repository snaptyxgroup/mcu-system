<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_user_stations_table
 *
 * Pivot table that assigns medical personnel (users) to stations on a
 * given date. This supports the daily scheduling of nurses, lab techs,
 * etc. to their respective workstations.
 *
 * A unique composite key prevents double-booking a user at the same
 * station on the same day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stations', function (Blueprint $table) {
            $table->id();  // explicit PK needed because we store `assigned_date`

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('station_id')
                  ->constrained('stations')
                  ->cascadeOnDelete();

            $table->date('assigned_date')
                  ->comment('The calendar date this assignment is effective for');

            $table->timestamps();

            // A user can only be assigned to one station per day per station
            $table->unique(['user_id', 'station_id', 'assigned_date'], 'uq_user_station_date');

            // Index for querying "who is working at station X today?"
            $table->index(['station_id', 'assigned_date'], 'idx_station_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stations');
    }
};
