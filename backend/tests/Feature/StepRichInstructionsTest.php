<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepChecklistCompletion;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepMedia;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rich work instructions on a process step: media (image/PDF/video) and
 * checklists defined on the template step, rendered at the operator workstation,
 * with checklist completion - while plain-text instructions keep working.
 */
class StepRichInstructionsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    private ProcessTemplate $template;

    private TemplateStep $templateStep;

    private WorkOrder $workOrder;

    private BatchStep $batchStep;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'web']);
        }
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $this->templateStep = TemplateStep::factory()->create([
            'process_template_id' => $this->template->id,
            'step_number' => 1,
            'name' => 'Assemble',
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $productType->id,
            'process_snapshot' => ['template_id' => $this->template->id],
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $this->workOrder->id]);
        $this->batchStep = BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'name' => 'Assemble',
            'instruction' => 'Plain text instruction still works',
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
    }

    private function media(string $type = TemplateStepMedia::TYPE_IMAGE): TemplateStepMedia
    {
        $factory = match ($type) {
            TemplateStepMedia::TYPE_PDF => TemplateStepMedia::factory()->pdf(),
            TemplateStepMedia::TYPE_VIDEO => TemplateStepMedia::factory()->video(),
            default => TemplateStepMedia::factory(),
        };

        return $factory->create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
        ]);
    }

    private function checklistItem(string $label = 'Torque to 5Nm'): TemplateStepChecklistItem
    {
        return TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
            'label' => $label,
        ]);
    }

    public function test_operator_view_carries_step_media_checklist_and_plain_text(): void
    {
        $this->media(TemplateStepMedia::TYPE_IMAGE);
        $this->media(TemplateStepMedia::TYPE_VIDEO);
        $this->checklistItem('Torque to 5Nm');

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get(route('operator.work-order.detail', $this->workOrder))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operator/WorkOrderDetail')
                ->has('stepMedia.1', 2)                       // two media on step 1
                ->where('stepMedia.1.0.media_type', 'image')
                ->has('stepChecklists.1', 1)                  // one checklist item
                ->where('stepChecklists.1.0.label', 'Torque to 5Nm')
                // Plain-text instruction is still delivered on the batch step.
                ->where('workOrder.batches.0.steps.0.instruction', 'Plain text instruction still works')
            );
    }

    public function test_operator_can_check_and_uncheck_a_checklist_item(): void
    {
        $item = $this->checklistItem();

        // Check it - records who and when.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.checklist.toggle', [$this->batchStep, $item]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('batch_step_checklist_completions', [
            'batch_step_id' => $this->batchStep->id,
            'checklist_item_id' => $item->id,
            'checked_by_id' => $this->operator->id,
        ]);
        $completion = BatchStepChecklistCompletion::firstOrFail();
        $this->assertNotNull($completion->checked_at);

        // Toggle again - un-checks (soft-deletes, keeping the who/when audit).
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.checklist.toggle', [$this->batchStep, $item]))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('batch_step_checklist_completions', [
            'batch_step_id' => $this->batchStep->id,
            'checklist_item_id' => $item->id,
        ]);

        // And it can be re-checked afterwards (partial unique allows a new live row).
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.checklist.toggle', [$this->batchStep, $item]))
            ->assertSessionHas('success');
        $this->assertSame(1, BatchStepChecklistCompletion::where('batch_step_id', $this->batchStep->id)
            ->where('checklist_item_id', $item->id)->count());
    }

    public function test_checklist_toggle_rejects_item_from_another_template(): void
    {
        $otherTemplate = ProcessTemplate::factory()->create();
        $otherStep = TemplateStep::factory()->create(['process_template_id' => $otherTemplate->id, 'step_number' => 1]);
        $foreignItem = TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $otherTemplate->id,
            'template_step_id' => $otherStep->id,
        ]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.checklist.toggle', [$this->batchStep, $foreignItem]))
            ->assertSessionHas('error');

        $this->assertSame(0, BatchStepChecklistCompletion::count());
    }

    public function test_required_checklist_item_blocks_step_completion(): void
    {
        $item = TemplateStepChecklistItem::factory()->required()->create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
            'label' => 'Safety lockout verified',
        ]);
        $service = app(\App\Services\WorkOrder\BatchService::class);

        // Blocked while the required item is unchecked.
        try {
            $service->completeStep($this->batchStep, $this->operator);
            $this->fail('Expected completion to be blocked by the required checklist item.');
        } catch (\Exception) {
            $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->batchStep->fresh()->status);
        }

        // Tick it off → the gate clears and the step completes.
        BatchStepChecklistCompletion::create([
            'batch_step_id' => $this->batchStep->id,
            'checklist_item_id' => $item->id,
            'checked_by_id' => $this->operator->id,
            'checked_at' => now(),
        ]);

        $service->completeStep($this->batchStep->fresh(), $this->operator);
        $this->assertSame(BatchStep::STATUS_DONE, $this->batchStep->fresh()->status);
    }

    public function test_media_streams_to_authenticated_user_with_safe_headers(): void
    {
        Storage::fake('local');
        $media = $this->media(TemplateStepMedia::TYPE_PDF);
        Storage::put($media->storage_path, '%PDF-1.4 fake');

        $this->actingAs($this->operator)
            ->get(route('process-templates.media.show', [$this->template, $media]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_guest_cannot_toggle_checklist_or_view_media(): void
    {
        $item = $this->checklistItem();
        $media = $this->media();

        $this->post(route('operator.batch-step.checklist.toggle', [$this->batchStep, $item]))->assertRedirect();
        $this->get(route('process-templates.media.show', [$this->template, $media]))->assertRedirect();
    }

    public function test_media_route_is_scoped_to_its_template(): void
    {
        $media = $this->media();
        $otherTemplate = ProcessTemplate::factory()->create();

        $this->actingAs($this->operator)
            ->get(route('process-templates.media.show', [$otherTemplate, $media]))
            ->assertNotFound();
    }
}
