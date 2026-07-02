@echo off
setlocal
docker exec openmes-backend php artisan migrate:fresh --seed --force || exit /b %errorlevel%
docker exec openmes-backend php artisan cache:clear || exit /b %errorlevel%
docker exec openmes-backend php artisan tinker --execute="\App\Models\User::create(['name' => 'Administrator', 'username' => config('openmmes.admin_username') ?: 'admin', 'email' => config('openmmes.admin_email') ?: 'admin@example.com', 'password' => \Illuminate\Support\Facades\Hash::make(config('openmmes.admin_password') ?: 'MFRz9GZBkM9UEYTfsaHPLG1P'), 'force_password_change' => false, 'email_verified_at' => now()])->assignRole('Admin');" || exit /b %errorlevel%
docker exec openmes-backend php artisan tinker --execute="\DB::table('system_settings')->where('key', 'lot_tracking_enabled')->update(['value' => 'true']);" || exit /b %errorlevel%
set "E2E_BASE_URL=http://localhost:8080"
npx playwright test tests/e2e/car-production-buildout-en.spec.ts --project=chromium --headed || exit /b %errorlevel%
