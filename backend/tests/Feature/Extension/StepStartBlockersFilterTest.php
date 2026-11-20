<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A module can refuse a step start through the `production.step_start_blockers` filter. */
class StepStartBlockersFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        DB::table('system_settings')->updateOrInsert(['key' => 'force_sequential_steps'], ['value' => json_encode(false)]);
        config(['openmmes.force_sequential_steps' => false]);
    }

    private function step(): BatchStep
    {
        $order = WorkOrder::factory()->create(['line_id' => Line::factory()->create()->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::create([
            'work_order_id' => $order->id, 'batch_number' => 1, 'target_qty' => 10, 'produced_qty' => 0,
            'status' => Batch::STATUS_IN_PROGRESS, 'started_at' => now(),
        ]);

        return BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'status' => BatchStep::STATUS_PENDING]);
    }

    public function test_a_listener_reason_refuses_the_start_and_leaves_the_step_untouched(): void
    {
        $seen = null;
        app(FilterRegistry::class)->addFilter('production.step_start_blockers', function (array $reasons, array $context) use (&$seen) {
            $seen = $context;

            return [...$reasons, 'No valid qualification for robot welding.'];
        });
        $step = $this->step();

        try {
            app(BatchService::class)->startStep($step, $this->operator);
            $this->fail('The start should have been refused.');
        } catch (\DomainException $e) {
            $this->assertSame('No valid qualification for robot welding.', $e->getMessage());
        }

        $this->assertSame(BatchStep::STATUS_PENDING, $step->fresh()->status);
        $this->assertTrue($seen['step']->is($step));
        $this->assertTrue($seen['user']->is($this->operator));
    }

    public function test_no_reason_lets_the_step_start(): void
    {
        app(FilterRegistry::class)->addFilter('production.step_start_blockers', fn (array $reasons) => [...$reasons, '', '  ']);

        $result = app(BatchService::class)->startStep($this->step(), $this->operator);

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_without_listeners_a_start_is_unchanged(): void
    {
        $result = app(BatchService::class)->startStep($this->step(), $this->operator);

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }
}
