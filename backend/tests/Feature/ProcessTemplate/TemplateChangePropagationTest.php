<?php

namespace Tests\Feature\ProcessTemplate;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepOutputValue;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepMedia;
use App\Models\TemplateStepOutput;
use App\Models\WorkOrder;
use App\Models\WorkOrderSnapshot;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Editing a process template (steps, checklist items, outputs, media):
 *  - orders already in progress keep what they were released with (snapshot),
 *  - orders not started yet take the edit (snapshot rebuilt as a new #182
 *    version, steps of their untouched batches regenerated),
 *  - new orders take the edited template.
 */
class TemplateChangePropagationTest extends TestCase
{
    use RefreshDatabase;
    use TemplateChangeFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTemplateChangeFixture();
    }

    // ---- what the snapshot holds ---------------------------------------------

    public function test_snapshot_freezes_checklist_outputs_and_media_per_step(): void
    {
        $step = $this->snapshotStep($this->running, 1);

        $this->assertSame($this->mix->id, $step['template_step_id']);
        $this->assertSame([$this->item->id], array_column($step['checklist_items'], 'id'));
        $this->assertTrue($step['checklist_items'][0]['is_required']);
        $this->assertSame('weight', $step['outputs'][0]['key']);
        $this->assertSame(TemplateStepOutput::TYPE_NUMBER, $step['outputs'][0]['value_type']);
        $this->assertSame([], $step['media']);
    }

    // ---- running orders keep their snapshot ----------------------------------

    public function test_required_checklist_item_added_later_does_not_block_running_order(): void
    {
        $this->actingAs($this->admin)->post("{$this->base}/checklist-items", [
            'template_step_id' => $this->mix->id, 'label' => 'Added later', 'is_required' => true,
        ])->assertRedirect();

        $this->assertSame(['Line clearance'], $this->runningStep->fresh()->pendingRequiredChecklistLabels()->all());

        $this->asOperator()->get("/operator/work-order/{$this->running->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('stepChecklists.1', 1)
                ->where('stepChecklists.1.0.label', 'Line clearance'));
    }

    public function test_required_output_deleted_later_still_gates_and_records_on_running_order(): void
    {
        $this->actingAs($this->admin)->delete("{$this->base}/outputs/{$this->weight->id}")->assertRedirect();

        $this->assertSame(['Weight'], $this->runningStep->fresh()->pendingRequiredOutputs()->all());

        $threw = false;
        try {
            app(BatchService::class)->completeStep($this->runningStep->fresh(), $this->operator);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'A running step completed without its released required output.');

        // The operator can still record it: the released definition stays valid.
        $this->asOperator()->post(route('operator.batch-step.outputs.record', [$this->runningStep, $this->weight->id]), ['value' => '12.5'])
            ->assertSessionHas('success');
        $this->assertSame(1, BatchStepOutputValue::where('output_id', $this->weight->id)->count());
        $this->assertSame([], $this->runningStep->fresh()->pendingRequiredOutputs()->all());
    }

    public function test_media_added_later_is_not_shown_on_running_order(): void
    {
        TemplateStepMedia::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id, 'title' => 'Revised',
        ]);

        $this->asOperator()->get("/operator/work-order/{$this->running->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('stepMedia.1'));
    }

    public function test_steps_added_or_removed_later_do_not_touch_running_order(): void
    {
        $before = $this->running->fresh()->process_snapshot;

        // (The SQLite test schema lost the partial unique index on step numbers, so
        // the step removed is the last one — no renumbering into a trashed slot.)
        $this->actingAs($this->admin)->post("{$this->base}/steps", ['name' => 'Label'])->assertRedirect();
        $this->assertCount(2, $this->running->fresh()->process_snapshot['steps']);
        $label = TemplateStep::where('process_template_id', $this->template->id)->where('name', 'Label')->firstOrFail();
        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$label->id}")->assertRedirect();
        $this->actingAs($this->admin)->post("{$this->base}/checklist-items", [
            'template_step_id' => $this->fill->id, 'label' => 'Seal', 'is_required' => true,
        ])->assertRedirect();

        $running = $this->running->fresh();
        $this->assertEquals($before, $running->process_snapshot);
        $this->assertSame(1, (int) ($running->snapshot_version ?? 1));
        $this->assertSame(['Mix', 'Fill'], $running->batches()->first()->steps()->orderBy('step_number')->pluck('name')->all());
    }

    public function test_a_started_step_counts_as_in_progress_even_when_the_order_status_lags(): void
    {
        $order = $this->order('WO-LAG');
        $batch = app(WorkOrderService::class)->createBatch($order, 5);
        $batch->steps()->where('step_number', 1)->update(['status' => BatchStep::STATUS_IN_PROGRESS]);
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);

        $this->actingAs($this->admin)->post("{$this->base}/steps", ['name' => 'Label'])->assertRedirect();

        $this->assertCount(2, $order->fresh()->process_snapshot['steps']);
        $this->assertSame(0, WorkOrderSnapshot::where('work_order_id', $order->id)->where('version', '>', 1)->count());
    }

    // ---- not-started orders take the edit ------------------------------------

    public function test_not_started_order_takes_an_added_required_checklist_item(): void
    {
        $this->actingAs($this->admin)->post("{$this->base}/checklist-items", [
            'template_step_id' => $this->mix->id, 'label' => 'Added later', 'is_required' => true,
        ])->assertRedirect();

        $this->assertSame(['Line clearance', 'Added later'], array_column($this->snapshotStep($this->notStarted, 1)['checklist_items'], 'label'));
        $this->assertSame(['Line clearance', 'Added later'], $this->pendingBatchStep($this->notStarted, 1)->pendingRequiredChecklistLabels()->all());
        $this->assertNewVersion($this->notStarted);
    }

    public function test_not_started_order_drops_a_deleted_output(): void
    {
        $this->actingAs($this->admin)->delete("{$this->base}/outputs/{$this->weight->id}")->assertRedirect();

        $this->assertSame([], $this->snapshotStep($this->notStarted, 1)['outputs']);
        $this->assertSame([], $this->pendingBatchStep($this->notStarted, 1)->pendingRequiredOutputs()->all());
        $this->assertNewVersion($this->notStarted);
    }

    public function test_not_started_order_takes_added_media(): void
    {
        $media = TemplateStepMedia::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id, 'title' => 'Revised',
        ]);

        $this->assertSame([$media->id], array_column($this->snapshotStep($this->notStarted, 1)['media'], 'id'));
        $this->asOperator()->get("/operator/work-order/{$this->notStarted->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stepMedia.1.0.title', 'Revised'));
        $this->assertNewVersion($this->notStarted);
    }

    public function test_not_started_order_takes_added_and_removed_steps_with_regenerated_batch_steps(): void
    {
        $this->actingAs($this->admin)->post("{$this->base}/steps", ['name' => 'Label'])->assertRedirect();

        $order = $this->notStarted->fresh();
        $this->assertSame(['Mix', 'Fill', 'Label'], array_column($order->process_snapshot['steps'], 'name'));
        $this->assertSame(['Mix', 'Fill', 'Label'], $order->batches()->first()->steps()->orderBy('step_number')->pluck('name')->all());
        $this->assertSame(2, (int) $order->snapshot_version);

        $label = TemplateStep::where('process_template_id', $this->template->id)->where('name', 'Label')->firstOrFail();
        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$label->id}")->assertRedirect();

        $order = $this->notStarted->fresh();
        $this->assertSame(['Mix', 'Fill'], array_column($order->process_snapshot['steps'], 'name'));
        $this->assertSame(['Mix', 'Fill'], $order->batches()->first()->steps()->orderBy('step_number')->pluck('name')->all());
        $this->assertSame(3, (int) $order->snapshot_version);
    }

    public function test_one_edit_request_appends_one_version(): void
    {
        // Deleting a step soft-deletes its checklist items and outputs too — several
        // row events, one request, one version.
        // Two rows written in one transaction are one edit as well.
        \Illuminate\Support\Facades\DB::transaction(function () {
            TemplateStepChecklistItem::factory()->create([
                'process_template_id' => $this->template->id, 'template_step_id' => $this->fill->id, 'label' => 'Seal',
            ]);
            TemplateStepOutput::create([
                'process_template_id' => $this->template->id, 'template_step_id' => $this->fill->id,
                'key' => 'fill_weight', 'label' => 'Fill weight', 'value_type' => TemplateStepOutput::TYPE_NUMBER,
            ]);
        });
        $this->assertSame(2, (int) $this->notStarted->fresh()->snapshot_version);

        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$this->fill->id}")->assertRedirect();

        $order = $this->notStarted->fresh();
        $this->assertSame(['Mix'], array_column($order->process_snapshot['steps'], 'name'));
        $this->assertSame(3, (int) $order->snapshot_version);
        $this->assertSame(3, WorkOrderSnapshot::where('work_order_id', $order->id)->count());
    }

    public function test_reordering_steps_reaches_not_started_orders(): void
    {
        $this->actingAs($this->admin)->postJson("{$this->base}/steps/reorder", ['order' => [$this->fill->id, $this->mix->id]])
            ->assertOk();

        $this->assertSame(['Fill', 'Mix'], array_column($this->notStarted->fresh()->process_snapshot['steps'], 'name'));
        $this->assertSame(['Mix', 'Fill'], array_column($this->running->fresh()->process_snapshot['steps'], 'name'));
    }

    public function test_new_order_takes_the_edited_template(): void
    {
        $this->actingAs($this->admin)->post("{$this->base}/checklist-items", [
            'template_step_id' => $this->mix->id, 'label' => 'Added later', 'is_required' => true,
        ]);

        $fresh = $this->order('WO-LATER');

        $this->assertSame(['Line clearance', 'Added later'], array_column($this->snapshotStep($fresh, 1)['checklist_items'], 'label'));
    }

    private function assertNewVersion(WorkOrder $order): void
    {
        $order = $order->fresh();
        $this->assertSame(2, (int) $order->snapshot_version);
        $version = WorkOrderSnapshot::where('work_order_id', $order->id)->where('version', 2)->firstOrFail();
        $this->assertSame(WorkOrderSnapshot::REASON_TEMPLATE_EDITED, $version->reason);
        $this->assertEquals($order->process_snapshot, $version->snapshot);
        $this->assertSame(1, WorkOrderSnapshot::where('work_order_id', $order->id)->where('version', 1)->count());
        $this->assertSame(2, (int) $order->batches()->first()->snapshot_version);
    }

    // ---- legacy orders, deletion safety, admin warning -----------------------

    public function test_legacy_snapshot_without_step_lists_reads_the_live_template(): void
    {
        $legacy = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'process_snapshot' => ['template_id' => $this->template->id, 'steps' => [['step_number' => 1, 'name' => 'Mix']]],
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $legacy->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1, 'status' => BatchStep::STATUS_IN_PROGRESS]);

        TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id,
            'label' => 'Live item', 'is_required' => true,
        ]);

        $this->assertSame(['Line clearance', 'Live item'], $step->pendingRequiredChecklistLabels()->all());
        $this->assertEquals(['template_id' => $this->template->id, 'steps' => [['step_number' => 1, 'name' => 'Mix']]], $legacy->fresh()->process_snapshot);
    }

    public function test_deleting_a_template_row_keeps_recorded_values(): void
    {
        $this->asOperator()->post(route('operator.batch-step.outputs.record', [$this->runningStep, $this->weight->id]), ['value' => '7'])
            ->assertSessionHas('success');
        $this->asOperator()->post(route('operator.batch-step.checklist.toggle', [$this->runningStep, $this->item->id]))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->delete("{$this->base}/outputs/{$this->weight->id}")->assertRedirect();
        $this->actingAs($this->admin)->delete("{$this->base}/checklist-items/{$this->item->id}")->assertRedirect();

        $this->assertSoftDeleted($this->weight);
        $this->assertSoftDeleted($this->item);
        $this->assertSame(1, BatchStepOutputValue::where('output_id', $this->weight->id)->count());
        $this->assertSame(1, $this->runningStep->checklistCompletions()->count());
        // Still the released record of the running step: nothing pending.
        $this->assertSame([], $this->runningStep->fresh()->pendingRequiredOutputs()->all());
        $this->assertSame([], $this->runningStep->fresh()->pendingRequiredChecklistLabels()->all());

        // A permanent delete of the row, its step or its template would cascade the
        // recorded values away: refused.
        foreach ([$this->weight->fresh(), $this->item->fresh(), $this->mix->fresh(), $this->template->fresh()] as $row) {
            try {
                $row->forceDelete();
                $this->fail(class_basename($row).' was permanently deleted with values recorded against it.');
            } catch (\DomainException) {
                // expected
            }
        }
        $this->assertSame(1, BatchStepOutputValue::where('output_id', $this->weight->id)->count());
    }

    public function test_template_rows_without_recorded_values_can_still_be_purged(): void
    {
        $spare = TemplateStepOutput::create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->fill->id,
            'key' => 'spare', 'label' => 'Spare', 'value_type' => TemplateStepOutput::TYPE_TEXT,
        ]);

        $spare->forceDelete();

        $this->assertDatabaseMissing('template_step_outputs', ['id' => $spare->id]);
    }

    public function test_admin_page_tells_in_progress_and_not_started_counts(): void
    {
        $this->actingAs($this->admin)->get($this->base)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('processTemplate.in_progress_work_order_count', 1)
                ->where('processTemplate.not_started_work_order_count', 1));
    }
}
