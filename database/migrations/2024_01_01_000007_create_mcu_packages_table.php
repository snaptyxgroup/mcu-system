<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mcu_packages_table + package_items pivot
 *
 * An MCU package bundles a curated set of examination items sold to a
 * specific corporate organization (e.g. "Basic MCU - PT ABC" or
 * "Executive MCU - Hospital Mitra").
 *
 * The `package_items` pivot is intentionally kept minimal (no extra columns)
 * so it can use Eloquent's standard belongsToMany without a custom pivot model.
 * If per-item pricing is required in the future, add a `price` column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── MCU Packages ─────────────────────────────────────────────────────
        Schema::create('mcu_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete()
                  ->comment('The corporate org this package is designed for');

            $table->string('name', 255);

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'idx_packages_org_active');
        });

        // ── Package Items Pivot ───────────────────────────────────────────────
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('package_id')
                  ->constrained('mcu_packages')
                  ->cascadeOnDelete();

            $table->foreignId('item_id')
                  ->constrained('examination_items')
                  ->cascadeOnDelete();

            $table->unique(['package_id', 'item_id'], 'uq_package_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('mcu_packages');
    }
};
