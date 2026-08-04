<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * DatabaseSeeder
 *
 * Seeds the foundational data required to start using the Snaptyx MCU system:
 *
 * 1. RBAC Roles (using Spatie Permission)
 * 2. Snaptyx Internal Organization
 * 3. Super Admin user
 * 4. Sample Corporate Client Organization + Doctor user
 *
 * Run with: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Create Roles ───────────────────────────────────────────────
        $this->command->info('Creating roles...');

        $roles = [
            'super_admin'  => 'Full system access — Snaptyx platform team only',
            'org_admin'    => 'Manage own organization: users, projects, packages',
            'doctor'       => 'Review completed registrations and approve medical status',
            'nurse'        => 'Enter examination results at clinical stations',
            'lab_tech'     => 'Enter laboratory examination results',
            'receptionist' => 'Register patients and manage barcode check-in',
        ];

        $createdRoles = [];
        foreach ($roles as $name => $description) {
            $createdRoles[$name] = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        $this->command->info('✓ ' . count($roles) . ' roles created.');

        // ── 2. Snaptyx Internal Organization ──────────────────────────────
        $this->command->info('Creating Snaptyx internal organization...');

        $snaptyx = Organization::firstOrCreate(
            ['name' => 'Snaptyx Group'],
            [
                'org_type'       => 'INTERNAL',
                'pic_name'       => 'System Administrator',
                'contact_number' => '+62-800-SNAPMCU',
                'address'        => 'Jakarta, Indonesia',
            ]
        );

        // ── 3. Super Admin User ───────────────────────────────────────────
        $this->command->info('Creating super admin user...');

        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@snaptyx.com'],
            [
                'name'            => 'Snaptyx Admin',
                'password'        => Hash::make('SnaptyxMCU@2025!'),  // Change immediately!
                'organization_id' => $snaptyx->id,
                'is_active'       => true,
            ]
        );
        $superAdmin->assignRole('super_admin');

        $this->command->info("✓ Super admin created: admin@snaptyx.com");
        $this->command->warn('  ⚠️  Change the default password immediately after first login!');

        // ── 4. Sample Corporate Client Organization ────────────────────────
        $this->command->info('Creating sample corporate client...');

        $corporateClient = Organization::firstOrCreate(
            ['name' => 'PT Maju Bersama Tbk'],
            [
                'org_type'       => 'CORPORATE',
                'pic_name'       => 'Budi Santoso',
                'contact_number' => '+62-21-1234-5678',
                'address'        => 'Jl. Sudirman No. 123, Jakarta Pusat',
            ]
        );

        // ── 5. Sample Doctor User ─────────────────────────────────────────
        $doctor = User::firstOrCreate(
            ['email' => 'dr.budi@snaptyx.com'],
            [
                'name'            => 'dr. Budi Hartono, SpOK',
                'password'        => Hash::make('Doctor@2025!'),
                'organization_id' => $snaptyx->id,
                'is_active'       => true,
            ]
        );
        $doctor->assignRole('doctor');

        // ── 6. Sample Receptionist User ───────────────────────────────────
        $receptionist = User::firstOrCreate(
            ['email' => 'reception@snaptyx.com'],
            [
                'name'            => 'Siti Rahayu',
                'password'        => Hash::make('Reception@2025!'),
                'organization_id' => $snaptyx->id,
                'is_active'       => true,
            ]
        );
        $receptionist->assignRole('receptionist');

        $this->command->info('✓ Sample users created:');
        $this->command->line('  Doctor:       dr.budi@snaptyx.com     (Doctor@2025!)');
        $this->command->line('  Receptionist: reception@snaptyx.com   (Reception@2025!)');

        $this->command->newLine();
        $this->command->info('🚀 Database seeded successfully! System is ready to use.');
    }
}
