<?php

namespace Modules\ExampleHooks\Providers;

use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Services\MenuRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\ExampleHooks\Listeners\LogBatchCreated;
use Modules\ExampleHooks\Listeners\LogStepCompleted;
use Modules\ExampleHooks\Listeners\LogStepStarted;
use Modules\ExampleHooks\Listeners\LogWorkOrderCompleted;
use Modules\ExampleHooks\Listeners\LogWorkOrderCreated;

class ExampleHooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // --- Event listeners ---
        Event::listen(WorkOrderCreated::class,   LogWorkOrderCreated::class);
        Event::listen(WorkOrderCompleted::class, LogWorkOrderCompleted::class);
        Event::listen(BatchCreated::class,       LogBatchCreated::class);
        Event::listen(StepStarted::class,        LogStepStarted::class);
        Event::listen(StepCompleted::class,      LogStepCompleted::class);

        // --- Menu hooks ---
        // This demonstrates both ways to extend the navigation menu.
        $menu = app(MenuRegistry::class);

        // 1. Add a link to an existing dropdown (built-in group keys:
        //    orders | production | structure | hr | maintenance | admin).
        //    Items are separated from built-in links by a divider and sorted by 'order'.
        $menu->addItem('admin', 'Example Module', url('/'), order: 90);

        // 2. Register a brand-new top-level dropdown with its own items.
        //    addGroup() sets the button label and sort position among other custom groups.
        //    addGroupItem() adds links to it (auto-creates the group if not pre-registered).
        // $menu->addGroup('example', 'Example', order: 55);
        // $menu->addGroupItem('example', 'Overview',  url('/'), order: 10);
        // $menu->addGroupItem('example', 'Settings',  url('/'), order: 20);
    }
}
