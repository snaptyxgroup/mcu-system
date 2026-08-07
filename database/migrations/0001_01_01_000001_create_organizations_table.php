<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_organizations_table
 *
 * The `organizations` table is the root multi-tenant anchor.
 * Every corporate client, clinic partner, hospital, or internal department
 * is stored here. CORPORATE organizations own projects & patients;
 * CLINIC_LAB / HOSPITAL organizations own examination items (as vendors).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);

            /**
             * org_type drives business rules:
             *  - CORPORATE   → can own projects, packages, patients
             *  - CLINIC_LAB  → acts as vendor for examination items
             *  - HOSPITAL    → same as CLINIC_LAB but hospital-grade
             *  - INTERNAL    → Snaptyx own staff / admin org
             */
            $table->enum('org_type', ['CORPORATE', 'CLINIC_LAB', 'HOSPITAL', 'INTERNAL'])
                  ->default('CORPORATE')
                  ->index();

            $table->string('pic_name', 150)->nullable()
                  ->comment('Person-in-Charge name for this organization');

            $table->string('contact_number', 30)->nullable();

            $table->text('address')->nullable();

            /**
             * JSON bag for organization-specific registration field templates.
             * When set, the MCU Registration form will dynamically render
             * these as additional form fields for patients of this org.
             * Format: [{"key": "blood_type", "label": "Blood Type", "type": "text"}, ...]
             */
            $table->json('registration_field_template')->nullable()
                  ->comment('Template for custom registration fields specific to this org');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
