<?php

namespace Modules\ExampleShowcase\Providers;

use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\Machine\WorkstationStateChanged;
use App\Events\MachineMessageReceived;
use App\Events\Resource\ResourceChanged;
use App\Events\Schedule\WorkOrderScheduled;
use App\Events\User\UserAssignedToLine;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderUpdated;
use App\Services\MenuRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\ExampleShowcase\Hooks;

/**
 * Wires the ExampleShowcase module into every OpenMES extension point. This
 * provider is only registered when the module is ENABLED (ModuleManager::
 * loadEnabled boots it in AppServiceProvider), so a disabled module adds nothing
 * — no listeners, no menu entries, no widgets, zero runtime cost.
 *
 * Three kinds of hook are demonstrated:
 *   1. Domain events   → Hooks methods (see Hooks.php)
 *   2. Menu hooks       → MenuRegistry (renders in the React sidebar)
 *   3. Dashboard widgets → WidgetRegistry (Blade zones)
 */
class ExampleShowcaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Own views + routes first — kept inside the module so it stays
        // self-contained and never edits a core file. Routes must be registered
        // before the menu hooks below, which resolve route('example-showcase.*').
        $this->loadViewsFrom(__DIR__.'/../views', 'example-showcase');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        $this->registerEventHooks();
        $this->registerMenuHooks();
        $this->registerWidgetHooks();
    }

    /**
     * 1) Domain events. Every business event OpenMES fires is mapped to a method
     *    on the single Hooks class (one method per hook, PrestaShop-style).
     */
    private function registerEventHooks(): void
    {
        Event::listen(WorkOrderCreated::class, [Hooks::class, 'onWorkOrderCreated']);
        Event::listen(WorkOrderUpdated::class, [Hooks::class, 'onWorkOrderUpdated']);
        Event::listen(WorkOrderCompleted::class, [Hooks::class, 'onWorkOrderCompleted']);
        Event::listen(BatchCreated::class, [Hooks::class, 'onBatchCreated']);
        Event::listen(StepStarted::class, [Hooks::class, 'onStepStarted']);
        Event::listen(StepCompleted::class, [Hooks::class, 'onStepCompleted']);
        Event::listen(WorkstationStateChanged::class, [Hooks::class, 'onWorkstationStateChanged']);
        Event::listen(MachineMessageReceived::class, [Hooks::class, 'onMachineMessageReceived']);
        Event::listen(UserAssignedToLine::class, [Hooks::class, 'onUserAssignedToLine']);

        // Generic CRUD (any curated resource) + planner scheduling changes.
        Event::listen(ResourceChanged::class, [Hooks::class, 'onResourceChanged']);
        Event::listen(WorkOrderScheduled::class, [Hooks::class, 'onWorkOrderScheduled']);
    }

    /**
     * 2) Menu hooks. All three MenuRegistry APIs. Registered items surface in the
     *    React sidebar via the `moduleNav` Inertia prop; module pages are legacy
     *    server-rendered, so the links do a full page load.
     */
    private function registerMenuHooks(): void
    {
        $menu = app(MenuRegistry::class);

        // URLs are resolved with url() (not route()) so registration never depends
        // on route-loading order or a cached route table — matches ExampleHooks.
        $page = url('/modules/example-showcase');

        // (a) Inject a link into an existing dropdown. Built-in group keys:
        //     orders | production | structure | hr | maintenance | admin.
        $menu->addItem('production', 'Showcase Page', $page, order: 90);

        // (b) Register a brand-new top-level dropdown...
        $menu->addGroup('showcase', 'Showcase', order: 55);
        // (c) ...and add links to it (auto-creates the group if not pre-registered).
        $menu->addGroupItem('showcase', 'Overview', $page, order: 10);
        $menu->addGroupItem('showcase', 'Hook Log', $page.'#log', order: 20);
    }

    /**
     * 3) Dashboard widget hooks. One card per zone (kpi | main | sidebar). Widgets
     *    are structured data — the React dashboard (DashboardController → the
     *    `moduleWidgets` prop) renders a standard card and escapes every field, so
     *    a module never ships raw HTML.
     */
    private function registerWidgetHooks(): void
    {
        $widgets = app(WidgetRegistry::class);
        $page = url('/modules/example-showcase');

        $widgets->register('kpi', [
            'title' => 'Example Showcase',
            'metric' => '9',
            'body' => 'hooks wired',
            'href' => $page,
            'external' => true,
        ], order: 90);

        $widgets->register('main', [
            'title' => 'Example Showcase — main widget',
            'body' => 'A module can drop a full-width card here (metrics, links to its own pages).',
            'href' => $page,
            'external' => true,
        ], order: 90);

        $widgets->register('sidebar', [
            'title' => 'Example Showcase',
            'body' => 'Sidebar-zone card.',
        ], order: 90);
    }
}
