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
    /** Supported database drivers and their display labels. */
    const DB_DRIVERS = [
        'pgsql'   => 'PostgreSQL',
        'mysql'   => 'MySQL',
        'mariadb' => 'MariaDB',
        'sqlite'  => 'SQLite',
    ];

    /**
     * Check if application is already installed
     */
    public function isInstalled()
    {
        return file_exists(storage_path('installed'));
    }

    /**
     * Check if .env exists and has APP_KEY
     */
    protected function needsEnvironmentSetup()
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return true;
        }

        $envContent = file_get_contents($envPath);
        return !preg_match('/APP_KEY=base64:.+/', $envContent);
    }

    /**
     * Show installation wizard
     */
    public function index()
    {
        if ($this->isInstalled()) {
            return redirect('/')->with('info', 'Application is already installed.');
        }

        if ($this->needsEnvironmentSetup()) {
            return redirect()->route('install.environment');
        }

        if (session('install_step_1_completed')) {
            return redirect()->route('install.admin');
        }

        if (session('install_database_configured')) {
            return redirect()->route('install.database');
        }

        return view('install.welcome');
    }

    /**
     * Step 0: Environment setup (.env creation)
     */
    public function showEnvironmentForm()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('install.environment');
    }

    /**
     * Step 0: Create .env file and generate APP_KEY
     */
    public function setupEnvironment(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url'  => 'required|url',
        ]);

        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $this->updateEnvFile([
            'APP_NAME'  => $validated['app_name'],
            'APP_URL'   => $validated['app_url'],
            'APP_ENV'   => 'production',
            'APP_DEBUG' => 'false',
        ]);

        Artisan::call('key:generate', ['--force' => true]);
        Artisan::call('config:clear');

        return redirect()->route('install.database');
    }

    /**
     * Step 1: Database configuration form
     */
    public function showDatabaseForm()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $dbConfig = session('install_database_config', [
            'db_driver'   => 'pgsql',
            'db_host'     => 'localhost',
            'db_port'     => '5432',
            'db_database' => 'openmmes',
            'db_username' => 'openmmes_user',
            'db_password' => '',
        ]);

        return view('install.database', [
            'dbConfig'  => $dbConfig,
            'dbDrivers' => self::DB_DRIVERS,
        ]);
    }

    /**
     * Step 1: Test connection and run migrations
     */
    public function setupDatabase(Request $request)
    {
        $driver = $request->input('db_driver', 'pgsql');

        if (!array_key_exists($driver, self::DB_DRIVERS)) {
            return back()->withErrors(['db_driver' => 'Invalid database driver.'])->withInput();
        }

        // Validate fields — host/port/user/pass only required for non-SQLite
        $rules = ['db_driver' => 'required|in:pgsql,mysql,mariadb,sqlite'];

        if ($driver !== 'sqlite') {
            $rules['db_host']     = 'required|string';
            $rules['db_port']     = 'required|integer';
            $rules['db_database'] = 'required|string';
            $rules['db_username'] = 'required|string';
            $rules['db_password'] = 'nullable|string';
        } else {
            $rules['db_database'] = 'required|string';
        }

        $validated = $request->validate($rules);
        $validated['db_password'] = $validated['db_password'] ?? '';

        // Build runtime connection config
        if ($driver === 'sqlite') {
            $dbPath = $validated['db_database'];
            // Resolve relative paths to storage/
            if (!str_starts_with($dbPath, '/')) {
                $dbPath = storage_path($dbPath);
            }
            config([
                "database.connections.{$driver}.database" => $dbPath,
            ]);
            config(['database.default' => $driver]);
        } else {
            config([
                "database.connections.{$driver}.host"     => $validated['db_host'],
                "database.connections.{$driver}.port"     => $validated['db_port'],
                "database.connections.{$driver}.database" => $validated['db_database'],
                "database.connections.{$driver}.username" => $validated['db_username'],
                "database.connections.{$driver}.password" => $validated['db_password'],
                "database.connections.{$driver}.options"  => [
                    \PDO::ATTR_TIMEOUT  => 30,
                    \PDO::ATTR_ERRMODE  => \PDO::ERRMODE_EXCEPTION,
                ],
            ]);
            config(['database.default' => $driver]);
        }

        // Test connection
        try {
            DB::purge($driver);
            DB::connection($driver)->getPdo();
        } catch (\PDOException $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
                return back()->withErrors(['db_connection' => 'Connection timed out (30 s). Check that the database server is reachable.'])->withInput();
            } elseif (str_contains($msg, 'password authentication failed') || $e->getCode() === '28P01') {
                return back()->withErrors(['db_connection' => 'Invalid database username or password.'])->withInput();
            } elseif (str_contains($msg, 'does not exist') || str_contains($msg, 'Unknown database')) {
                return back()->withErrors(['db_connection' => 'Database "' . $validated['db_database'] . '" does not exist. Create it first.'])->withInput();
            } elseif (str_contains($msg, 'could not translate host name') || str_contains($msg, 'Connection refused')) {
                return back()->withErrors(['db_connection' => 'Could not connect to the database server. Check the host and port.'])->withInput();
            } else {
                return back()->withErrors(['db_connection' => 'Database connection error: ' . $msg])->withInput();
            }
        } catch (\Exception $e) {
            return back()->withErrors(['db_connection' => 'Unexpected error: ' . $e->getMessage()])->withInput();
        }

        // Run migrations
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } catch (\Exception $e) {
            return back()->withErrors(['migration' => 'Migration failed: ' . $e->getMessage()]);
        }

        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'IssueTypesSeeder', '--force' => true]);

        session([
            'install_step_1_completed'    => true,
            'install_database_configured' => true,
            'install_database_config'     => array_merge($validated, ['db_driver' => $driver]),
        ]);

        return redirect()->route('install.admin');
    }

    /**
     * Step 2: Admin account creation form
     */
    public function showAdminForm()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        if (!session('install_step_1_completed')) {
            return redirect()->route('install.database')
                ->with('error', 'Please complete database configuration first.');
        }

        $adminConfig = session('install_admin_config', [
            'site_name'      => 'OpenMES',
            'site_url'       => 'http://localhost',
            'admin_username' => '',
            'admin_email'    => '',
        ]);

        return view('install.admin', ['adminConfig' => $adminConfig]);
    }

    /**
     * Step 3: Create admin account and finish installation
     */
    public function createAdmin(Request $request)
    {
        if (!session('install_step_1_completed')) {
            return redirect()->route('install.database')
                ->with('error', 'Please complete database configuration first.');
        }

        $validated = $request->validate([
            'admin_username' => 'required|string|max:255|unique:users,username',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'site_name'      => 'required|string|max:255',
            'site_url'       => 'required|url',
        ]);

        session([
            'install_admin_config' => [
                'site_name'      => $validated['site_name'],
                'site_url'       => $validated['site_url'],
                'admin_username' => $validated['admin_username'],
                'admin_email'    => $validated['admin_email'],
            ]
        ]);

        $dbConfig = session('install_database_config');

        if (!$dbConfig) {
            return redirect()->route('install.database')
                ->with('error', 'Database configuration not found. Please configure database first.');
        }

        $driver = $dbConfig['db_driver'];

        // Re-apply runtime DB config so Eloquent uses the correct connection
        if ($driver === 'sqlite') {
            $dbPath = $dbConfig['db_database'];
            if (!str_starts_with($dbPath, '/')) {
                $dbPath = storage_path($dbPath);
            }
            config(["database.connections.{$driver}.database" => $dbPath]);
        } else {
            config([
                "database.connections.{$driver}.host"     => $dbConfig['db_host'],
                "database.connections.{$driver}.port"     => $dbConfig['db_port'],
                "database.connections.{$driver}.database" => $dbConfig['db_database'],
                "database.connections.{$driver}.username" => $dbConfig['db_username'],
                "database.connections.{$driver}.password" => $dbConfig['db_password'],
            ]);
        }

        config([
            'database.default' => $driver,
            'app.name'         => $validated['site_name'],
            'app.url'          => $validated['site_url'],
        ]);

        DB::purge($driver);
        DB::reconnect($driver);

        $admin = User::create([
            'name'                  => 'Administrator',
            'username'              => $validated['admin_username'],
            'email'                 => $validated['admin_email'],
            'password'              => Hash::make($validated['admin_password']),
            'force_password_change' => false,
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

        session()->forget([
            'install_step_1_completed',
            'install_database_configured',
            'install_database_config',
            'install_admin_config',
        ]);

        // Write final .env after response is sent
        defer(function () use ($dbConfig, $validated, $driver) {
            $envData = [
                'DB_CONNECTION' => $driver,
                'APP_NAME'      => $validated['site_name'],
                'APP_URL'       => $validated['site_url'],
            ];

            if ($driver === 'sqlite') {
                $envData['DB_DATABASE'] = $dbConfig['db_database'];
            } else {
                $envData['DB_HOST']     = $dbConfig['db_host'];
                $envData['DB_PORT']     = $dbConfig['db_port'];
                $envData['DB_DATABASE'] = $dbConfig['db_database'];
                $envData['DB_USERNAME'] = $dbConfig['db_username'];
                $envData['DB_PASSWORD'] = $dbConfig['db_password'];
            }

            $this->updateEnvFile($envData);
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
            $value = str_replace('"', '\"', $value);

            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}=\"{$value}\"",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}=\"{$value}\"";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
