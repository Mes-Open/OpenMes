<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        if (!is_dir($dir)) {
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
                'action' => 'backup_create_full',
                'after_state' => ['filename' => $filename],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Sao lưu toàn bộ website thành công.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Lỗi tạo bản sao lưu: ') . $e->getMessage());
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
                'action' => 'backup_create_data',
                'after_state' => ['filename' => $filename],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Sao lưu dữ liệu thành công.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Lỗi tạo bản sao lưu dữ liệu: ') . $e->getMessage());
        }
    }

    /**
     * Upload a backup file.
     */
    public function uploadBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:zip|max:102400', // max 100MB
        ]);

        try {
            $file = $request->file('backup_file');
            $backupsDir = $this->getBackupsDir();

            $safeName = 'uploaded_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $file->getClientOriginalName());
            $file->move($backupsDir, $safeName);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'backup_upload',
                'after_state' => ['filename' => $safeName],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Tải lên tệp sao lưu thành công.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Lỗi tải lên bản sao lưu: ') . $e->getMessage());
        }
    }

    /**
     * Download a backup.
     */
    public function downloadBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir . '/' . $filename);

        if (!$filePath || !str_starts_with($filePath, realpath($backupsDir))) {
            abort(400, __('Đường dẫn không hợp lệ.'));
        }

        if (!file_exists($filePath)) {
            abort(404, __('Không tìm thấy file sao lưu.'));
        }

        return response()->download($filePath, $filename);
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir . '/' . $filename);

        if (!$filePath || !str_starts_with($filePath, realpath($backupsDir))) {
            return redirect()->route('settings.system')->with('error', __('Đường dẫn không hợp lệ.'));
        }

        if (!file_exists($filePath)) {
            return redirect()->route('settings.system')->with('error', __('Không tìm thấy file sao lưu.'));
        }

        unlink($filePath);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'backup_delete',
            'before_state' => ['filename' => $filename],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('settings.system')->with('success', __('Đã xóa bản sao lưu thành công.'));
    }

    /**
     * Restore from a backup file (Streaming parse).
     */
    public function restoreBackup(Request $request, string $filename)
    {
        $backupsDir = $this->getBackupsDir();
        $filePath = realpath($backupsDir . '/' . $filename);

        if (!$filePath || !str_starts_with($filePath, realpath($backupsDir))) {
            return redirect()->route('settings.system')->with('error', __('Đường dẫn không hợp lệ.'));
        }

        if (!file_exists($filePath)) {
            return redirect()->route('settings.system')->with('error', __('Không tìm thấy file sao lưu.'));
        }

        $tempDir = storage_path('app/temp_restore_' . uniqid());
        mkdir($tempDir, 0755, true);

        try {
            // Extract the Zip
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new \Exception(__('Không thể mở file zip sao lưu.'));
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $jsonPath = $tempDir . '/db_backup.json';
            if (!file_exists($jsonPath)) {
                throw new \Exception(__('File sao lưu không chứa dữ liệu database (thiếu db_backup.json).'));
            }

            // Get tables list in database (excluding migrations)
            $tables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))
                ->pluck('table_name')
                ->reject(fn($name) => $name === 'migrations')
                ->values()
                ->toArray();

            // Perform DB Restore in a Transaction
            DB::transaction(function () use ($jsonPath, $tables) {
                // 1. Disable triggers to skip foreign key validation
                foreach ($tables as $table) {
                    DB::statement("ALTER TABLE \"$table\" DISABLE TRIGGER ALL;");
                }

                // 2. Truncate tables to clear existing data and reset identity sequences
                foreach ($tables as $table) {
                    DB::statement("TRUNCATE TABLE \"$table\" RESTART IDENTITY CASCADE;");
                }

                // 3. Streaming read and restore of table data
                $handle = fopen($jsonPath, 'r');
                if (!$handle) {
                    throw new \Exception(__('Không thể mở file db_backup.json.'));
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
                        if ($currentTable && !empty($buffer)) {
                            $this->bulkInsertSafe($currentTable, $buffer);
                            $buffer = [];
                        }
                        $currentTable = $matches[1];
                        continue;
                    }

                    // Check for table end: ] or ],
                    if ($line === ']' || $line === '],') {
                        if ($currentTable && !empty($buffer)) {
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

                if ($currentTable && !empty($buffer)) {
                    $this->bulkInsertSafe($currentTable, $buffer);
                }

                fclose($handle);

                // 4. Re-enable triggers
                foreach ($tables as $table) {
                    DB::statement("ALTER TABLE \"$table\" ENABLE TRIGGER ALL;");
                }

                // 5. Align auto-increment sequences (PostgreSQL setval)
                foreach ($tables as $table) {
                    $seqExists = DB::select("
                        SELECT pg_get_serial_sequence(:table, 'id') as seq
                    ", ['table' => $table])[0]->seq ?? null;

                    if ($seqExists) {
                        DB::statement("SELECT setval('$seqExists', COALESCE((SELECT MAX(id) FROM \"$table\"), 1))");
                    }
                }
            });

            // 6. Restore uploaded files if they exist in zip
            $sourceStorageApp = $tempDir . '/storage_app';
            if (is_dir($sourceStorageApp)) {
                $this->clearDirectorySafe(storage_path('app/private'));
                $this->clearDirectorySafe(storage_path('app/public'));

                $this->copyDirectorySafe($sourceStorageApp . '/private', storage_path('app/private'));
                $this->copyDirectorySafe($sourceStorageApp . '/public', storage_path('app/public'));
            }

            // Log activity
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'backup_restore',
                'after_state' => ['filename' => $filename],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('settings.system')->with('success', __('Khôi phục hệ thống thành công.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Lỗi khôi phục: ') . $e->getMessage());
        } finally {
            // Clean up tempDir
            $this->removeDirectoryRecursive($tempDir);
        }
    }

    /**
     * Reset the system (Wipe + Seed + Re-create Admin + Delete Uploads).
     */
    public function resetSystem(Request $request)
    {
        $request->validate([
            'confirm_text' => 'required|string|in:RESET',
        ]);

        try {
            // 1. Wipe and re-migrate & seed
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            // 2. Recreate admin user
            $admin = User::create([
                'name'                  => 'Administrator',
                'username'              => env('ADMIN_USERNAME', 'admin'),
                'email'                 => env('ADMIN_EMAIL', 'admin@example.com'),
                'password'              => bcrypt(env('ADMIN_PASSWORD', 'Admin1234!')),
                'force_password_change' => false,
                'email_verified_at'     => now(),
            ]);
            $admin->assignRole('Admin');

            // 3. Clear uploads/attachments
            $this->clearDirectorySafe(storage_path('app/private'));
            $this->clearDirectorySafe(storage_path('app/public'));

            // Log activity (note: auth state is wiped out since we migrated fresh, so we log with new admin user id)
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'system_reset',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // In Laravel, since we wiped the sessions table, we should force logout the current session
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('success', __('Hệ thống đã được thiết lập lại về trạng thái ban đầu. Hãy đăng nhập lại bằng tài khoản Admin mặc định.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.system')->with('error', __('Lỗi reset hệ thống: ') . $e->getMessage());
        }
    }

    /**
     * Internal implementation of streaming backup.
     */
    private function runBackupInternal(bool $dataOnly): string
    {
        $tempDir = storage_path('app/temp_backup_' . uniqid());
        mkdir($tempDir, 0755, true);

        try {
            $jsonPath = $tempDir . '/db_backup.json';
            $handle = fopen($jsonPath, 'w');
            if (!$handle) {
                throw new \Exception(__('Không thể tạo file db_backup.json.'));
            }

            // Get tables list
            $tables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))
                ->pluck('table_name')
                ->reject(fn($name) => $name === 'migrations')
                ->values()
                ->toArray();

            fwrite($handle, "{\n");
            $firstTable = true;

            foreach ($tables as $table) {
                if (!$firstTable) {
                    fwrite($handle, ",\n");
                }
                $firstTable = false;

                fwrite($handle, "  \"" . $table . "\": [\n");
                $firstRow = true;

                // Stream rows using lazy cursor to keep memory usage low
                DB::table($table)->lazy(500)->each(function ($row) use ($handle, &$firstRow) {
                    if (!$firstRow) {
                        fwrite($handle, ",\n");
                    }
                    $firstRow = false;
                    fwrite($handle, "    " . json_encode($row, JSON_UNESCAPED_UNICODE));
                });

                fwrite($handle, "\n  ]");
            }

            fwrite($handle, "\n}\n");
            fclose($handle);

            // Compress to Zip
            $backupsDir = $this->getBackupsDir();
            $zipName = ($dataOnly ? 'backup_data_' : 'backup_full_') . date('Ymd_His') . '.zip';
            $zipPath = $backupsDir . '/' . $zipName;

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception(__('Không thể tạo file zip sao lưu.'));
            }

            // Add database json
            $zip->addFile($jsonPath, 'db_backup.json');

            // Add storage files recursively if not dataOnly
            if (!$dataOnly) {
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
        if (!is_dir($sourcePath)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
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
                $zipPath = $zipPathPrefix . '/' . str_replace('\\', '/', $relativePath);

                $zip->addFile($filePath, $zipPath);
            }
        }
    }

    private function bulkInsertSafe(string $table, array $rows)
    {
        if (empty($rows)) return;
        // In PostgreSQL, some column definitions might require type casting. Standard insert is sufficient.
        // We run in chunks of 100 to make it extra safe.
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function clearDirectorySafe(string $dir)
    {
        if (!is_dir($dir)) return;

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
        if (!is_dir($src)) return;
        @mkdir($dst, 0755, true);

        $files = new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach ($files as $file) {
            $target = $dst . '/' . $file->getFilename();
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
        if (!is_dir($dir)) return;

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
