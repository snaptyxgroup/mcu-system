<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_examination_items_table
 *
 * Examination items are the atomic test/procedure units performed at a station.
 * Examples: Hemoglobin (numeric), Blood Type (text), Chest X-Ray Finding (text).
 *
 * `vendor_org_id` → the clinic/lab that performs this item (can be null for
 * in-house Snaptyx procedures).
 *
 * `normal_min` / `normal_max` → for numeric items, auto-flags `is_abnormal`
 * when result is outside this range.
 *
 * `normal_text` → for text items, a descriptive reference (e.g., "Negative").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('station_id')
                  ->constrained('stations')
                  ->restrictOnDelete()
                  ->comment('Which station performs this item');

            $table->foreignId('vendor_org_id')
                  ->nullable()
                  ->constrained('organizations')
                  ->nullOnDelete()
                  ->comment('External clinic/lab vendor; null = Snaptyx in-house');

            $table->string('item_code', 50)
                  ->unique()
                  ->comment('Unique catalog code, e.g. LAB-HGB, RAD-CXR');

            $table->string('name', 255);

            // Normal-range fields — only meaningful when input_type = 'numeric'
            $table->decimal('normal_min', 10, 4)->nullable();
            $table->decimal('normal_max', 10, 4)->nullable();

            // Reference text for text-type results, e.g. "Normal", "Negative"
            $table->string('normal_text', 255)->nullable();

            $table->enum('input_type', ['numeric', 'text'])
                  ->default('numeric')
                  ->index();

            $table->string('unit', 50)->nullable()
                  ->comment('Measurement unit, e.g. g/dL, mg/dL, %');

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            // Common query: all items belonging to a station
            $table->index(['station_id', 'is_active'], 'idx_items_station_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_items');
    }
};
