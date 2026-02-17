<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class InstallController extends Controller
{
    /**
     * Check if application is already installed
     */
    public function isInstalled()
    {
        return file_exists(storage_path('installed'));
    }

    /**
     * Show installation wizard
     */
    public function index()
    {
        if ($this->isInstalled()) {
            return redirect('/')->with('info', 'Application is already installed.');
        }

        return view('install.welcome');
    }

    /**
     * Step 1: Database configuration
     */
    public function showDatabaseForm()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('install.database');
    }

    /**
     * Step 2: Test database connection and run migrations
     */
    public function setupDatabase(Request $request)
    {
        $validated = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'required|string',
        ]);

        // Update .env file
        $this->updateEnvFile([
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => $validated['db_password'],
        ]);

        // Clear config cache
        Artisan::call('config:clear');

        // Test database connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            return back()->withErrors(['db_connection' => 'Could not connect to database: ' . $e->getMessage()]);
        }

        // Run migrations
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } catch (\Exception $e) {
            return back()->withErrors(['migration' => 'Migration failed: ' . $e->getMessage()]);
        }

        // Run role seeder
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'IssueTypesSeeder', '--force' => true]);

        return redirect()->route('install.admin');
    }

    /**
     * Step 3: Admin account creation form
     */
    public function showAdminForm()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('install.admin');
    }

    /**
     * Step 4: Create admin account and finish installation
     */
    public function createAdmin(Request $request)
    {
        $validated = $request->validate([
            'admin_username' => 'required|string|max:255|unique:users,username',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'site_name' => 'required|string|max:255',
            'site_url' => 'required|url',
        ]);

        // Update .env with site settings
        $this->updateEnvFile([
            'APP_NAME' => $validated['site_name'],
            'APP_URL' => $validated['site_url'],
        ]);

        Artisan::call('config:clear');

        // Create admin user
        $admin = User::create([
            'name' => 'Administrator',
            'username' => $validated['admin_username'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'force_password_change' => false,
        ]);

        // Assign admin role
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        // Mark as installed
        file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

        return redirect()->route('install.complete');
    }

    /**
     * Installation complete page
     */
    public function complete()
    {
        if (!$this->isInstalled()) {
            return redirect()->route('install.index');
        }

        return view('install.complete');
    }

    /**
     * Update .env file with new values
     */
    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            // Escape special characters in value
            $value = str_replace('"', '\"', $value);

            // Check if key exists
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                // Update existing key
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}=\"{$value}\"",
                    $envContent
                );
            } else {
                // Add new key
                $envContent .= "\n{$key}=\"{$value}\"";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
