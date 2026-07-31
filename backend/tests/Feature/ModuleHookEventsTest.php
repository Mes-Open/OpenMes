<?php

namespace Tests\Feature;

use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\Resource\ResourceChanged;
use App\Events\Schedule\WorkOrderScheduled;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderUpdated;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Customer;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The module hook system's domain events must actually fire on real saves.
 * These were previously defined but never dispatched; now they are wired via
 * model observers ($dispatchesEvents), a generic CRUD listener (ResourceChanged)
 * and the planner controller (WorkOrderScheduled).
 *
 * We fake only the domain events under test so the underlying model lifecycle
 * still runs (the observers/listeners that dispatch them).
 */
class ModuleHookEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_lifecycle_events_fire_on_save(): void
    {
        Event::fake([WorkOrderCreated::class, WorkOrderUpdated::class, WorkOrderCompleted::class]);

        $wo = WorkOrder::factory()->create();
        Event::assertDispatched(WorkOrderCreated::class, fn ($e) => $e->workOrder->is($wo));

        $wo->update(['planned_qty' => 999]);
        Event::assertDispatched(WorkOrderUpdated::class, fn ($e) => array_key_exists('planned_qty', $e->changes));

        // Not completed yet.
        Event::assertNotDispatched(WorkOrderCompleted::class);

        $wo->update(['status' => WorkOrder::STATUS_DONE]);
        Event::assertDispatched(WorkOrderCompleted::class, fn ($e) => $e->workOrder->is($wo));
    }

    public function test_batch_and_step_events_fire(): void
    {
        Event::fake([BatchCreated::class, StepStarted::class, StepCompleted::class]);

        $batch = Batch::factory()->create();
        Event::assertDispatched(BatchCreated::class, fn ($e) => $e->batch->is($batch));

        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'status' => BatchStep::STATUS_PENDING]);

        $step->update(['status' => BatchStep::STATUS_IN_PROGRESS]);
        Event::assertDispatched(StepStarted::class, fn ($e) => $e->batchStep->is($step));

        $step->update(['status' => BatchStep::STATUS_DONE]);
        Event::assertDispatched(StepCompleted::class, fn ($e) => $e->batchStep->is($step));
    }

    public function test_resource_changed_fires_for_curated_entities(): void
    {
        Event::fake([ResourceChanged::class]);

        $customer = Customer::factory()->create();
        Event::assertDispatched(ResourceChanged::class, fn ($e) => $e->model->is($customer) && $e->action === 'created');

        $customer->update(['name' => 'Renamed']);
        Event::assertDispatched(ResourceChanged::class, fn ($e) => $e->model->is($customer) && $e->action === 'updated');

        $customer->delete();
        Event::assertDispatched(ResourceChanged::class, fn ($e) => $e->action === 'deleted');
    }

    public function test_work_order_scheduled_fires_on_planner_update(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $line = Line::factory()->create();
        $wo = WorkOrder::factory()->create(['line_id' => null]);

        Event::fake([WorkOrderScheduled::class]);

        $this->actingAs($admin)
            ->putJson(route('admin.schedule.update', $wo), [
                'line_id' => $line->id,
                'due_date' => now()->addDay()->format('Y-m-d'),
                'shift_number' => 1,
            ])
            ->assertSuccessful();

        Event::assertDispatched(WorkOrderScheduled::class, fn ($e) => $e->workOrder->is($wo));
    }
}
