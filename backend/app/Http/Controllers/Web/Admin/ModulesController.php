<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleManager;
use App\Services\OctaneReloader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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
        $module = $modules->firstWhere('name', $name);

        if (! $module) {
            return redirect()->back()->with('error', __('Module ":name" not found.', ['name' => $name]));
        }

        $this->manager->enable($name);
        $this->clearCache();

        // A module that ships tables registers them with loadMigrationsFrom in
        // its provider, which only runs once the module is enabled — so this is
        // the first moment they can be applied. Without it the module's screens
        // load and then fail on the first query.
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->manager->runInstaller($name, 'install');
        } catch (\Throwable $e) {
            report($e);

            // Leave it disabled rather than half-installed: an enabled module
            // whose tables are missing breaks on every page it contributes to.
            $this->manager->disable($name);
            $this->clearCache();

            return redirect()->route('admin.modules.index')->with('error', __(
                'Module ":name" could not be installed: :msg',
                ['name' => $module['display_name'], 'msg' => $e->getMessage()],
            ));
        }

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" enabled.', ['name' => $module['display_name']]));
    }

    public function disable(Request $request, string $name)
    {
        $modules = $this->manager->discover();
        $module = $modules->firstWhere('name', $name);

        if (! $module) {
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

        $file = $request->file('module_zip');
        $zipPath = $file->store('module-uploads', 'local');
        // Ask the disk where it put the file. The `local` disk is rooted at
        // storage/app/private (Laravel 11+), so a hand-built storage/app/… path
        // pointed at a file that was never there — every upload failed with
        // "Could not open ZIP file" and the stored zip was never cleaned up.
        $fullPath = Storage::disk('local')->path($zipPath);

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
        $module = $modules->firstWhere('name', $name);

        if (! $module) {
            return redirect()->back()->with('error', __('Module ":name" not found.', ['name' => $name]));
        }

        $this->manager->uninstall($name);
        $this->clearCache();

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":name" uninstalled.', ['name' => $module['display_name']]));
    }

    protected function clearCache(): void
    {
        // Routes as well as config: a module registers its own routes with
        // loadRoutesFrom, which Laravel skips entirely while a route cache is
        // in place — so on a production install the module's pages would 404.
        foreach (['config:clear', 'route:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Exception) {
                // Non-fatal
            }
        }

        $this->reloadWorkers();
    }

    /**
     * Make the change take effect on the running server.
     *
     * Under Octane the booted application lives in memory between requests, so
     * clearing caches is not enough — the workers still hold the routes they
     * loaded and the providers they registered at boot. Without this, disabling
     * a module leaves every one of its pages serving normally until somebody
     * restarts the server by hand, which for a paid module is not an
     * inconvenience but a hole.
     *
     * Deferred until after the response: reloading mid-request can take down the
     * worker that is still holding the redirect the admin is waiting for.
     */
    protected function reloadWorkers(): void
    {
        if (! class_exists(\Laravel\Octane\Octane::class)) {
            return;
        }

        // Hooked to Octane's own end-of-request event, not app()->terminating():
        // Octane serves each request from a sandbox container that is flushed
        // before Laravel would reach those callbacks, so a terminating callback
        // registered here never runs and the reload silently never happens.
        //
        // Waiting for the event also keeps the reload off the critical path —
        // reloading mid-request can take down the worker still holding the
        // redirect the admin is waiting for. RoadRunner reloads gracefully, so
        // in-flight requests finish.
        Event::listen(\Laravel\Octane\Events\RequestTerminated::class, function () {
            $this->reloadNow();
        });
    }

    /**
     * Signal the Octane master process to cycle its workers.
     *
     * The work lives in OctaneReloader, resolved from the container so a test
     * can swap it — see that class for why the reload cannot be done in-process
     * from an HTTP worker.
     */
    protected function reloadNow(): void
    {
        app(OctaneReloader::class)->reload();
    }
}
