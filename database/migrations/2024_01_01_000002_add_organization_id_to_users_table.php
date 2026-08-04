<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_organization_id_to_users_table
 *
 * Extends Laravel's default `users` table with an optional FK to `organizations`.
 * Null means a super-admin or platform-level user not bound to any org.
 * We also add an `is_active` flag to prevent login without hard-deleting records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Place the column right after `email`
            $table->foreignId('organization_id')
                  ->nullable()
                  ->after('email')
                  ->constrained('organizations')
                  ->nullOnDelete()   // keep user record if org is soft-deleted/hard-deleted
                  ->comment('Owning organization; null for platform admins');

            $table->boolean('is_active')
                  ->default(true)
                  ->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'is_active']);
        });
    }
};
