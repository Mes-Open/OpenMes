<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ModulesController extends Controller
{
    public function __construct(
        protected ModuleManager $manager
    ) {}

    public function index()
    {
        $modules = $this->manager->discover();

        return view('admin.modules.index', compact('modules'));
    }

    public function enable(Request $request, string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', "Module \"{$name}\" not found.");
        }

        $this->manager->enable($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', "Module \"{$module['display_name']}\" enabled. Restart the server if changes don't appear.");
    }

    public function disable(Request $request, string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', "Module \"{$name}\" not found.");
        }

        $this->manager->disable($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', "Module \"{$module['display_name']}\" disabled.");
    }

    public function upload(Request $request)
    {
        $request->validate([
            'module_zip' => 'required|file|mimes:zip|max:20480',
        ]);

        $file    = $request->file('module_zip');
        $zipPath = $file->store('module-uploads', 'local');
        $fullPath = storage_path("app/{$zipPath}");

        try {
            $moduleName = $this->manager->installFromZip($fullPath);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', 'Install failed: ' . $e->getMessage());
        } finally {
            @unlink($fullPath);
        }

        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', "Module \"{$moduleName}\" installed. Enable it below.");
    }

    public function destroy(string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', "Module \"{$name}\" not found.");
        }

        $this->manager->uninstall($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', "Module \"{$module['display_name']}\" uninstalled.");
    }

    protected function clearCache(): void
    {
        try {
            Artisan::call('config:clear');
        } catch (\Exception) {
            // Non-fatal
        }
    }
}
