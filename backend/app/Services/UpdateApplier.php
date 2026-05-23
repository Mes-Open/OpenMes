<?php

namespace App\Services;

use App\Models\SystemUpdate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Self-update applier.
 *
 * Encapsulates the full apply-update flow (download → verify → extract → backup
 * → copy → migrate → cache-clear → prune) so it can run from a queued Job
 * without blocking the HTTP request that triggered it.
 *
 * All state is reported through the shared `update_apply_status` cache key so
 * the admin update banner can poll progress.
 */
class UpdateApplier
{
    public const STATUS_CACHE_KEY = 'update_apply_status';
    public const STATUS_CACHE_TTL = 86400; // 24h
    public const CHECK_CACHE_KEY  = 'update_check_result';

    private const BACKUP_KEEP            = 3;
    private const CHECKSUM_ALGO          = 'sha256';
    private const MAINTENANCE_SECRET_KEY = 'update_maintenance_secret';

    /**
     * Run the full update flow.
     *
     * Reports progress via Cache; throws on hard failure (Job will mark failed).
     */
    public function run(string $version, array $remote, int $userId): void
    {
        $root = base_path();
        $zipUrl = $remote['zip_url']
            ?? str_replace('{version}', $version, config('version.archive_url'));

        $tempZip = storage_path("app/update-{$version}.zip");
        $tempDir = storage_path("app/update-{$version}");

        // 0. Open audit record. A failure to persist must NOT abort the update
        //    — admins still get cache + log telemetry, and the table is a
        //    convenience, not a precondition.
        $auditRecord = $this->openAuditRecord($version, $userId);

        // 1. Download
        $this->setStatus([
            'state'    => 'downloading',
            'progress' => 5,
            'message'  => __('Downloading update package…'),
        ]);

        try {
            $response = Http::timeout(120)->withOptions(['sink' => $tempZip])->get($zipUrl);

            if (! $response->ok() || ! file_exists($tempZip) || filesize($tempZip) < 1000) {
                @unlink($tempZip);
                $msg = __('Failed to download update package. Check server internet connection.');
                $this->patchAuditRecord($auditRecord, [
                    'state'       => SystemUpdate::STATE_FAILED,
                    'finished_at' => now(),
                    'error'       => (string) $msg,
                ]);
                $this->fail($msg);
                return;
            }
        } catch (\Throwable $e) {
            @unlink($tempZip);
            Log::error('Update download failed: ' . $e->getMessage());
            $msg = __('Failed to download update: :error', ['error' => $e->getMessage()]);
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
            $this->fail($msg);
            return;
        }

        // 1b. Verify checksum
        $this->setStatus([
            'state'    => 'verifying',
            'progress' => 20,
            'message'  => __('Verifying integrity…'),
        ]);

        $checksumResult = $this->verifyChecksumDetailed($tempZip, $remote);
        if ($checksumResult['error'] !== null) {
            @unlink($tempZip);
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => $checksumResult['error'],
            ]);
            $this->fail($checksumResult['error']);
            return;
        }

        if ($checksumResult['verified']) {
            $this->patchAuditRecord($auditRecord, ['checksum_verified' => true]);
        }

        // 2. Extract
        $this->setStatus([
            'state'    => 'extracting',
            'progress' => 35,
            'message'  => __('Extracting update package…'),
        ]);

        $zip = new \ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            $msg = __('Failed to open update package.');
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => (string) $msg,
            ]);
            $this->fail($msg);
            return;
        }

        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($tempZip);

        // 3. Resolve source root
        $extracted   = glob($tempDir . '/*', GLOB_ONLYDIR);
        $sourceRoot  = $extracted[0] ?? $tempDir;
        $sourceBackend = is_dir($sourceRoot . '/backend') ? $sourceRoot . '/backend' : $sourceRoot;

        if (! is_dir($sourceBackend)) {
            $this->deleteDirectory($tempDir);
            $msg = __('Invalid update package structure.');
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => (string) $msg,
            ]);
            $this->fail($msg);
            return;
        }

        $protected = ['storage', 'bootstrap/cache', '.env', 'vendor', 'node_modules'];

        // 4. Snapshot
        $this->setStatus([
            'state'    => 'backing_up',
            'progress' => 50,
            'message'  => __('Creating safety snapshot…'),
        ]);

        $timestamp = date('Ymd-His');
        $backupDir = storage_path("app/update-backups/{$timestamp}-{$version}");
        $manifest  = ['modified' => [], 'created' => []];

        try {
            if (! is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            $manifest = $this->createBackup($sourceBackend, $root, $protected, $backupDir);

            @file_put_contents(
                $backupDir . '/manifest.json',
                json_encode([
                    'version'   => $version,
                    'timestamp' => $timestamp,
                    'modified'  => $manifest['modified'],
                    'created'   => $manifest['created'],
                ], JSON_PRETTY_PRINT)
            );

            Log::info("Update backup created at {$backupDir}", [
                'version'        => $version,
                'modified_count' => count($manifest['modified']),
                'created_count'  => count($manifest['created']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Update backup failed: ' . $e->getMessage());
            $this->deleteDirectory($tempDir);
            $this->deleteDirectory($backupDir);
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
            $this->fail(__('Failed to create backup before update: :error', ['error' => $e->getMessage()]));
            return;
        }

        // 5. Copy + migrate (atomic — rollback on failure)
        // Enter maintenance mode so end-users do not hit a half-copied app
        // while files are being swapped in and migrations are being applied.
        try {
            $this->enterMaintenance();
        } catch (\Throwable $e) {
            Log::error('Failed to enter maintenance mode, aborting update', [
                'version' => $version,
                'error'   => $e->getMessage(),
            ]);
            $this->deleteDirectory($tempDir);
            $this->deleteDirectory($backupDir);
            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
            $this->fail(__('Failed to enter maintenance mode before update: :error', ['error' => $e->getMessage()]));
            return;
        }

        // Detect composer dependency drift BEFORE copying — compare the lock
        // file shipped by the release against the live one. We capture the
        // 'old' hash up-front because copyDirectory will overwrite composer.lock.
        $oldLockPath = $root . '/composer.lock';
        $newLockPath = $sourceBackend . '/composer.lock';
        $oldLockHash = file_exists($oldLockPath) ? md5_file($oldLockPath) : null;
        $newLockHash = file_exists($newLockPath) ? md5_file($newLockPath) : null;
        $composerChanged = $oldLockHash !== $newLockHash;

        $this->setStatus([
            'state'    => 'copying',
            'progress' => 70,
            'message'  => __('Installing new files…'),
        ]);

        $composerWarning = null;
        $copyCount = 0;
        try {
            $copyCount = $this->copyDirectory($sourceBackend, $root, $protected);

            // If composer.lock changed, vendor/ (which is in $protected and
            // therefore NOT overwritten) is now out of sync with the new lock
            // file. Run composer install to bring it in line before migrations
            // — otherwise any new classes referenced by migrate/cache code
            // will trigger fatal errors.
            if ($composerChanged) {
                $composerWarning = $this->runComposerInstall($root);
                // Stamp only when composer actually executed (no warning means
                // the binary ran and returned 0). A warning here means the
                // binary was missing — vendor/ stayed untouched.
                if ($composerWarning === null) {
                    $this->patchAuditRecord($auditRecord, ['composer_install_ran' => true]);
                }
            }

            $this->setStatus([
                'state'    => 'migrating',
                'progress' => 85,
                'message'  => __('Running database migrations…'),
            ]);

            Artisan::call('migrate', ['--force' => true]);

            // Files + DB are now consistent — let end-users back in before
            // cache-clear (which is safe to run with traffic flowing).
            $this->exitMaintenance();
        } catch (\Throwable $e) {
            Log::error('Update failed, rolling back', [
                'version' => $version,
                'error'   => $e->getMessage(),
            ]);

            $this->setStatus([
                'state'   => 'rolling_back',
                'message' => __('Update failed — rolling back…'),
                'error'   => $e->getMessage(),
            ]);

            try {
                $this->restoreBackup($backupDir, $manifest, $root);
            } finally {
                // Always exit maintenance after rollback — even if restore
                // partially failed, leaving the app stuck in 'down' is worse
                // than serving the (possibly inconsistent) restored state.
                $this->exitMaintenance();
            }

            try {
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                Artisan::call('cache:clear');
            } catch (\Throwable $clearErr) {
                Log::warning('Post-rollback cache clear failed: ' . $clearErr->getMessage());
            }

            $this->deleteDirectory($tempDir);

            Log::error('Update failed, rolled back to previous version', [
                'version'    => $version,
                'backup_dir' => $backupDir,
            ]);

            $this->setStatus([
                'state'    => 'rolled_back',
                'progress' => 100,
                'message'  => __('Update failed and was rolled back: :error', ['error' => $e->getMessage()]),
                'error'    => $e->getMessage(),
            ]);

            $this->patchAuditRecord($auditRecord, [
                'state'       => SystemUpdate::STATE_ROLLED_BACK,
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
            return;
        }

        // 6. Cleanup temp
        $this->deleteDirectory($tempDir);

        // 7. Clear caches
        $this->setStatus([
            'state'    => 'migrating',
            'progress' => 95,
            'message'  => __('Clearing caches…'),
        ]);

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // 8. Drop update-check cache so banner refreshes
        Cache::forget(self::CHECK_CACHE_KEY);

        // 9. Prune backups
        $this->pruneBackups(self::BACKUP_KEEP);

        Log::info("System updated to {$version}", ['files_copied' => $copyCount]);

        $this->patchAuditRecord($auditRecord, [
            'state'        => SystemUpdate::STATE_COMPLETED,
            'finished_at'  => now(),
            'files_copied' => $copyCount,
        ]);

        $this->setStatus([
            'state'            => 'completed',
            'progress'         => 100,
            'message'          => __('Updated to :version successfully (:count files updated).', [
                'version' => $version,
                'count'   => $copyCount,
            ]),
            'files_copied'     => $copyCount,
            'error'            => null,
            'composer_warning' => $composerWarning,
        ]);
    }

    /**
     * Run `composer install --no-dev` against the freshly-copied codebase to
     * bring vendor/ in sync with the new composer.lock.
     *
     * Returns null on success or when skipped silently (no warning). Returns a
     * human-readable warning string if no composer binary could be located —
     * the update is allowed to continue (e.g. on locked-down docker prod
     * environments without a composer CLI) but the admin gets a warning.
     *
     * Throws \RuntimeException on hard composer execution failure so the
     * caller's existing rollback path picks it up — a release whose
     * composer install fails is broken and must be reverted.
     */
    private function runComposerInstall(string $root): ?string
    {
        $this->setStatus([
            'state'    => 'installing_dependencies',
            'progress' => 65,
            'message'  => __('Installing PHP dependencies…'),
        ]);

        $composerPath = $this->locateComposerBinary($root);
        if ($composerPath === null) {
            $warning = 'Composer binary not found on server — vendor/ may be stale; run "composer install --no-dev" manually.';
            Log::warning('composer binary not found, skipping vendor install — release may have broken dependencies', [
                'root' => $root,
            ]);
            return $warning;
        }

        // --no-scripts: prevent post-install hooks from clearing caches in the
        //   middle of the update (we do that ourselves at the end of run()).
        // --optimize-autoloader: production-grade classmap for performance.
        // --no-dev: production install only.
        $result = Process::path($root)
            ->timeout(600)
            ->run([
                $composerPath,
                'install',
                '--no-dev',
                '--no-interaction',
                '--prefer-dist',
                '--optimize-autoloader',
                '--no-scripts',
            ]);

        if ($result->exitCode() !== 0) {
            Log::error('composer install failed', [
                'exit'        => $result->exitCode(),
                'output'      => $result->output(),
                'errorOutput' => $result->errorOutput(),
            ]);

            throw new \RuntimeException('Composer install failed: ' . $result->errorOutput());
        }

        Log::info('composer install completed', [
            'binary' => $composerPath,
        ]);

        return null;
    }

    /**
     * Try to locate a composer binary on the host. Returns null if none found.
     *
     * Search order:
     *   1. `which composer` (system PATH)
     *   2. /usr/local/bin/composer
     *   3. $root/composer.phar (project-local)
     */
    private function locateComposerBinary(string $root): ?string
    {
        $which = Process::run(['which', 'composer']);
        if ($which->exitCode() === 0) {
            $path = trim($which->output());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        $candidates = [
            '/usr/local/bin/composer',
            $root . '/composer.phar',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Open the persistent audit row for this run. Returns null on storage
     * failure (e.g. DB locked, table missing on a brand-new install before
     * migrations) — the rest of the flow must tolerate a null record so an
     * audit-side problem does not break the update itself.
     */
    private function openAuditRecord(string $version, int $userId): ?SystemUpdate
    {
        try {
            return SystemUpdate::create([
                'user_id'              => $userId > 0 ? $userId : null,
                'from_version'         => config('version.current'),
                'to_version'           => $version,
                'state'                => SystemUpdate::STATE_QUEUED,
                'started_at'           => now(),
                'checksum_verified'    => false,
                'composer_install_ran' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to open system_updates audit row: ' . $e->getMessage(), [
                'version' => $version,
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Patch the audit row. Audit failures are swallowed — same rationale as
     * `openAuditRecord()`: telemetry is best-effort and must not break updates.
     */
    private function patchAuditRecord(?SystemUpdate $record, array $changes): void
    {
        if ($record === null) {
            return;
        }

        try {
            // Compute duration_seconds whenever we are reaching a terminal
            // state and the caller has not explicitly provided one.
            if (
                ! array_key_exists('duration_seconds', $changes)
                && isset($changes['state'])
                && in_array($changes['state'], [
                    SystemUpdate::STATE_COMPLETED,
                    SystemUpdate::STATE_FAILED,
                    SystemUpdate::STATE_ROLLED_BACK,
                ], true)
                && $record->started_at !== null
            ) {
                $changes['finished_at']      = $changes['finished_at'] ?? now();
                $changes['duration_seconds'] = max(
                    0,
                    $changes['finished_at']->diffInSeconds($record->started_at)
                );
            }

            $record->update($changes);
        } catch (\Throwable $e) {
            Log::warning('Failed to patch system_updates audit row: ' . $e->getMessage(), [
                'changes' => array_keys($changes),
            ]);
        }
    }

    /**
     * Mark the most recent queued audit row for the given target version as
     * failed. Used by `ApplyUpdateJob::failed()` when the job blew up before
     * `run()` could record a terminal state itself.
     */
    public function markPendingAuditFailed(string $version, string $error): void
    {
        try {
            $record = SystemUpdate::where('to_version', $version)
                ->where('state', SystemUpdate::STATE_QUEUED)
                ->orderByDesc('id')
                ->first();

            if ($record === null) {
                return;
            }

            $this->patchAuditRecord($record, [
                'state'       => SystemUpdate::STATE_FAILED,
                'finished_at' => now(),
                'error'       => $error,
            ]);
        } catch (\Throwable $e) {
            Log::warning('markPendingAuditFailed swallowed: ' . $e->getMessage());
        }
    }

    /**
     * Initialise the status cache before dispatching the Job.
     * Called by the controller so the banner sees the queued state immediately.
     */
    public function initStatus(string $version, int $userId): void
    {
        $now = now()->toIso8601String();

        Cache::put(self::STATUS_CACHE_KEY, [
            'state'              => 'queued',
            'version'            => $version,
            'started_at'         => $now,
            'updated_at'         => $now,
            'progress'           => 0,
            'message'            => __('Queued — waiting for worker.'),
            'error'              => null,
            'started_by_user_id' => $userId,
            'files_copied'       => null,
        ], self::STATUS_CACHE_TTL);
    }

    /**
     * Set a hard failure terminal state and return.
     */
    private function fail(string $message): void
    {
        // Defensive: drop any maintenance bypass secret left over from a
        // partially-started run so it cannot leak across update attempts.
        Cache::forget(self::MAINTENANCE_SECRET_KEY);

        $this->setStatus([
            'state'                  => 'failed',
            'progress'               => 100,
            'message'                => $message,
            'error'                  => $message,
            'maintenance_active'     => false,
            'maintenance_bypass_url' => null,
        ]);
    }

    /**
     * Merge a partial status patch into the cached status payload.
     */
    private function setStatus(array $patch): void
    {
        $existing = Cache::get(self::STATUS_CACHE_KEY) ?? [];
        $merged   = array_merge($existing, $patch, [
            'updated_at' => now()->toIso8601String(),
        ]);

        Cache::put(self::STATUS_CACHE_KEY, $merged, self::STATUS_CACHE_TTL);
    }

    /**
     * Return the one-shot maintenance bypass secret for this update run,
     * generating + caching it on first call.
     */
    private function maintenanceSecret(): string
    {
        return Cache::remember(
            self::MAINTENANCE_SECRET_KEY,
            self::STATUS_CACHE_TTL,
            static fn (): string => Str::random(32)
        );
    }

    /**
     * Put Laravel into maintenance mode for the critical copy + migrate
     * window. Publishes the bypass URL into the status cache so the admin
     * banner can surface it.
     */
    private function enterMaintenance(): void
    {
        $secret = $this->maintenanceSecret();

        // `--render` intentionally omitted: resources/views/errors/503.blade.php
        // is not present in this repo, so Laravel's default maintenance screen
        // will be served instead.
        Artisan::call('down', [
            '--retry'  => 60,
            '--secret' => $secret,
        ]);

        $bypassUrl = rtrim(url('/'), '/') . '/' . $secret;

        $this->setStatus([
            'maintenance_active'      => true,
            'maintenance_bypass_url'  => $bypassUrl,
        ]);

        Log::info('Update entered maintenance mode', [
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Lift maintenance mode and clear the bypass advertisement from status.
     * Safe to call multiple times — `artisan up` is a no-op when already up.
     */
    private function exitMaintenance(): void
    {
        try {
            Artisan::call('up');
        } catch (\Throwable $e) {
            Log::error('Failed to exit maintenance mode: ' . $e->getMessage());
            // Do not rethrow — leaving the system 'down' is worse than logging.
        }

        Cache::forget(self::MAINTENANCE_SECRET_KEY);

        $this->setStatus([
            'maintenance_active'     => false,
            'maintenance_bypass_url' => null,
        ]);

        Log::info('Update exited maintenance mode', [
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Same as `verifyChecksum()` but also reports whether the release actually
     * advertised a hash that we successfully matched. Used by `run()` to set
     * the `checksum_verified` audit flag — `false` means the release shipped
     * without a hash (we still proceed), `true` means we actually verified.
     *
     * Returns ['error' => ?string, 'verified' => bool].
     */
    private function verifyChecksumDetailed(string $zipPath, array $remote): array
    {
        $expected = $remote['sha256'] ?? ($remote['assets'][0]['sha256'] ?? null);
        $advertised = is_string($expected) && trim($expected) !== '';

        $error = $this->verifyChecksum($zipPath, $remote);

        return [
            'error'    => $error,
            'verified' => $advertised && $error === null,
        ];
    }

    /**
     * Verify the downloaded ZIP against the SHA-256 checksum advertised by the release.
     * Returns null when OK or no checksum advertised; returns error message on mismatch.
     */
    private function verifyChecksum(string $zipPath, array $remote): ?string
    {
        $expected = $remote['sha256'] ?? ($remote['assets'][0]['sha256'] ?? null);
        $version  = $remote['version'] ?? 'unknown';

        if (! is_string($expected) || trim($expected) === '') {
            Log::warning("Update release does not advertise sha256 checksum — integrity check skipped for {$version}");
            return null;
        }

        $actual = @hash_file(self::CHECKSUM_ALGO, $zipPath);
        if ($actual === false) {
            Log::error('Update checksum verification failed — unable to hash downloaded file', [
                'version' => $version,
                'path'    => $zipPath,
            ]);
            return __('Update package integrity check failed — file does not match expected checksum. Update aborted.');
        }

        if (strtolower($actual) !== strtolower(trim($expected))) {
            Log::error('Update checksum mismatch', [
                'version'   => $version,
                'expected'  => strtolower(trim($expected)),
                'actual'    => strtolower($actual),
                'algorithm' => self::CHECKSUM_ALGO,
                'size'      => @filesize($zipPath),
            ]);
            return __('Update package integrity check failed — file does not match expected checksum. Update aborted.');
        }

        return null;
    }

    /**
     * Walk $source and snapshot any destination files that would be overwritten,
     * recording new files separately so they can be removed on rollback.
     */
    private function createBackup(string $source, string $dest, array $protected, string $backupDir, string $relPath = ''): array
    {
        $manifest = ['modified' => [], 'created' => []];

        $items = @scandir($source);
        if ($items === false) {
            return $manifest;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $currentRel = $relPath ? $relPath . '/' . $item : $item;

            if (in_array($currentRel, $protected, true)) {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $sub = $this->createBackup($srcPath, $dstPath, $protected, $backupDir, $currentRel);
                $manifest['modified'] = array_merge($manifest['modified'], $sub['modified']);
                $manifest['created']  = array_merge($manifest['created'], $sub['created']);
                continue;
            }

            if (file_exists($dstPath)) {
                $backupPath   = $backupDir . '/files/' . $currentRel;
                $backupParent = dirname($backupPath);
                if (! is_dir($backupParent)) {
                    @mkdir($backupParent, 0755, true);
                }

                if (! @copy($dstPath, $backupPath)) {
                    throw new \RuntimeException("Failed to snapshot file: {$currentRel}");
                }

                $manifest['modified'][] = $currentRel;
            } else {
                $manifest['created'][] = $currentRel;
            }
        }

        return $manifest;
    }

    /**
     * Restore files from a backup manifest.
     */
    private function restoreBackup(string $backupDir, array $manifest, string $root): void
    {
        $filesDir = $backupDir . '/files';

        foreach ($manifest['modified'] ?? [] as $rel) {
            $src = $filesDir . '/' . $rel;
            $dst = $root . '/' . $rel;

            if (! file_exists($src)) {
                Log::warning("Rollback: missing backup file {$rel}");
                continue;
            }

            $dstParent = dirname($dst);
            if (! is_dir($dstParent)) {
                @mkdir($dstParent, 0755, true);
            }

            if (! @copy($src, $dst)) {
                Log::warning("Rollback: failed to restore {$rel}");
            }
        }

        foreach ($manifest['created'] ?? [] as $rel) {
            $path = $root . '/' . $rel;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Keep the N most recent backup directories.
     */
    private function pruneBackups(int $keep = 3): void
    {
        $base = storage_path('app/update-backups');
        if (! is_dir($base)) {
            return;
        }

        $entries = @scandir($base);
        if ($entries === false) {
            return;
        }

        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $base . '/' . $entry;
            if (is_dir($path)) {
                $dirs[$path] = @filemtime($path) ?: 0;
            }
        }

        arsort($dirs);
        $stale = array_slice(array_keys($dirs), $keep);

        foreach ($stale as $path) {
            $this->deleteDirectory($path);
        }
    }

    /**
     * Recursively copy directory, skipping protected paths.
     */
    private function copyDirectory(string $source, string $dest, array $protected, string $relPath = ''): int
    {
        $count = 0;

        if (! is_dir($dest)) {
            if (! @mkdir($dest, 0755, true) && ! is_dir($dest)) {
                throw new \RuntimeException("Failed to create directory: {$dest}");
            }
        }

        $items = @scandir($source);
        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $currentRel = $relPath ? $relPath . '/' . $item : $item;

            if (in_array($currentRel, $protected, true)) {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $count += $this->copyDirectory($srcPath, $dstPath, $protected, $currentRel);
            } else {
                if (! @copy($srcPath, $dstPath)) {
                    throw new \RuntimeException("Failed to copy file: {$currentRel}");
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
