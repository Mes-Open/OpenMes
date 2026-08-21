<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepOutputValue;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepOutput;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Typed operator outputs (#B): definitions on a template step, resolved live at
 * the operator workstation, recorded per batch step, with required ones gating
 * step completion. Mirrors the checklist sub-system tests.
 */
class StepTypedOutputsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Line $line;

    private ProductType $productType;

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
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        $this->line = Line::factory()->create();
        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id]);
        $this->templateStep = TemplateStep::factory()->create([
            'process_template_id' => $this->template->id, 'step_number' => 1, 'name' => 'Assemble',
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'process_snapshot' => ['template_id' => $this->template->id],
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $this->workOrder->id]);
        $this->batchStep = BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 1, 'name' => 'Assemble',
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
    }

    private function def(array $attrs = []): TemplateStepOutput
    {
        return TemplateStepOutput::create(array_merge([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->templateStep->id,
            'key' => 'output_torque', 'label' => 'Torque', 'value_type' => 'number', 'is_required' => true,
        ], $attrs));
    }

    private function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    // ── Admin authoring ──────────────────────────────────────────────────────

    public function test_admin_adds_a_typed_output_definition(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->admin)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id,
            'key' => 'output_qcpic', 'label' => 'QC photo', 'value_type' => 'picture', 'is_required' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('template_step_outputs', [
            'process_template_id' => $this->template->id, 'key' => 'output_qcpic', 'value_type' => 'picture',
        ]);
    }

    public function test_select_output_requires_options(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->admin)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id,
            'key' => 'grade', 'label' => 'Grade', 'value_type' => 'select',
        ])->assertSessionHasErrors('options');
    }

    public function test_operator_cannot_author_outputs(): void
    {
        $base = "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";

        $this->actingAs($this->operator)->post("{$base}/outputs", [
            'template_step_id' => $this->templateStep->id, 'key' => 'x', 'label' => 'X', 'value_type' => 'text',
        ])->assertForbidden();
    }

    // ── Completion gate ──────────────────────────────────────────────────────

    public function test_required_output_blocks_step_completion(): void
    {
        $output = $this->def();
        $service = app(\App\Services\WorkOrder\BatchService::class);

        try {
            $service->completeStep($this->batchStep, $this->operator);
            $this->fail('Expected completion to be blocked by the required output.');
        } catch (\Exception) {
            $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->batchStep->fresh()->status);
        }

        BatchStepOutputValue::create([
            'batch_step_id' => $this->batchStep->id, 'output_id' => $output->id,
            'value_number' => 5, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $service->completeStep($this->batchStep->fresh(), $this->operator);
        $this->assertSame(BatchStep::STATUS_DONE, $this->batchStep->fresh()->status);
    }

    // ── Operator recording ───────────────────────────────────────────────────

    public function test_operator_view_carries_step_outputs(): void
    {
        $this->def(['label' => 'Torque']);

        $this->asOperator()
            ->get(route('operator.work-order.detail', $this->workOrder))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->component('operator/WorkOrderDetail')
                ->has('stepOutputs.1', 1)
                ->where('stepOutputs.1.0.label', 'Torque'));
    }

    public function test_operator_records_a_number_output(): void
    {
        $output = $this->def(['value_type' => 'number']);

        $this->asOperator()
            ->post("/operator/batch-step/{$this->batchStep->id}/outputs/{$output->id}", ['value' => '4.2'])
            ->assertRedirect();

        $value = BatchStepOutputValue::firstWhere('output_id', $output->id);
        $this->assertEqualsWithDelta(4.2, (float) $value->value_number, 0.0001);
        $this->assertSame($this->operator->id, $value->recorded_by_id);
    }

    public function test_operator_uploads_a_picture_output_and_it_serves_back(): void
    {
        $output = $this->def(['key' => 'output_qcpic', 'value_type' => 'picture']);
        $file = UploadedFile::fake()->image('qc.jpg', 640, 480);

        $this->asOperator()
            ->post("/operator/batch-step/{$this->batchStep->id}/outputs/{$output->id}", ['value' => $file])
            ->assertRedirect()->assertSessionHas('success');

        $value = BatchStepOutputValue::firstWhere('output_id', $output->id);
        $this->assertNotNull($value->file_path);
        $this->assertTrue(Storage::exists($value->file_path));

        // Serves inline over the authenticated operator endpoint.
        $this->asOperator()
            ->get("/operator/batch-step-output/{$value->id}/file")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_non_image_upload_to_a_picture_output_is_rejected(): void
    {
        $output = $this->def(['key' => 'output_qcpic', 'value_type' => 'picture']);
        $file = UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf');

        $this->asOperator()
            ->post("/operator/batch-step/{$this->batchStep->id}/outputs/{$output->id}", ['value' => $file])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseCount('batch_step_output_values', 0);
    }

    public function test_recording_is_idempotent_overwrite(): void
    {
        $output = $this->def(['value_type' => 'number']);
        $url = "/operator/batch-step/{$this->batchStep->id}/outputs/{$output->id}";

        $this->asOperator()->post($url, ['value' => '1'])->assertRedirect();
        $this->asOperator()->post($url, ['value' => '2'])->assertRedirect();

        // One live value (the latest); the prior is soft-deleted (audit).
        $this->assertSame(1, BatchStepOutputValue::where('output_id', $output->id)->count());
        $this->assertEqualsWithDelta(2.0, (float) BatchStepOutputValue::firstWhere('output_id', $output->id)->value_number, 0.0001);
    }

    public function test_read_api_returns_recorded_outputs_for_a_work_order(): void
    {
        $num = $this->def(['key' => 'output_torque', 'value_type' => 'number']);
        BatchStepOutputValue::create([
            'batch_step_id' => $this->batchStep->id, 'output_id' => $num->id,
            'value_number' => 5.5, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        // The read API is for privileged/ERP callers — grant the view ability.
        \Spatie\Permission\Models\Permission::findOrCreate('view work orders', 'web');
        $this->admin->givePermissionTo('view work orders');

        $token = $this->admin->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/work-orders/{$this->workOrder->id}/step-outputs")
            ->assertOk()
            ->assertJsonPath('data.0.key', 'output_torque')
            ->assertJsonPath('data.0.value', 5.5)
            ->assertJsonPath('data.0.step_number', 1);
    }

    public function test_output_from_another_template_is_rejected(): void
    {
        $otherTemplate = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id, 'version' => 2]);
        $otherStep = TemplateStep::factory()->create(['process_template_id' => $otherTemplate->id, 'step_number' => 1]);
        $stranger = TemplateStepOutput::create([
            'process_template_id' => $otherTemplate->id, 'template_step_id' => $otherStep->id,
            'key' => 'x', 'label' => 'X', 'value_type' => 'number',
        ]);

        $this->asOperator()
            ->post("/operator/batch-step/{$this->batchStep->id}/outputs/{$stranger->id}", ['value' => '1'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('batch_step_output_values', 0);
    }
}
