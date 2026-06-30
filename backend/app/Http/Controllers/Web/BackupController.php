<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetSystemRequest;
use App\Http\Requests\UploadBackupRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    private function getBackupsDir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Create a full backup (database + uploads).
     */
    public function createFullBackup(Request $request)
    {
        try {
            $filename = $this->runBackupInternal(false);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'entity_type' => null,
                'entity_id' => null,
                'action' => 'backup_create_full',
                'after_state' => ['filename' => $filename],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Full website backup created successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Error creating backup: ').$e->getMessage());
        }
    }

    /**
     * Create a data-only backup.
     */
    public function createDataBackup(Request $request)
    {
        try {
            $filename = $this->runBackupInternal(true);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'entity_type' => null,
                'entity_id' => null,
                'action' => 'backup_create_data',
                'after_state' => ['filename' => $filename],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Data backup created successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Error creating data backup: ').$e->getMessage());
        }
    }

    /**
     * Upload a backup file.
     */
    public function uploadBackup(UploadBackupRequest $request)
    {
        try {
            $file = $request->file('backup_file');
            $backupsDir = $this->getBackupsDir();

            $safeName = 'uploaded_'.date('Ymd_His').'_'.preg_replace('/[^a-zA-Z0-9_.-]/', '', $file->getClientOriginalName());
            $file->move($backupsDir, $safeName);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'entity_type' => null,
                'entity_id' => null,
                'action' => 'backup_upload',
                'after_state' => ['filename' => $safeName],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'filename' => $safeName,
                    'message' => __('Backup file uploaded successfully.'),
                ]);
            }

            return redirect()->route('settings.system')->with('success', __('Backup file uploaded successfully.'));
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Error uploading backup: ').$e->getMessage(),
                ], 500);
            }

            return redirect()->route('settings.system')->with('error', __('Error uploading backup: ').$e->getMessage());
        }
    }

    /**
     * Download a backup.
     */
    public function downloadBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir.'/'.$filename);

        if (! $filePath || ! str_starts_with($filePath, realpath($backupsDir))) {
            abort(400, __('Invalid path.'));
        }

        if (! file_exists($filePath)) {
            abort(404, __('Backup file not found.'));
        }

        return response()->download($filePath, $filename);
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir.'/'.$filename);

        if (! $filePath || ! str_starts_with($filePath, realpath($backupsDir))) {
            return redirect()->route('settings.system')->with('error', __('Invalid path.'));
        }

        if (! file_exists($filePath)) {
            return redirect()->route('settings.system')->with('error', __('Backup file not found.'));
        }

        unlink($filePath);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'entity_type' => null,
            'entity_id' => null,
            'action' => 'backup_delete',
            'before_state' => ['filename' => $filename],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('settings.system')->with('success', __('Backup deleted successfully.'));
    }

    /**
     * Restore from a backup file (Streaming parse).
     */
    public function restoreBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir.'/'.$filename);

        if (! $filePath || ! str_starts_with($filePath, realpath($backupsDir))) {
            return redirect()->route('settings.system')->with('error', __('Invalid path.'));
        }

        if (! file_exists($filePath)) {
            return redirect()->route('settings.system')->with('error', __('Backup file not found.'));
        }

        $tempDir = storage_path('app/temp_restore_'.uniqid());
        mkdir($tempDir, 0755, true);

        // Capture request details
        $userId = $request->user()?->id;
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Session will be invalidated after restore succeeds

        try {
            // Extract the Zip
            $zip = new \ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new \Exception(__('Could not open backup zip file.'));
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $jsonPath = $tempDir.'/db_backup.json';
            if (! file_exists($jsonPath)) {
                throw new \Exception(__('Backup file does not contain database data (missing db_backup.json).'));
            }

            // Get tables list in database (excluding migrations and sessions)
            $tables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))
                ->pluck('table_name')
                ->reject(fn ($name) => in_array($name, ['migrations', 'sessions']))
                ->values()
                ->toArray();

            // Perform DB Restore in a Transaction
            DB::transaction(function () use ($jsonPath, $tables) {
                // 1. Truncate all tables in a single statement to acquire locks instantly and avoid deadlocks/disk sync overhead
                if (! empty($tables)) {
                    $quotedTables = array_map(fn ($t) => "\"$t\"", $tables);
                    $tablesList = implode(', ', $quotedTables);
                    DB::statement("TRUNCATE TABLE $tablesList RESTART IDENTITY CASCADE;");
                }

                // 2. Disable triggers to skip foreign key validation during data restoration
                foreach ($tables as $table) {
                    DB::statement("ALTER TABLE \"$table\" DISABLE TRIGGER ALL;");
                }

                // 3. Streaming read and restore of table data
                $handle = fopen($jsonPath, 'r');
                if (! $handle) {
                    throw new \Exception(__('Could not open db_backup.json.'));
                }

                $currentTable = null;
                $buffer = [];

                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // Check for table start: "table_name": [
                    if (preg_match('/^"([^"]+)"\s*:\s*\[$/', $line, $matches)) {
                        if ($currentTable && ! empty($buffer)) {
                            $this->bulkInsertSafe($currentTable, $buffer);
                            $buffer = [];
                        }
                        $tableName = $matches[1];
                        $currentTable = in_array($tableName, $tables) ? $tableName : null;

                        continue;
                    }

                    // Check for table end: ] or ],
                    if ($line === ']' || $line === '],') {
                        if ($currentTable && ! empty($buffer)) {
                            $this->bulkInsertSafe($currentTable, $buffer);
                            $buffer = [];
                        }
                        $currentTable = null;

                        continue;
                    }

                    // If we are reading rows for a table
                    if ($currentTable) {
                        if (str_ends_with($line, ',')) {
                            $line = substr($line, 0, -1);
                        }

                        $row = json_decode($line, true);
                        if (is_array($row)) {
                            $buffer[] = $row;
                            if (count($buffer) >= 500) {
                                $this->bulkInsertSafe($currentTable, $buffer);
                                $buffer = [];
                            }
                        }
                    }
                }

                if ($currentTable && ! empty($buffer)) {
                    $this->bulkInsertSafe($currentTable, $buffer);
                }

                fclose($handle);

                // 4. Re-enable triggers
                foreach ($tables as $table) {
                    DB::statement("ALTER TABLE \"$table\" ENABLE TRIGGER ALL;");
                }

                // 5. Align auto-increment sequences (PostgreSQL setval)
                foreach ($tables as $table) {
                    try {
                        if (Schema::hasColumn($table, 'id')) {
                            $seqExists = DB::select("
                                SELECT pg_get_serial_sequence(:table, 'id') as seq
                            ", ['table' => $table])[0]->seq ?? null;

                            if ($seqExists) {
                                DB::statement("SELECT setval('$seqExists', COALESCE((SELECT MAX(id) FROM \"$table\"), 1))");
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip if sequence alignment fails (e.g. table has no sequence)
                    }
                }
            });

            // 6. Restore uploaded files if they exist in zip
            $sourceStorageApp = $tempDir.'/storage_app';
            if (is_dir($sourceStorageApp)) {
                $this->clearDirectorySafe(storage_path('app/private'));
                $this->clearDirectorySafe(storage_path('app/public'));

                $this->copyDirectorySafe($sourceStorageApp.'/private', storage_path('app/private'));
                $this->copyDirectorySafe($sourceStorageApp.'/public', storage_path('app/public'));
            }

            // Log out the user and invalidate session after restore succeeds
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Log activity
            AuditLog::create([
                'user_id' => $userId,
                'entity_type' => null,
                'entity_id' => null,
                'action' => 'backup_restore',
                'after_state' => ['filename' => $filename],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => __('System restored successfully.'),
                ]);
            }

            return redirect('/')->with('success', __('System restored successfully.'));
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Restore error: ').$e->getMessage(),
                ], 500);
            }

            return redirect()->route('settings.system')->with('error', __('Restore error: ').$e->getMessage());
        } finally {
            // Clean up tempDir
            $this->removeDirectoryRecursive($tempDir);
        }
    }

    /**
     * Reset the system (Wipe + Seed + Re-create Admin + Delete Uploads).
     */
    public function resetSystem(ResetSystemRequest $request)
    {
        try {
            // Capture request details for logging later before session is invalidated
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $initiatingUserId = $request->user()?->id;
            $initiatingUsername = $request->user()?->username;

            // Read via config() (not env()) so the values still resolve once
            // config is cached in production. The reset refuses to run unless
            // all three are explicitly configured — never a predictable default.
            $adminUsername = config('openmmes.admin.username');
            $adminEmail = config('openmmes.admin.email');
            $adminPassword = config('openmmes.admin.password');

            if (empty($adminUsername) || empty($adminEmail) || empty($adminPassword)) {
                throw new \Exception(__('Admin creation failed: ADMIN_USERNAME, ADMIN_EMAIL, or ADMIN_PASSWORD is not configured.'));
            }

            // 1. Log out the user and invalidate session first to prevent session middleware querying the database at the end of the request
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 2. Purge and reconnect to database to clear any cached plans or connections in Octane/Roadrunner worker
            DB::purge();
            DB::reconnect();

            // 3. Wipe and re-migrate & seed
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            // 4. Reconnect again after migrations to ensure fresh schema states
            DB::purge();
            DB::reconnect();

            // 5. Recreate admin user using explicitly configured values
            $admin = User::create([
                'name' => 'Administrator',
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password' => bcrypt($adminPassword),
                'force_password_change' => false,
                'email_verified_at' => now(),
            ]);
            $admin->assignRole('Admin');

            // 6. Clear uploads/attachments
            $this->clearDirectorySafe(storage_path('app/private'));
            $this->clearDirectorySafe(storage_path('app/public'));

            // 7. Log activity
            AuditLog::create([
                'user_id' => User::where('id', $initiatingUserId)->exists() ? $initiatingUserId : null,
                'entity_type' => null,
                'entity_id' => null,
                'action' => 'system_reset',
                'after_state' => [
                    'initiating_user_id' => $initiatingUserId,
                    'initiating_username' => $initiatingUsername,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => __('System has been reset to its initial state. Please log in again using the default Admin account.'),
                ]);
            }

            return redirect('/')->with('success', __('System has been reset to its initial state. Please log in again using the default Admin account.'));
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('System reset error: ').$e->getMessage(),
                ], 500);
            }

            return redirect()->route('settings.system')->with('error', __('System reset error: ').$e->getMessage());
        }
    }

    /**
     * Internal implementation of streaming backup.
     */
    private function runBackupInternal(bool $dataOnly): string
    {
        $tempDir = storage_path('app/temp_backup_'.uniqid());
        mkdir($tempDir, 0755, true);

        try {
            $jsonPath = $tempDir.'/db_backup.json';
            $handle = fopen($jsonPath, 'w');
            if (! $handle) {
                throw new \Exception(__('Could not create db_backup.json.'));
            }

            // Get tables list (excluding migrations and sessions)
            $tables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))
                ->pluck('table_name')
                ->reject(fn ($name) => in_array($name, ['migrations', 'sessions']))
                ->values()
                ->toArray();

            fwrite($handle, "{\n");
            $firstTable = true;

            foreach ($tables as $table) {
                if (! $firstTable) {
                    fwrite($handle, ",\n");
                }
                $firstTable = false;

                fwrite($handle, '  "'.$table."\": [\n");
                $firstRow = true;

                // Stream rows using cursor to keep memory usage low and avoid requiring orderBy
                DB::table($table)->cursor()->each(function ($row) use ($handle, &$firstRow) {
                    if (! $firstRow) {
                        fwrite($handle, ",\n");
                    }
                    $firstRow = false;
                    fwrite($handle, '    '.json_encode($row, JSON_UNESCAPED_UNICODE));
                });

                fwrite($handle, "\n  ]");
            }

            fwrite($handle, "\n}\n");
            fclose($handle);

            // Compress to Zip
            $backupsDir = $this->getBackupsDir();
            $zipName = ($dataOnly ? 'backup_data_' : 'backup_full_').date('Ymd_His').'.zip';
            $zipPath = $backupsDir.'/'.$zipName;

            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception(__('Could not create backup zip file.'));
            }

            // Add database json
            $zip->addFile($jsonPath, 'db_backup.json');

            // Add storage files recursively if not dataOnly
            if (! $dataOnly) {
                $storageAppPath = storage_path('app');
                $excludePath = $this->getBackupsDir();
                $this->addDirectoryToZip($zip, $storageAppPath, 'storage_app', $excludePath);
            }

            $zip->close();

            return $zipName;
        } finally {
            $this->removeDirectoryRecursive($tempDir);
        }
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $sourcePath, string $zipPathPrefix, string $excludePath)
    {
        if (! is_dir($sourcePath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();

                // Skip the backups folder
                if (str_starts_with($filePath, $excludePath)) {
                    continue;
                }

                // Skip temporary folders
                if (preg_match('/temp_(backup|restore)_/', $filePath)) {
                    continue;
                }

                $relativePath = substr($filePath, strlen($sourcePath) + 1);
                $zipPath = $zipPathPrefix.'/'.str_replace('\\', '/', $relativePath);

                $zip->addFile($filePath, $zipPath);
            }
        }
    }

    private function bulkInsertSafe(string $table, array $rows)
    {
        if (empty($rows)) {
            return;
        }
        // In PostgreSQL, some column definitions might require type casting. Standard insert is sufficient.
        // We run in chunks of 100 to make it extra safe.
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function clearDirectorySafe(string $dir)
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            if ($fileinfo->isFile() && $fileinfo->getFilename() === '.gitignore') {
                continue;
            }
            @$todo($fileinfo->getRealPath());
        }
    }

    private function copyDirectorySafe(string $src, string $dst)
    {
        if (! is_dir($src)) {
            return;
        }
        @mkdir($dst, 0755, true);

        $files = new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach ($files as $file) {
            $target = $dst.'/'.$file->getFilename();
            if ($file->isDir()) {
                $this->copyDirectorySafe($file->getRealPath(), $target);
            } else {
                $targetRealPath = realpath($target) ?: $target;
                $dstRealPath = realpath($dst) ?: $dst;
                if (str_starts_with($targetRealPath, $dstRealPath)) {
                    copy($file->getRealPath(), $target);
                }
            }
        }
    }

    private function removeDirectoryRecursive(string $dir)
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
