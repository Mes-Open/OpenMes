<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The READY ("Ready to start") batch-step status: PENDING (blocked) → READY
 * (prerequisites met, awaiting an operator) → IN_PROGRESS.
 */
class ReadyToStartStepTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        $this->operator = User::factory()->create(['account_type' => 'operator']);
        $this->operator->assignRole('Operator');
    }

    /** A batch with three plain sequential steps. */
    private function makeBatch(): Batch
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Cut']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Drill']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Pack']);

        $wo = WorkOrder::factory()->create(['process_snapshot' => $template->load('steps')->toSnapshot()]);

        return app(WorkOrderService::class)->createBatch($wo, 10);
    }

    private function step(Batch $batch, int $number): BatchStep
    {
        return $batch->steps()->where('step_number', $number)->first();
    }

    public function test_first_step_is_ready_and_the_rest_pending_on_creation(): void
    {
        $batch = $this->makeBatch();

        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 1)->status);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 2)->status);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 3)->status);
    }

    public function test_completing_a_step_promotes_the_next_to_ready(): void
    {
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->completeStep($this->step($batch, 1), $this->operator);

        $this->assertSame(BatchStep::STATUS_DONE, $this->step($batch, 1)->status);
        // Step 2 (previous now DONE) is promoted PENDING → READY; step 3 stays blocked.
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 3)->status);
    }

    public function test_only_a_ready_step_can_start(): void
    {
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        // Step 2 is PENDING (blocked) — starting it must fail.
        $this->assertFalse($this->step($batch, 2)->canStart());
        $this->expectException(\Exception::class);
        $service->startStep($this->step($batch, 2), $this->operator);
    }

    public function test_ready_step_starts_into_in_progress(): void
    {
        $batch = $this->makeBatch();
        $ready = $this->step($batch, 1);

        $this->assertTrue($ready->canStart());
        app(BatchService::class)->startStep($ready, $this->operator);

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $ready->fresh()->status);
    }

    public function test_non_sequential_mode_marks_all_steps_ready(): void
    {
        config(['openmmes.force_sequential_steps' => false]);

        $batch = $this->makeBatch();

        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 1)->status);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 3)->status);
    }
}
