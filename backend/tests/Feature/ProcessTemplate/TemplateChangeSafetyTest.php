<?php

namespace Tests\Feature\ProcessTemplate;

use App\Enums\ScheduledOperationStatus;
use App\Jobs\RefreshNotStartedOrders;
use App\Models\BatchStep;
use App\Models\BatchStepDocument;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ScheduledOperation;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepMedia;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSnapshot;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Edge cases of carrying template edits to not-started orders: what a refresh
 * must keep, what must not trigger it, and who may still see deleted media.
 */
class TemplateChangeSafetyTest extends TestCase
{
    use RefreshDatabase;
    use TemplateChangeFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTemplateChangeFixture();
    }

    private function addChecklistItem(int $stepId, string $label = 'Added later'): void
    {
        $this->actingAs($this->admin)->post("{$this->base}/checklist-items", [
            'template_step_id' => $stepId, 'label' => $label, 'is_required' => true,
        ])->assertRedirect();
    }

    // ---- what triggers a refresh ---------------------------------------------

    public function test_bom_edit_reaches_not_started_order_only(): void
    {
        $runningBom = $this->running->fresh()->process_snapshot['bom'];
        $line = BomItem::factory()->create(['process_template_id' => $this->template->id]);

        $order = $this->notStarted->fresh();
        $this->assertSame(2, (int) $order->snapshot_version);
        $this->assertContains($line->material_id, array_column($order->process_snapshot['bom'], 'material_id'));
        $this->assertEquals($runningBom, $this->running->fresh()->process_snapshot['bom']);
    }

    public function test_a_rolled_back_edit_reaches_no_order(): void
    {
        try {
            DB::transaction(function () {
                TemplateStepChecklistItem::factory()->create([
                    'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id, 'label' => 'Never',
                ]);
                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, (int) ($this->notStarted->fresh()->snapshot_version ?? 1));
        $this->assertSame(0, WorkOrderSnapshot::where('version', '>', 1)->count());
    }

    public function test_an_edit_queues_one_unique_refresh_per_template_after_commit(): void
    {
        // A step delete cascades to the step's checklist item and output: three
        // row events, one queued refresh.
        TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->fill->id,
        ]);
        Queue::fake();
        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$this->fill->id}")->assertRedirect();

        Queue::assertPushedTimes(RefreshNotStartedOrders::class, 1);
        Queue::assertPushed(RefreshNotStartedOrders::class, fn ($job) => $job->templateId === $this->template->id);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, new RefreshNotStartedOrders($this->template->id));
        $this->assertSame((string) $this->template->id, (new RefreshNotStartedOrders($this->template->id))->uniqueId());
    }

    public function test_a_refresh_that_keeps_failing_is_logged(): void
    {
        $job = new RefreshNotStartedOrders($this->template->id);
        $this->assertGreaterThan(1, $job->tries);
        Log::spy();

        $job->failed(new \RuntimeException('database gone'));

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => str_contains($message, 'template')
            && $context['process_template_id'] === $this->template->id)->once();
    }

    public function test_one_failing_order_does_not_keep_the_next_ones_on_the_old_template(): void
    {
        $later = $this->order('WO-NEW-2');
        app(\App\Services\WorkOrder\WorkOrderService::class)->createBatch($later, 10);
        $this->assertGreaterThan($this->notStarted->id, $later->id);

        Queue::fake();
        BomItem::factory()->create(['process_template_id' => $this->template->id]);

        // The first order (lowest id) cannot take its new version.
        WorkOrderSnapshot::creating(function (WorkOrderSnapshot $version) {
            if ($version->work_order_id === $this->notStarted->id) {
                throw new \RuntimeException('bad data on this order');
            }
        });
        Log::spy();

        try {
            app(\App\Services\ProcessTemplate\ProcessTemplateChangePropagator::class)->refreshNotStarted($this->template->fresh());
            $this->fail('the failure was swallowed, so the queued job would not retry it');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString((string) $this->notStarted->id, $e->getMessage());
        }

        $this->assertSame(2, (int) $later->fresh()->snapshot_version, 'the order after the failing one was still moved');
        $this->assertSame(1, (int) $this->notStarted->fresh()->snapshot_version);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => ($context['work_order_id'] ?? null) === $this->notStarted->id)->once();
    }

    public function test_api_reorder_reaches_not_started_orders(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/process-templates/{$this->template->id}/steps/reorder", [
            'step_ids' => [$this->fill->id, $this->mix->id],
        ])->assertOk();

        $this->assertSame(['Fill', 'Mix'], array_column($this->notStarted->fresh()->process_snapshot['steps'], 'name'));
        $this->assertSame(['Mix', 'Fill'], array_column($this->running->fresh()->process_snapshot['steps'], 'name'));
    }

    // ---- what a refresh keeps ------------------------------------------------

    public function test_regeneration_keeps_planning_assignments_and_documents_of_surviving_steps(): void
    {
        $oldFill = $this->pendingBatchStep($this->notStarted, 2);
        $workstation = Workstation::factory()->create(['line_id' => $this->line->id]);
        $oldFill->update(['workstation_id' => $workstation->id, 'assigned_by_id' => $this->admin->id, 'assigned_at' => now()]);
        $operation = ScheduledOperation::create([
            'work_order_id' => $this->notStarted->id, 'batch_step_id' => $oldFill->id, 'step_number' => 2,
            'step_name' => 'Fill', 'status' => ScheduledOperationStatus::Scheduled,
        ]);
        $document = BatchStepDocument::factory()->create(['batch_step_id' => $oldFill->id]);

        // Reorder: Fill becomes step 1 — carried over by the template step, not the number.
        $this->actingAs($this->admin)->postJson("{$this->base}/steps/reorder", ['order' => [$this->fill->id, $this->mix->id]])
            ->assertOk();

        $newFill = $this->pendingBatchStep($this->notStarted, 1);
        $this->assertSame('Fill', $newFill->name);
        $this->assertNotSame($oldFill->id, $newFill->id);
        $this->assertSame($workstation->id, $newFill->workstation_id);
        $this->assertSame($this->admin->id, $newFill->assigned_by_id);
        $this->assertSame($newFill->id, $operation->fresh()->batch_step_id);
        $this->assertSame(1, $operation->fresh()->step_number);
        $this->assertSame($newFill->id, $document->fresh()->batch_step_id);
        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_regeneration_drops_planning_of_removed_steps_with_a_log_line(): void
    {
        $oldFill = $this->pendingBatchStep($this->notStarted, 2);
        $operation = ScheduledOperation::create([
            'work_order_id' => $this->notStarted->id, 'batch_step_id' => $oldFill->id, 'step_number' => 2,
            'step_name' => 'Fill', 'status' => ScheduledOperationStatus::Scheduled,
        ]);
        Log::spy();

        $this->actingAs($this->admin)->delete("{$this->base}/steps/{$this->fill->id}")->assertRedirect();

        $this->assertModelMissing($operation);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context = []) => str_contains($message, 'no longer in the process')
            && ($context['batch_step_id'] ?? null) === $oldFill->id);
    }

    // ---- operator side -------------------------------------------------------

    public function test_starting_a_step_that_was_regenerated_meanwhile_asks_for_a_reload(): void
    {
        $stale = $this->pendingBatchStep($this->notStarted, 1);
        $this->addChecklistItem($this->mix->id);
        $this->assertSoftDeleted($stale);

        $this->asOperator()->from('/operator/queue')
            ->post(route('operator.batch-step.start', $stale->id))
            ->assertRedirect('/operator/queue')
            ->assertSessionHas('error', __('The process of this order was updated. Reload the page.'));

        $this->asOperator()->postJson(route('operator.batch-step.start', $stale->id))
            ->assertStatus(409);
    }

    public function test_checklist_anti_idor_against_trashed_items(): void
    {
        // Trashed, but released with this order on this step: accepted.
        $this->actingAs($this->admin)->delete("{$this->base}/checklist-items/{$this->item->id}")->assertRedirect();
        $this->asOperator()->post(route('operator.batch-step.checklist.toggle', [$this->runningStep, $this->item->id]))
            ->assertSessionHas('success');

        // Trashed and from another template: rejected.
        $other = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'version' => 2, 'is_active' => false]);
        $foreign = TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $other->id,
            'template_step_id' => TemplateStep::factory()->create(['process_template_id' => $other->id, 'step_number' => 1])->id,
        ]);
        $foreign->delete();
        $this->asOperator()->post(route('operator.batch-step.checklist.toggle', [$this->runningStep, $foreign->id]))
            ->assertSessionHas('error');

        // Trashed and from this template, but another step: rejected.
        $otherStep = TemplateStepChecklistItem::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->fill->id,
        ]);
        $otherStep->delete();
        $this->asOperator()->post(route('operator.batch-step.checklist.toggle', [$this->runningStep, $otherStep->id]))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->runningStep->checklistCompletions()->count());
    }

    public function test_toggle_and_record_need_an_operator(): void
    {
        $toggle = route('operator.batch-step.checklist.toggle', [$this->runningStep, $this->item->id]);
        $record = route('operator.batch-step.outputs.record', [$this->runningStep, $this->weight->id]);

        $this->post($toggle)->assertRedirect(route('login'));
        $this->post($record, ['value' => 1])->assertRedirect(route('login'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post($toggle)->assertForbidden();
        $this->actingAs($stranger)->post($record, ['value' => 1])->assertForbidden();

        $this->assertSame(0, $this->runningStep->checklistCompletions()->count());
    }

    // ---- deleted media -------------------------------------------------------

    private function mediaOnMix(): TemplateStepMedia
    {
        Storage::fake();
        $media = TemplateStepMedia::factory()->create([
            'process_template_id' => $this->template->id, 'template_step_id' => $this->mix->id,
        ]);
        Storage::put($media->storage_path, 'jpeg-bytes');

        return $media;
    }

    private function mediaUrl(TemplateStepMedia $media): string
    {
        return route('process-templates.media.show', [$this->template->id, $media->id]);
    }

    public function test_deleted_media_stays_visible_to_an_operator_of_an_order_released_with_it(): void
    {
        $media = $this->mediaOnMix();
        // Released with the media, then started; the template drops it afterwards.
        $order = $this->order('WO-MEDIA');
        app(\App\Services\WorkOrder\WorkOrderService::class)->createBatch($order, 1)
            ->steps()->where('step_number', 1)->update(['status' => BatchStep::STATUS_IN_PROGRESS]);
        $order->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $media->delete();

        $this->asOperator()->get($this->mediaUrl($media))->assertOk();

        // An operator of another line cannot reach that order, so not the media either.
        $elsewhere = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
        $elsewhere->lines()->attach(Line::factory()->create()->id);
        $this->actingAs($elsewhere)->get($this->mediaUrl($media))->assertNotFound();
    }

    public function test_deleted_media_no_order_was_released_with_is_gone(): void
    {
        $media = $this->mediaOnMix();
        // Not-started orders were moved onto the version without it, the running
        // order was released before it existed.
        $media->delete();

        $this->asOperator()->get($this->mediaUrl($media))->assertNotFound();
        $this->actingAs($this->admin)->get($this->mediaUrl($media))->assertNotFound();
    }

    // ---- admin warning -------------------------------------------------------

    public function test_admin_page_counts_orders_using_the_template_only_as_an_extra_bom(): void
    {
        $primary = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'version' => 2, 'is_active' => false]);
        TemplateStep::factory()->create(['process_template_id' => $primary->id, 'step_number' => 1]);
        app(\App\Services\WorkOrder\WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-MULTI', 'line_id' => $this->line->id, 'product_type_id' => $this->productType->id,
            'planned_qty' => 1, 'bom_template_ids' => [$primary->id, $this->template->id],
        ]);

        $this->actingAs($this->admin)->get($this->base)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('processTemplate.in_progress_work_order_count', 1)
                ->where('processTemplate.not_started_work_order_count', 1)
                ->where('processTemplate.secondary_bom_work_order_count', 1)
                ->missing('processTemplate.active_work_order_count'));
    }
}
