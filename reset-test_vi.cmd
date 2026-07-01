@echo off
docker exec openmes-backend php artisan migrate:fresh --seed --force
docker exec openmes-backend php artisan cache:clear
docker exec openmes-backend php artisan tinker --execute="\App\Models\User::create(['name' => 'Administrator', 'username' => config('openmmes.admin_username') ?: 'admin', 'email' => config('openmmes.admin_email') ?: 'admin@example.com', 'password' => \Illuminate\Support\Facades\Hash::make(config('openmmes.admin_password') ?: 'MFRz9GZBkM9UEYTfsaHPLG1P'), 'force_password_change' => false, 'email_verified_at' => now()])->assignRole('Admin');"
set E2E_BASE_URL=http://localhost:8080
npx playwright test tests/e2e/car-production-buildout-vi.spec.ts --project=chromium --headed
