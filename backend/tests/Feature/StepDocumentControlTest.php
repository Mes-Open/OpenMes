<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepDocument;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Shop-floor document control: a step with an unvalidated mandatory document
 * cannot be completed; once validated (who/when recorded) it proceeds.
 */
class StepDocumentControlTest extends TestCase
{
    use RefreshDatabase;

    private BatchService $service;

    private Line $line;

    private WorkOrder $workOrder;

    private Batch $batch;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->service = app(BatchService::class);
        $this->line = Line::factory()->create();
        $this->workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        $this->batch = app(WorkOrderService::class)->createBatch($this->workOrder, 50);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        // On the work order's line, so line/policy-scoped actions are authorized.
        $this->operator->lines()->attach($this->line);
    }

    /** First step of the batch, forced IN_PROGRESS so completion is reachable. */
    private function inProgressStep(): BatchStep
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();
        $step->update(['status' => BatchStep::STATUS_IN_PROGRESS, 'started_at' => now()->subMinutes(10)]);

        return $step->fresh();
    }

    public function test_step_with_unvalidated_mandatory_document_cannot_be_completed(): void
    {
        $step = $this->inProgressStep();
        BatchStepDocument::factory()->create([
            'batch_step_id' => $step->id,
            'name' => 'Assembly SOP',
        ]);

        $this->expectException(\Exception::class);

        try {
            $this->service->completeStep($step, $this->operator);
        } finally {
            // The step must remain IN_PROGRESS - it did not pass the gate.
            $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
        }
    }

    public function test_step_completes_once_the_document_is_validated(): void
    {
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id, 'name' => 'Assembly SOP']);

        $doc->markValidated($this->operator);

        $this->service->completeStep($step->fresh(), $this->operator);

        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_optional_document_does_not_block_completion(): void
    {
        $step = $this->inProgressStep();
        BatchStepDocument::factory()->optional()->create(['batch_step_id' => $step->id]);

        $this->service->completeStep($step, $this->operator);

        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_validation_records_who_and_when(): void
    {
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id]);

        $doc->markValidated($this->operator);

        $fresh = $doc->fresh();
        $this->assertNotNull($fresh->validated_at);
        $this->assertSame($this->operator->id, $fresh->validated_by_id);
        $this->assertTrue($fresh->isValidated());
    }

    public function test_operator_validates_document_via_web_route_then_completes(): void
    {
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id, 'name' => 'Assembly SOP']);

        // Blocked while unvalidated: completing flashes an error, step stays put.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.complete', $step))
            ->assertSessionHas('error');
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);

        // Validate the document.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step-document.validate', $doc))
            ->assertSessionHas('success');
        $this->assertSame($this->operator->id, $doc->fresh()->validated_by_id);

        // Now completion goes through.
        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->post(route('operator.batch-step.complete', $step))
            ->assertSessionHas('success');
        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_validate_rejects_a_document_from_another_line(): void
    {
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id]);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id + 999])
            ->post(route('operator.batch-step-document.validate', $doc))
            ->assertSessionHas('error');
        $this->assertNull($doc->fresh()->validated_at);
    }

    public function test_api_supervisor_can_attach_document(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $step = $this->inProgressStep();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/documents", [
                'name' => 'Assembly SOP',
                'reference' => 'SOP-42',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Assembly SOP');

        $this->assertDatabaseHas('batch_step_documents', [
            'batch_step_id' => $step->id,
            'name' => 'Assembly SOP',
            'is_mandatory' => true,
            'requires_validation' => true,
        ]);
    }

    public function test_api_operator_cannot_attach_document(): void
    {
        $step = $this->inProgressStep();

        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/documents", ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_api_validate_document_records_validation(): void
    {
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id]);

        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batch-step-documents/{$doc->id}/validate")
            ->assertOk();

        $this->assertSame($this->operator->id, $doc->fresh()->validated_by_id);
    }

    public function test_api_validate_document_is_forbidden_for_an_unrelated_user(): void
    {
        // An operator not assigned to the work order's line cannot clear the gate
        // by document id (IDOR guard on the API endpoint).
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create(['batch_step_id' => $step->id]);
        $outsider = User::factory()->create();
        $outsider->assignRole('Operator');

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/batch-step-documents/{$doc->id}/validate")
            ->assertForbidden();

        $this->assertNull($doc->fresh()->validated_at);
    }

    public function test_operator_can_stream_a_document_file(): void
    {
        Storage::fake('local');
        $step = $this->inProgressStep();
        $doc = BatchStepDocument::factory()->create([
            'batch_step_id' => $step->id,
            'file_path' => 'batch-step-documents/sop.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::put($doc->file_path, '%PDF-1.4 fake');

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get(route('operator.batch-step-document.file', $doc))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_attach_endpoint_requires_a_name(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $step = $this->inProgressStep();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/batch-steps/{$step->id}/documents", ['reference' => 'no-name'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
