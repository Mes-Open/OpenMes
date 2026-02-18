<?php

namespace Modules\ExampleHooks\Providers;

use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
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
        Event::listen(WorkOrderCreated::class,   LogWorkOrderCreated::class);
        Event::listen(WorkOrderCompleted::class, LogWorkOrderCompleted::class);
        Event::listen(BatchCreated::class,       LogBatchCreated::class);
        Event::listen(StepStarted::class,        LogStepStarted::class);
        Event::listen(StepCompleted::class,      LogStepCompleted::class);
    }
}
