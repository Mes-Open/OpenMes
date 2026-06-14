<?php

namespace Tests\Feature;

use App\Models\BatchStep;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1: optional/variant flags flow template → snapshot → batch steps, and
 * the default variant is pre-selected at batch instantiation (siblings skipped).
 */
class ProcessOptionalVariantStepTest extends TestCase
{
    use RefreshDatabase;

    private function templateWithVariantSteps(): ProcessTemplate
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create([
            'product_type_id' => $pt->id,
            'is_active' => true,
        ]);

        // 1 = required, 2 = optional, 3 & 4 = variant group "finish" (4 is default)
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Cut']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Polish', 'is_optional' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'Matte finish', 'variant_group' => 'finish', 'is_default_variant' => false]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 4, 'name' => 'Gloss finish', 'variant_group' => 'finish', 'is_default_variant' => true]);

        return $template->load('steps');
    }

    public function test_snapshot_carries_optional_and_variant_flags(): void
    {
        $snapshot = $this->templateWithVariantSteps()->toSnapshot();

        $byNum = collect($snapshot['steps'])->keyBy('step_number');

        $this->assertFalse($byNum[1]['is_optional']);
        $this->assertNull($byNum[1]['variant_group']);
        $this->assertTrue($byNum[2]['is_optional']);
        $this->assertSame('finish', $byNum[3]['variant_group']);
        $this->assertFalse($byNum[3]['is_default_variant']);
        $this->assertSame('finish', $byNum[4]['variant_group']);
        $this->assertTrue($byNum[4]['is_default_variant']);
    }

    public function test_batch_steps_inherit_flags_and_default_variant_is_preselected(): void
    {
        $snapshot = $this->templateWithVariantSteps()->toSnapshot();
        $wo = WorkOrder::factory()->create(['process_snapshot' => $snapshot]);

        $batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $steps = $batch->steps()->orderBy('step_number')->get()->keyBy('step_number');

        // Required + optional both start pending; optional flag copied.
        $this->assertSame(BatchStep::STATUS_PENDING, $steps[1]->status);
        $this->assertFalse($steps[1]->is_optional);
        $this->assertSame(BatchStep::STATUS_PENDING, $steps[2]->status);
        $this->assertTrue($steps[2]->is_optional);

        // Variant group: default (step 4) active, sibling (step 3) auto-skipped.
        $this->assertSame(BatchStep::STATUS_SKIPPED, $steps[3]->status);
        $this->assertSame(BatchStep::STATUS_PENDING, $steps[4]->status);
        $this->assertSame('finish', $steps[3]->variant_group);
        $this->assertSame('finish', $steps[4]->variant_group);
    }

    public function test_variant_group_without_explicit_default_picks_lowest_step_number(): void
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'A', 'variant_group' => 'g']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'B', 'variant_group' => 'g']);

        $wo = WorkOrder::factory()->create(['process_snapshot' => $template->load('steps')->toSnapshot()]);
        $batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $steps = $batch->steps()->orderBy('step_number')->get()->keyBy('step_number');

        $this->assertSame(BatchStep::STATUS_PENDING, $steps[1]->status); // lowest = default
        $this->assertSame(BatchStep::STATUS_SKIPPED, $steps[2]->status);
    }

    public function test_can_skip_only_for_optional_or_variant_steps(): void
    {
        $snapshot = $this->templateWithVariantSteps()->toSnapshot();
        $wo = WorkOrder::factory()->create(['process_snapshot' => $snapshot]);
        $batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $steps = $batch->steps()->orderBy('step_number')->get()->keyBy('step_number');

        $this->assertFalse($steps[1]->canSkip());            // required, pending
        $this->assertTrue($steps[2]->canSkip());             // optional
        $this->assertTrue($steps[4]->canSkip());             // variant member
    }
}
