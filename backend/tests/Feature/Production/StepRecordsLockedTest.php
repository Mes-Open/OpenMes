<?php

namespace Tests\Feature\Production;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepChecklistCompletion;
use App\Models\BatchStepOutputValue;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepOutput;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A step's checklist ticks and typed output values are its execution record:
 * the operator writes them only while the step is IN_PROGRESS. Once the step is
 * DONE or SKIPPED - or before it has started - a tick can't be added or removed
 * and a value can't be recorded or overwritten.
 */
class StepRecordsLockedTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKED = 'Checklist and outputs can only be recorded while the step is in progress.';

    private User $operator;

    private Line $line;

    private ProcessTemplate $template;

    private TemplateStep $templateStep;

    private BatchStep $batchStep;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->line = Line::factory()->create();
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        $productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $this->templateStep = TemplateStep::factory()->create([
            'process_template_id' => $this->template->id, 'step_number' => 1, 'name' => 'Mix',
        ]);
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $productType->id,
            'process_snapshot' => ['template_id' => $this->template->id],
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);
        $this->batchStep = BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 1, 'name' => 'Mix',
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
    }

    private function markDone(): void
    {
        $this->batchStep->update([
            'status' => BatchStep::STATUS_DONE,
            'completed_at' => now()->subHour(),
            'completed_by_id' => $this->operator->id,
        ]);
    }

    private function checklistItem(): TemplateStepChecklistItem
    {
        return TemplateStepChecklistItem::create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
            'label' => 'Scale tared', 'is_required' => true, 'sort_order' => 1,
        ]);
    }

    private function outputDef(array $attrs = []): TemplateStepOutput
    {
        return TemplateStepOutput::create(array_merge([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
            'key' => 'output_weight', 'label' => 'Weight', 'value_type' => 'number', 'is_required' => true,
        ], $attrs));
    }

    private function tick(TemplateStepChecklistItem $item): BatchStepChecklistCompletion
    {
        return BatchStepChecklistCompletion::create([
            'batch_step_id' => $this->batchStep->id, 'checklist_item_id' => $item->id,
            'checked_by_id' => $this->operator->id, 'checked_at' => now()->subHours(2),
        ]);
    }

    private function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    private function toggleUrl(TemplateStepChecklistItem $item): string
    {
        return route('operator.batch-step.checklist.toggle', [$this->batchStep, $item]);
    }

    private function outputUrl(TemplateStepOutput $output): string
    {
        return route('operator.batch-step.outputs.record', [$this->batchStep, $output]);
    }

    // ── Running step: still writable ─────────────────────────────────────────

    public function test_running_step_checklist_and_output_are_recorded(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();

        $this->asOperator()->post($this->toggleUrl($item))->assertSessionHas('success');
        $this->asOperator()->post($this->outputUrl($output), ['value' => 12.5])->assertSessionHas('success');

        $this->assertDatabaseHas('batch_step_checklist_completions', [
            'batch_step_id' => $this->batchStep->id, 'checklist_item_id' => $item->id,
        ]);
        $this->assertDatabaseHas('batch_step_output_values', [
            'batch_step_id' => $this->batchStep->id, 'output_id' => $output->id, 'value_number' => 12.5,
        ]);
    }

    public function test_running_step_output_rejects_an_invalid_value(): void
    {
        $output = $this->outputDef();

        $this->asOperator()->postJson($this->outputUrl($output), ['value' => 'heavy'])
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->asOperator()->post($this->outputUrl($output), [])->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('batch_step_output_values', ['output_id' => $output->id]);
    }

    // ── Done step: locked ────────────────────────────────────────────────────

    public function test_done_step_checklist_tick_cannot_be_removed(): void
    {
        $item = $this->checklistItem();
        $tick = $this->tick($item);
        $this->markDone();

        $this->asOperator()->post($this->toggleUrl($item))
            ->assertRedirect()->assertSessionHas('error', self::LOCKED);

        $this->assertNotSoftDeleted('batch_step_checklist_completions', ['id' => $tick->id]);
    }

    public function test_done_step_checklist_item_cannot_be_ticked(): void
    {
        $item = $this->checklistItem();
        $this->markDone();

        $this->asOperator()->post($this->toggleUrl($item))->assertSessionHas('error', self::LOCKED);

        $this->assertDatabaseMissing('batch_step_checklist_completions', ['checklist_item_id' => $item->id]);
    }

    public function test_done_step_output_value_cannot_be_overwritten(): void
    {
        $output = $this->outputDef();
        $original = BatchStepOutputValue::create([
            'batch_step_id' => $this->batchStep->id, 'output_id' => $output->id,
            'value_number' => 10, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now()->subHours(2),
        ]);
        $this->markDone();

        $this->asOperator()->post($this->outputUrl($output), ['value' => 99])
            ->assertRedirect()->assertSessionHas('error', self::LOCKED);

        $live = BatchStepOutputValue::where('output_id', $output->id)->get();
        $this->assertCount(1, $live);
        $this->assertSame($original->id, $live->first()->id);
        $this->assertEquals(10, (float) $live->first()->value_number);
        $this->assertSame(0, BatchStepOutputValue::onlyTrashed()->count());
    }

    public function test_done_step_output_value_cannot_be_recorded(): void
    {
        $output = $this->outputDef();
        $this->markDone();

        $this->asOperator()->post($this->outputUrl($output), ['value' => 99])->assertSessionHas('error', self::LOCKED);

        $this->assertDatabaseMissing('batch_step_output_values', ['output_id' => $output->id]);
    }

    public function test_locked_step_answers_json_callers_with_422(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();
        $this->markDone();

        $this->asOperator()->postJson($this->toggleUrl($item))
            ->assertStatus(422)->assertJson(['message' => self::LOCKED]);
        $this->asOperator()->postJson($this->outputUrl($output), ['value' => 99])
            ->assertStatus(422)->assertJson(['message' => self::LOCKED]);

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_locked_step_refuses_an_invalid_value_with_the_lock_message(): void
    {
        $output = $this->outputDef();
        $this->markDone();

        $this->asOperator()->post($this->outputUrl($output), ['value' => 'not a number'])
            ->assertSessionHas('error', self::LOCKED)
            ->assertSessionDoesntHaveErrors('value');
        $this->asOperator()->postJson($this->outputUrl($output), [])
            ->assertStatus(422)->assertJson(['message' => self::LOCKED])->assertJsonMissingValidationErrors('value');
    }

    public function test_picture_is_discarded_when_the_step_completes_before_the_write(): void
    {
        Storage::fake('local');
        $output = $this->outputDef(['key' => 'output_photo', 'label' => 'Photo', 'value_type' => 'picture']);

        // The step is running when the request is checked, and done by the time
        // the write re-reads it under the row lock (a completion in between).
        $reads = 0;
        BatchStep::retrieved(function (BatchStep $step) use (&$reads) {
            if (++$reads > 1) {
                $step->status = BatchStep::STATUS_DONE;
            }
        });

        $this->asOperator()->post($this->outputUrl($output), ['value' => UploadedFile::fake()->image('qc.jpg', 64, 64)])
            ->assertSessionHas('error', self::LOCKED);

        $this->assertSame(2, $reads, 'The step should be read once by route binding and once under the lock.');
        $this->assertDatabaseCount('batch_step_output_values', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ── Other non-running states ─────────────────────────────────────────────

    public function test_skipped_step_records_are_locked(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();
        $this->batchStep->update(['status' => BatchStep::STATUS_SKIPPED]);

        $this->asOperator()->post($this->toggleUrl($item))->assertSessionHas('error', self::LOCKED);
        $this->asOperator()->post($this->outputUrl($output), ['value' => 1])->assertSessionHas('error', self::LOCKED);

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_step_not_started_yet_records_are_locked(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();

        foreach ([BatchStep::STATUS_PENDING, BatchStep::STATUS_READY] as $status) {
            $this->batchStep->update(['status' => $status]);

            $this->asOperator()->post($this->toggleUrl($item))->assertSessionHas('error', self::LOCKED);
            $this->asOperator()->post($this->outputUrl($output), ['value' => 1])->assertSessionHas('error', self::LOCKED);
        }

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_records_locked_follows_the_step_status(): void
    {
        $this->assertFalse($this->batchStep->recordsLocked());

        foreach ([BatchStep::STATUS_PENDING, BatchStep::STATUS_READY, BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED] as $status) {
            $this->batchStep->status = $status;
            $this->assertTrue($this->batchStep->recordsLocked(), $status);
        }
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_cannot_toggle_or_record(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();

        $this->post($this->toggleUrl($item))->assertRedirect(route('login'));
        $this->post($this->outputUrl($output), ['value' => 1])->assertRedirect(route('login'));

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_user_without_an_operator_role_cannot_toggle_or_record(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->withSession(['selected_line_id' => $this->line->id])
            ->post($this->toggleUrl($item))->assertForbidden();
        $this->actingAs($outsider)->withSession(['selected_line_id' => $this->line->id])
            ->post($this->outputUrl($output), ['value' => 1])->assertForbidden();

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_operator_on_another_line_cannot_toggle_or_record(): void
    {
        $item = $this->checklistItem();
        $output = $this->outputDef();
        $otherLine = Line::factory()->create();

        $this->actingAs($this->operator)->withSession(['selected_line_id' => $otherLine->id])
            ->post($this->toggleUrl($item))->assertSessionHas('error');
        $this->actingAs($this->operator)->withSession(['selected_line_id' => $otherLine->id])
            ->post($this->outputUrl($output), ['value' => 1])->assertSessionHas('error');

        $this->assertDatabaseCount('batch_step_checklist_completions', 0);
        $this->assertDatabaseCount('batch_step_output_values', 0);
    }
}
