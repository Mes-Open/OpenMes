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

        // Check if user is in the middle of installation
        if (session('install_step_1_completed')) {
            return redirect()->route('install.admin');
        }

        if (session('install_database_configured')) {
            return redirect()->route('install.database');
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

        // Load saved database config from session if exists
        $dbConfig = session('install_database_config', [
            'db_host' => 'postgres',
            'db_port' => '5432',
            'db_database' => 'openmmes',
            'db_username' => 'openmmes_user',
            'db_password' => '',
        ]);

        return view('install.database', ['dbConfig' => $dbConfig]);
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

        // Temporarily set database config for this request
        config([
            'database.connections.pgsql.host' => $validated['db_host'],
            'database.connections.pgsql.port' => $validated['db_port'],
            'database.connections.pgsql.database' => $validated['db_database'],
            'database.connections.pgsql.username' => $validated['db_username'],
            'database.connections.pgsql.password' => $validated['db_password'],
        ]);

        // Test database connection with 30-second timeout
        try {
            // Set connection timeout to 30 seconds
            config([
                'database.connections.pgsql.options' => [
                    \PDO::ATTR_TIMEOUT => 30,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]
            ]);

            // Purge existing connection and reconnect with new configuration
            DB::purge('pgsql');

            // Test connection
            DB::connection()->getPdo();

        } catch (\PDOException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            // Provide specific error messages in Polish
            if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'timed out')) {
                return back()->withErrors(['db_connection' => 'Przekroczono limit czasu połączenia (30 sekund). Sprawdź, czy baza danych jest dostępna.'])->withInput();
            } elseif (str_contains($errorMessage, 'password authentication failed') || $errorCode === '28P01') {
                return back()->withErrors(['db_connection' => 'Nieprawidłowa nazwa użytkownika lub hasło do bazy danych.'])->withInput();
            } elseif (str_contains($errorMessage, 'database') && str_contains($errorMessage, 'does not exist')) {
                return back()->withErrors(['db_connection' => 'Baza danych "' . $validated['db_database'] . '" nie istnieje. Utwórz ją przed kontynuowaniem.'])->withInput();
            } elseif (str_contains($errorMessage, 'could not translate host name') || str_contains($errorMessage, 'Connection refused')) {
                return back()->withErrors(['db_connection' => 'Nie można połączyć się z serwerem bazy danych. Sprawdź adres hosta i port.'])->withInput();
            } else {
                return back()->withErrors(['db_connection' => 'Błąd połączenia z bazą danych: ' . $errorMessage])->withInput();
            }
        } catch (\Exception $e) {
            return back()->withErrors(['db_connection' => 'Nieoczekiwany błąd: ' . $e->getMessage()])->withInput();
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

        // Save database config in session (including password for .env update later)
        session([
            'install_step_1_completed' => true,
            'install_database_configured' => true,
            'install_database_config' => [
                'db_host' => $validated['db_host'],
                'db_port' => $validated['db_port'],
                'db_database' => $validated['db_database'],
                'db_username' => $validated['db_username'],
                'db_password' => $validated['db_password'], // Store for .env update
            ]
        ]);

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

        // Check if database step is completed
        if (!session('install_step_1_completed')) {
            return redirect()->route('install.database')
                ->with('error', 'Please complete database configuration first.');
        }

        // Load saved admin config from session if exists
        $adminConfig = session('install_admin_config', [
            'site_name' => 'OpenMES',
            'site_url' => 'http://localhost',
            'admin_username' => '',
            'admin_email' => '',
        ]);

        return view('install.admin', ['adminConfig' => $adminConfig]);
    }

    /**
     * Step 4: Create admin account and finish installation
     */
    public function createAdmin(Request $request)
    {
        // Check if database step is completed
        if (!session('install_step_1_completed')) {
            return redirect()->route('install.database')
                ->with('error', 'Please complete database configuration first.');
        }

        $validated = $request->validate([
            'admin_username' => 'required|string|max:255|unique:users,username',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'site_name' => 'required|string|max:255',
            'site_url' => 'required|url',
        ]);

        // Save admin config in session (for going back if needed)
        session([
            'install_admin_config' => [
                'site_name' => $validated['site_name'],
                'site_url' => $validated['site_url'],
                'admin_username' => $validated['admin_username'],
                'admin_email' => $validated['admin_email'],
            ]
        ]);

        // Get database config from session
        $dbConfig = session('install_database_config');

        if (!$dbConfig) {
            return redirect()->route('install.database')
                ->with('error', 'Database configuration not found. Please configure database first.');
        }

        // Temporarily set database and app config for this request
        config([
            'database.connections.pgsql.host' => $dbConfig['db_host'],
            'database.connections.pgsql.port' => $dbConfig['db_port'],
            'database.connections.pgsql.database' => $dbConfig['db_database'],
            'database.connections.pgsql.username' => $dbConfig['db_username'],
            'database.connections.pgsql.password' => $dbConfig['db_password'],
            'app.name' => $validated['site_name'],
            'app.url' => $validated['site_url'],
        ]);

        // Reconnect to database with new config
        DB::purge('pgsql');
        DB::reconnect('pgsql');

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

        // Clear installation session data
        session()->forget([
            'install_step_1_completed',
            'install_database_configured',
            'install_database_config',
            'install_admin_config',
        ]);

        // Update .env file AFTER sending response to prevent connection interruption
        defer(function () use ($dbConfig, $validated) {
            $this->updateEnvFile([
                'DB_HOST' => $dbConfig['db_host'],
                'DB_PORT' => $dbConfig['db_port'],
                'DB_DATABASE' => $dbConfig['db_database'],
                'DB_USERNAME' => $dbConfig['db_username'],
                'DB_PASSWORD' => $dbConfig['db_password'],
                'APP_NAME' => $validated['site_name'],
                'APP_URL' => $validated['site_url'],
            ]);
        });

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
