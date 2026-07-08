<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class ModulesController extends Controller
{
    public function __construct(
        protected ModuleManager $manager
    ) {}

    public function index()
    {
        $modules = $this->manager->discover();

        return Inertia::render('admin/modules/Index', [
            'modules' => $modules->values(),
        ]);
    }

    public function install()
    {
        return Inertia::render('admin/modules/Install');
    }

    public function store()
    {
        return Inertia::render('admin/modules/Store');
    }

    public function enable(Request $request, string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', __('Module ":name" not found.', ['name' => $name]));
        }

        $this->manager->enable($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" enabled. Restart the server if changes don\'t appear.', ['name' => $module['display_name']]));
    }

    public function disable(Request $request, string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', __('Module ":name" not found.', ['name' => $name]));
        }

        $this->manager->disable($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" disabled.', ['name' => $module['display_name']]));
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
            return redirect()->back()->with('error', __('Install failed: :error', ['error' => $e->getMessage()]));
        } finally {
            @unlink($fullPath);
        }

        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" installed. Enable it below.', ['name' => $moduleName]));
    }

    public function destroy(string $name)
    {
        $modules = $this->manager->discover();
        $module  = $modules->firstWhere('name', $name);

        if (!$module) {
            return redirect()->back()->with('error', __('Module ":name" not found.', ['name' => $name]));
        }

        $this->manager->uninstall($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" uninstalled.', ['name' => $module['display_name']]));
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
