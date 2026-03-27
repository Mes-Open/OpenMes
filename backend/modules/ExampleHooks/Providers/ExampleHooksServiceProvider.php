<?php

namespace Modules\ExampleHooks\Providers;

use App\Contracts\Modules\ModuleContract;
use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Services\MenuRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\ExampleHooks\Listeners\LogBatchCreated;
use Modules\ExampleHooks\Listeners\LogStepCompleted;
use Modules\ExampleHooks\Listeners\LogStepStarted;
use Modules\ExampleHooks\Listeners\LogWorkOrderCompleted;
use Modules\ExampleHooks\Listeners\LogWorkOrderCreated;

class ExampleHooksServiceProvider extends ServiceProvider implements ModuleContract
{
    public function getName(): string
    {
        return 'ExampleHooks';
    }

    public function getDisplayName(): string
    {
        return 'Example Hooks';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(Application $app): void
    {
        // Register module-specific services here
    }

    public function boot(Application $app = null): void
    {
        $app = $app ?: $this->app;

        // --- Event listeners ---
        Event::listen(WorkOrderCreated::class,   LogWorkOrderCreated::class);
        Event::listen(WorkOrderCompleted::class, LogWorkOrderCompleted::class);
        Event::listen(BatchCreated::class,       LogBatchCreated::class);
        Event::listen(StepStarted::class,        LogStepStarted::class);
        Event::listen(StepCompleted::class,      LogStepCompleted::class);

        // --- Menu hooks ---
        $menu = $app->make(MenuRegistry::class);
        $menu->addItem('admin', 'Example Module', url('/'), 90);
    }
}
