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

class OptionalVariantStepExecutionTest extends TestCase
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

    /** A batch whose steps are: 1 required, 2 optional, 3/4 variant group (4 default). */
    private function makeBatch(): Batch
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Cut']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Polish', 'is_optional' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Matte', 'variant_group' => 'finish']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 4, 'name' => 'Gloss', 'variant_group' => 'finish', 'is_default_variant' => true]);

        $wo = WorkOrder::factory()->create(['process_snapshot' => $template->load('steps')->toSnapshot()]);

        return app(WorkOrderService::class)->createBatch($wo, 10);
    }

    private function step(Batch $batch, int $number): BatchStep
    {
        return $batch->steps()->where('step_number', $number)->first();
    }

    private function actingOperator(Batch $batch)
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $batch->workOrder->line_id]);
    }

    public function test_operator_can_skip_optional_step(): void
    {
        $batch = $this->makeBatch();
        $optional = $this->step($batch, 2);

        $this->actingOperator($batch)
            ->post("/operator/batch-step/{$optional->id}/skip", ['skip_reason' => 'Not needed for this order'])
            ->assertSessionHasNoErrors();

        $optional->refresh();
        $this->assertSame(BatchStep::STATUS_SKIPPED, $optional->status);
        $this->assertSame('Not needed for this order', $optional->skip_reason);
        $this->assertSame($this->operator->id, $optional->completed_by_id);
    }

    public function test_required_step_cannot_be_skipped(): void
    {
        $batch = $this->makeBatch();
        $required = $this->step($batch, 1);

        $this->actingOperator($batch)
            ->post("/operator/batch-step/{$required->id}/skip")
            ->assertSessionHas('error');

        $this->assertSame(BatchStep::STATUS_PENDING, $required->fresh()->status);
    }

    public function test_default_variant_is_active_and_sibling_skipped(): void
    {
        $batch = $this->makeBatch();

        $this->assertSame(BatchStep::STATUS_SKIPPED, $this->step($batch, 3)->status); // matte
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 4)->status);  // gloss (default)
    }

    public function test_operator_can_switch_variant(): void
    {
        $batch = $this->makeBatch();
        $matte = $this->step($batch, 3);

        $this->actingOperator($batch)
            ->post("/operator/batch-step/{$matte->id}/choose-variant")
            ->assertSessionHasNoErrors();

        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 3)->fresh()->status);
        $this->assertSame(BatchStep::STATUS_SKIPPED, $this->step($batch, 4)->fresh()->status);
    }

    public function test_batch_is_not_complete_while_a_variant_group_is_fully_skipped(): void
    {
        $batch = $this->makeBatch();
        $svc = app(BatchService::class);

        // Do the required + optional, then skip the only active variant too.
        $svc->startStep($this->step($batch, 1), $this->operator);
        $svc->completeStep($this->step($batch, 1)->fresh(), $this->operator);
        $svc->skipStep($this->step($batch, 2), $this->operator);
        $svc->skipStep($this->step($batch, 4), $this->operator); // skip the default gloss too

        // Group "finish" now has zero executed steps → batch must NOT be complete.
        $this->assertFalse($batch->fresh()->allStepsComplete());

        // Choosing + completing one variant resolves the group.
        $svc->chooseVariant($this->step($batch, 3)->fresh(), $this->operator);
        $svc->startStep($this->step($batch, 3)->fresh(), $this->operator);
        $svc->completeStep($this->step($batch, 3)->fresh(), $this->operator);

        $this->assertTrue($batch->fresh()->allStepsComplete());
    }
}
