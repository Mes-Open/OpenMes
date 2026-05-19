<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        if (!is_dir($this->modulesPath)) {
            return collect();
        }

        $enabled = $this->enabledNames();
        $modules = collect();

        foreach (scandir($this->modulesPath) as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $manifestPath = "{$this->modulesPath}/{$entry}/module.json";
            if (!is_file($manifestPath)) continue;

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest || empty($manifest['name'])) continue;

            $manifest['enabled']   = in_array($manifest['name'], $enabled);
            $manifest['directory'] = $entry;
            $manifest['has_error'] = false;

            // Check if provider class exists (only if enabled)
            if ($manifest['enabled'] && !empty($manifest['provider'])) {
                $manifest['has_error'] = !class_exists($manifest['provider']);
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
     * Enable a module by name.
     */
    public function enable(string $name): void
    {
        $enabled = $this->enabledNames();
        if (!in_array($name, $enabled)) {
            $enabled[] = $name;
            $this->saveEnabled($enabled);
        }
    }

    /**
     * Disable a module by name.
     */
    public function disable(string $name): void
    {
        $enabled = array_values(array_filter($this->enabledNames(), fn($n) => $n !== $name));
        $this->saveEnabled($enabled);
    }

    /**
     * Install a module from a ZIP file.
     * Returns the module name on success, throws on failure.
     */
    public function installFromZip(string $zipPath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive PHP extension is required.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open ZIP file.');
        }

        // Find module.json inside the ZIP
        $manifestContent = null;
        $moduleRoot      = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, 'module.json')) {
                $manifestContent = $zip->getFromIndex($i);
                // Determine the root directory inside the ZIP
                $parts      = explode('/', $name);
                $moduleRoot = count($parts) > 1 ? $parts[0] : null;
                break;
            }
        }

        if (!$manifestContent) {
            $zip->close();
            throw new \RuntimeException('No module.json found inside the ZIP.');
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || empty($manifest['name'])) {
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
        if (!str_starts_with($manifest['provider'], 'Modules\\')) {
            $zip->close();
            throw new \RuntimeException('Invalid provider namespace: must start with "Modules\\".');
        }

        // Validate module name (alphanumeric + hyphens only, no path traversal)
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $manifest['name'])) {
            $zip->close();
            throw new \RuntimeException('Invalid module name: only alphanumeric characters, hyphens, and underscores are allowed.');
        }

        // Check for path traversal in ZIP entries
        $dangerousFunctions = ['exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen', 'eval'];
        $dangerousPattern = '/\b(' . implode('|', $dangerousFunctions) . ')\s*\(/i';

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

        $moduleName  = $manifest['name'];
        $destDir     = "{$this->modulesPath}/{$moduleName}";

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
     */
    public function uninstall(string $name): void
    {
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
            if (!is_file($manifestPath)) continue;

            $manifest = json_decode(file_get_contents($manifestPath), true);
            $provider  = $manifest['provider'] ?? null;

            if ($provider && class_exists($provider)) {
                $app->register($provider);
            }
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
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = "{$dir}/{$item}";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
