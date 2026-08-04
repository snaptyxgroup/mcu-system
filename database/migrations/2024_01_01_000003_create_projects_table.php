<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_projects_table
 *
 * A `project` represents one MCU engagement between Snaptyx and a corporate
 * client (organization). It defines the time-window in which patient
 * registrations are valid and is the primary billing unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete()
                  ->comment('The corporate client that commissioned this project');

            $table->string('name', 255);

            $table->date('start_date');
            $table->date('end_date');

            // A project-level note or scope description
            $table->text('description')->nullable();

            $table->enum('status', ['DRAFT', 'ACTIVE', 'CLOSED'])
                  ->default('DRAFT')
                  ->index();

            $table->timestamps();
            $table->softDeletes();

            // Composite index for the common query: org projects within a date range
            $table->index(['organization_id', 'start_date', 'end_date'], 'idx_projects_org_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
