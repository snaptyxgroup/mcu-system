<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mcu_results_table
 *
 * Stores individual examination results per item per registration.
 * Design notes:
 *
 * 1. `result_value` is stored as TEXT to accommodate both numeric values
 *    (e.g., "14.2") and text values (e.g., "Reactive", "No active lesion").
 *    The application layer handles parsing.
 *
 * 2. `is_abnormal` is pre-computed at input time by comparing the numeric
 *    value against `examination_items.normal_min/max`. This avoids expensive
 *    joins when building the AI prompt — simply query WHERE is_abnormal = 1.
 *
 * 3. A unique constraint on (registration_id, item_id) ensures each test
 *    can only have one result per registration. Updates are done via
 *    upsert(), not repeated inserts.
 *
 * 4. `input_by` is nullable to handle bulk imports but should always be
 *    populated for manual entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcu_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                  ->constrained('mcu_registrations')
                  ->cascadeOnDelete()
                  ->comment('The MCU registration this result belongs to');

            $table->foreignId('item_id')
                  ->constrained('examination_items')
                  ->restrictOnDelete()
                  ->comment('The examination item (test) this result is for');

            $table->text('result_value')
                  ->nullable()
                  ->comment('Stored as string; parsed to numeric by application if needed');

            $table->boolean('is_abnormal')
                  ->default(false)
                  ->index()
                  ->comment('Pre-computed flag; true when numeric result is outside normal range');

            $table->text('remarks')->nullable()
                  ->comment('Lab tech or nurse remarks for this specific result');

            $table->foreignId('input_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User who entered or last updated this result');

            $table->timestamps();

            // One result per item per registration (upsert pattern)
            $table->unique(['registration_id', 'item_id'], 'uq_result_registration_item');

            // Fast retrieval of all abnormal results for AI prompt building
            $table->index(['registration_id', 'is_abnormal'], 'idx_results_registration_abnormal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcu_results');
    }
};
