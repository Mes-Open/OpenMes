<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUsername = env('DEFAULT_ADMIN_USERNAME', 'admin');
        $adminEmail = env('DEFAULT_ADMIN_EMAIL', 'admin@openmmes.local');
        $adminPassword = env('DEFAULT_ADMIN_PASSWORD', 'admin123');

        // Check if admin user already exists
        $admin = User::where('username', $adminUsername)->first();

        if (!$admin) {
            $admin = User::create([
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'force_password_change' => true, // Force password change on first login
            ]);

            // Assign Admin role
            $adminRole = Role::where('name', 'Admin')->where('guard_name', 'sanctum')->first();
            if ($adminRole) {
                $admin->assignRole($adminRole);
            }

            $this->command->info("Default admin user created:");
            $this->command->info("Username: {$adminUsername}");
            $this->command->info("Email: {$adminEmail}");
            $this->command->warn("Password: {$adminPassword}");
            $this->command->warn("*** CHANGE THIS PASSWORD ON FIRST LOGIN ***");
        } else {
            $this->command->info("Admin user already exists.");
        }
    }
}
