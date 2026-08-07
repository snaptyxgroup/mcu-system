<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_stations_table
 *
 * A `station` is a physical or logical checkpoint in the MCU flow.
 * Examples: Registration Desk (1), Blood Draw (2), Radiology (3),
 * Doctor Consultation (4), Pharmacy (5).
 * `sequence_order` drives the patient's journey progression logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);

            $table->unsignedSmallInteger('sequence_order')
                  ->default(0)
                  ->comment('Ascending order of patient flow through this station');

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            // Enforce unique ordering to prevent two stations having same position
            $table->unique('sequence_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
