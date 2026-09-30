<?php

namespace App\Services;

use App\Support\CoreVersionConstraint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModuleManager
{
    protected string $modulesPath;

    protected string $settingsKey = 'modules_enabled';

    public function __construct()
    {
        $this->modulesPath = base_path('modules');
    }

    /**
     * Discover all modules by scanning the modules/ directory.
     */
    public function discover(): Collection
    {
        if (! is_dir($this->modulesPath)) {
            return collect();
        }

        $enabled = $this->enabledNames();
        $modules = collect();

        foreach (scandir($this->modulesPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $manifestPath = "{$this->modulesPath}/{$entry}/module.json";
            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (! $manifest || empty($manifest['name'])) {
                continue;
            }

            // A module lives in a directory named after itself — that is how
            // installFromZip() puts it there and how loadEnabled() finds it.
            // Anything else carrying a module.json is a leftover: a backup an
            // updater parked next to the module, a half-finished copy, an
            // unpacked archive. Those used to show up here as a second, also
            // "enabled" install of the same module, because enablement matches
            // on the manifest name and the copy carries the same one — two
            // cards, two directories, the same provider class, and no way for
            // the operator to tell which is which.
            if ($manifest['name'] !== $entry) {
                Log::warning('module.directory_name_mismatch', [
                    'directory' => $entry,
                    'declares' => $manifest['name'],
                ]);

                continue;
            }

            $manifest['enabled'] = in_array($manifest['name'], $enabled);
            $manifest['directory'] = $entry;
            $manifest['has_error'] = false;

            // Check if provider class exists (only if enabled)
            if ($manifest['enabled'] && ! empty($manifest['provider'])) {
                $manifest['has_error'] = ! class_exists($manifest['provider']);
            }

            $modules->push($manifest);
        }

        return $modules->sortBy('display_name');
    }

    /**
     * Return list of enabled module names.
     */
    public function enabledNames(): array
    {
        try {
            $row = DB::table('system_settings')->where('key', $this->settingsKey)->first();

            return $row ? (json_decode($row->value, true) ?? []) : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * The module's manifest, or an empty array when it has none.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $name): array
    {
        $path = "{$this->modulesPath}/{$name}/module.json";

        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }

    /**
     * Whether this core satisfies a module's `requires_core`.
     *
     * An unreadable constraint counts as unsatisfied rather than as "no
     * constraint": a typo must not quietly widen what a module accepts.
     */
    public function coreSatisfies(?string $constraint): bool
    {
        try {
            return CoreVersionConstraint::isSatisfied($constraint);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Enable a module by name.
     */
    public function enable(string $name): void
    {
        $this->publishAssets($name);

        $enabled = $this->enabledNames();
        if (! in_array($name, $enabled)) {
            $enabled[] = $name;
            $this->saveEnabled($enabled);
        }
    }

    /**
     * Disable a module by name.
     */
    public function disable(string $name): void
    {
        $enabled = array_values(array_filter($this->enabledNames(), fn ($n) => $n !== $name));
        $this->saveEnabled($enabled);

        $this->unpublishAssets($name);
    }

    /**
     * Copy a module's compiled frontend into public/, where the browser can
     * fetch it.
     *
     * A module's pages are not in this app's bundle — that was compiled before
     * the module existed, and nothing rebuilds it on a running system. The
     * module ships its own built file instead, and it has to be reachable over
     * HTTP for the browser to load it; modules/ is not web-accessible.
     *
     * Silently does nothing for a module without a built frontend: plenty of
     * modules are backend-only, and those are not broken.
     */
    public function publishAssets(string $name): void
    {
        $source = "{$this->modulesPath}/{$name}/public/{$name}.js";

        if (! is_file($source)) {
            return;
        }

        $targetDir = public_path('modules');
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        copy($source, "{$targetDir}/{$name}.js");

        // The sidecar records which runtime contract the module was built
        // against; HandleInertiaRequests passes it on so the browser can refuse
        // a module built for a core it no longer matches.
        $manifest = "{$this->modulesPath}/{$name}/public/{$name}.runtime.json";
        if (is_file($manifest)) {
            copy($manifest, "{$targetDir}/{$name}.runtime.json");
        }
    }

    /** Remove what publishAssets() put in public/. */
    public function unpublishAssets(string $name): void
    {
        foreach (["{$name}.js", "{$name}.runtime.json"] as $file) {
            $path = public_path("modules/{$file}");
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Enabled modules that ship a frontend, as the browser needs them: a URL to
     * load and the contract version the file was built against.
     *
     * @return array<int, array{name: string, url: string, requires_runtime: int|null}>
     */
    public function frontendAssets(): array
    {
        $assets = [];

        foreach ($this->enabledNames() as $name) {
            $path = public_path("modules/{$name}.js");

            if (! is_file($path)) {
                continue;
            }

            $requires = null;
            $sidecar = public_path("modules/{$name}.runtime.json");
            if (is_file($sidecar)) {
                $decoded = json_decode(file_get_contents($sidecar), true);
                $requires = is_array($decoded) ? ($decoded['requires_runtime'] ?? null) : null;
            }

            $assets[] = [
                'name' => $name,
                // Cache-busted on the file's contents, not its mtime: publishing
                // copies the file, so mtime is "when it was copied" — it changes
                // on every enable/disable cycle even when nothing about the
                // module did, throwing away the browser's cache for no reason,
                // and two publishes inside the same second collide.
                'url' => "/modules/{$name}.js?v=".substr(md5_file($path), 0, 12),
                'requires_runtime' => $requires,
            ];
        }

        return $assets;
    }

    /**
     * Install a module from a ZIP file.
     * Returns the module name on success, throws on failure.
     */
    public function installFromZip(string $zipPath): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive PHP extension is required.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open ZIP file.');
        }

        // Find module.json inside the ZIP
        $manifestContent = null;
        $moduleRoot = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, 'module.json')) {
                $manifestContent = $zip->getFromIndex($i);
                // Determine the root directory inside the ZIP
                $parts = explode('/', $name);
                $moduleRoot = count($parts) > 1 ? $parts[0] : null;
                break;
            }
        }

        if (! $manifestContent) {
            $zip->close();
            throw new \RuntimeException('No module.json found inside the ZIP.');
        }

        $manifest = json_decode($manifestContent, true);
        if (! $manifest || empty($manifest['name'])) {
            $zip->close();
            throw new \RuntimeException('Invalid module.json: missing "name" field.');
        }

        // Validate required manifest fields
        if (empty($manifest['version'])) {
            $zip->close();
            throw new \RuntimeException('Invalid module.json: missing "version" field.');
        }
        if (empty($manifest['provider'])) {
            $zip->close();
            throw new \RuntimeException('Invalid module.json: missing "provider" field.');
        }

        // Validate provider namespace
        if (! str_starts_with($manifest['provider'], 'Modules\\')) {
            $zip->close();
            throw new \RuntimeException('Invalid provider namespace: must start with "Modules\\".');
        }

        // The core version the module was built against. Nothing read this
        // until now, so a module installed on an older core simply did not
        // work: the seams it relies on were absent, and the admin was left
        // guessing. Refusing the install says so once, at the only moment
        // anybody can act on it.
        if (! $this->coreSatisfies($manifest['requires_core'] ?? null)) {
            $zip->close();
            throw new \RuntimeException(sprintf(
                'Module "%s" requires OpenMES %s; this installation is %s.',
                $manifest['name'],
                $manifest['requires_core'],
                config('version.current'),
            ));
        }

        // Validate module name (alphanumeric + hyphens only, no path traversal)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $manifest['name'])) {
            $zip->close();
            throw new \RuntimeException('Invalid module name: only alphanumeric characters, hyphens, and underscores are allowed.');
        }

        // Check for path traversal in ZIP entries
        $dangerousFunctions = ['exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen', 'eval'];
        $dangerousPattern = '/\b('.implode('|', $dangerousFunctions).')\s*\(/i';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            // Path traversal check
            $normalized = str_replace('\\', '/', $entryName);
            if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                $zip->close();
                throw new \RuntimeException("ZIP contains suspicious path: {$entryName}");
            }

            // Scan PHP files for dangerous functions
            if (str_ends_with(strtolower($entryName), '.php')) {
                $content = $zip->getFromIndex($i);
                if ($content !== false && preg_match($dangerousPattern, $content)) {
                    $zip->close();
                    throw new \RuntimeException("PHP file contains prohibited function call: {$entryName}");
                }
            }
        }

        $moduleName = $manifest['name'];
        $destDir = "{$this->modulesPath}/{$moduleName}";

        // Extract
        if ($moduleRoot) {
            // ZIP has a top-level directory — extract and rename it
            $tmpDir = "{$this->modulesPath}/_tmp_{$moduleName}";
            $zip->extractTo($tmpDir);
            $zip->close();

            if (is_dir($destDir)) {
                $this->deleteDirectory($destDir);
            }
            rename("{$tmpDir}/{$moduleRoot}", $destDir);
            $this->deleteDirectory($tmpDir);
        } else {
            // ZIP contains files at the root
            $zip->extractTo($destDir);
            $zip->close();
        }

        return $moduleName;
    }

    /**
     * Delete an installed module directory.
     *
     * The module's own uninstall hook runs FIRST, while its classes are still
     * on disk and autoloadable — after the directory is gone there is nothing
     * left to call. It is the module's only chance to undo what it did outside
     * its own tables: permissions it registered, settings it wrote.
     *
     * A throwing hook aborts the uninstall and leaves the directory in place.
     * Deleting anyway would run half an uninstall and lose the other half with
     * no way to retry; this way the administrator sees the failure and the
     * module is still there to try again. The module's own migrations are NOT
     * rolled back — see the note the controller shows.
     */
    public function uninstall(string $name): void
    {
        $this->runInstaller($name, 'uninstall');

        $this->disable($name);
        $dir = "{$this->modulesPath}/{$name}";
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    /**
     * Load all enabled module ServiceProviders into the application.
     * Called from AppServiceProvider::boot().
     */
    public function loadEnabled(\Illuminate\Foundation\Application $app): void
    {
        foreach ($this->enabledNames() as $name) {
            $manifestPath = "{$this->modulesPath}/{$name}/module.json";
            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            $provider = $manifest['provider'] ?? null;

            if ($provider && class_exists($provider)) {
                // Wrapped rather than registered directly: an exception from the
                // provider's boot() is thrown while Laravel boots every provider,
                // long after this method returned, so the caller's try/catch
                // cannot see it — and one bad module would 502 the whole app.
                //
                // Registered with force, because the container deduplicates by
                // class name: without it the second module's guard would be
                // mistaken for the first one's and silently skipped.
                $app->register(
                    new \App\Support\ModuleProviderGuard($app, new $provider($app), $name),
                    force: true,
                );
            }
        }
    }

    /**
     * The module's own migrations directory, or null when it ships none.
     *
     * Needed because a module's migrations are registered by its service
     * provider, which is only loaded at boot — so the process that *enables* a
     * module cannot see them and has to be told where they are.
     */
    public function migrationsPath(string $name): ?string
    {
        $path = "{$this->modulesPath}/{$name}/database/migrations";

        return is_dir($path) ? $path : null;
    }

    /**
     * Run a module's optional installer hook.
     *
     * Migrations cover tables, but a module usually has setup that is not schema:
     * the permissions behind the tabs it registers, default settings, seed rows.
     * A module provides it by shipping `Modules\<Name>\Installer` with an
     * `install()` and/or `uninstall()` method; modules without one are the normal
     * case and this does nothing.
     *
     * Exceptions are deliberately NOT swallowed — the caller decides what a
     * failed install means, and enabling a module whose setup failed is worse
     * than not enabling it.
     *
     * @param  string  $method  install | uninstall
     */
    public function runInstaller(string $name, string $method = 'install'): void
    {
        $installer = "Modules\\{$name}\\Installer";

        if (! class_exists($installer)) {
            return;
        }

        $instance = app($installer);

        if (method_exists($instance, $method)) {
            $instance->{$method}();
        }
    }

    // -------------------------------------------------------------------------

    protected function saveEnabled(array $names): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $this->settingsKey],
            ['value' => json_encode(array_values($names)), 'description' => 'Enabled module names']
        );
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "{$dir}/{$item}";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
