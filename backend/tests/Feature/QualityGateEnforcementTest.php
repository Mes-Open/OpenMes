<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepOutputValue;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepOutput;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end regression for #quality-gate: a recorded value that fails its
 * output's expected result must raise a blocking Issue and stop the *next*
 * BatchStep from starting — reusing WorkOrder::isBlocked()/BatchStep::canStart(),
 * not a new mechanism. Mirrors the manual browser test run against
 * WO-YB1-TEST-FAIL that discovered the gap this plan closes.
 */
class QualityGateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Batch $batch;

    private BatchStep $step1;

    private BatchStep $step2;

    private TemplateStepOutput $gatedOutput;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'web']);
        }
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        IssueType::create([
            'code' => 'IN_PROCESS_QC_FAIL', 'name' => 'In-process Quality — Control Failed',
            'severity' => 'HIGH', 'is_blocking' => true, 'is_active' => true,
        ]);

        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $step1Def = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'Voltage Test']);
        TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'Top Balance']);

        $this->gatedOutput = TemplateStepOutput::create([
            'process_template_id' => $template->id, 'template_step_id' => $step1Def->id,
            'key' => 'pack_voltage', 'label' => 'Pack Voltage (V)', 'value_type' => 'number',
            'is_required' => true, 'expected_min' => 3.20, 'expected_max' => 4.25,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id, 'product_type_id' => $productType->id,
            'process_snapshot' => ['template_id' => $template->id],
        ]);
        $this->batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);
        // step1 is already DONE so step2's prerequisitesMet() (previous step
        // DONE/SKIPPED) holds independently of the quality gate — isolating
        // what this test actually exercises: WorkOrder::isBlocked() via the
        // auto-raised Issue, not the unrelated sequential-step check. Recording
        // a BatchStepOutputValue against an already-DONE step is unconstrained
        // by design (see plan's "what this plan does not build").
        $this->step1 = BatchStep::factory()->create([
            'batch_id' => $this->batch->id, 'step_number' => 1, 'name' => 'Voltage Test',
            'status' => BatchStep::STATUS_DONE,
        ]);
        $this->step2 = BatchStep::factory()->create([
            'batch_id' => $this->batch->id, 'step_number' => 2, 'name' => 'Top Balance',
            'status' => BatchStep::STATUS_PENDING,
        ]);
    }

    public function test_an_out_of_range_value_raises_a_blocking_issue_and_stops_the_next_step(): void
    {
        $this->assertTrue($this->step2->fresh()->canStart(), 'sanity: next step is startable before any value is recorded');

        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 5.50, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $issue = Issue::where('batch_step_id', $this->step1->id)->first();
        $this->assertNotNull($issue, 'expected an Issue to be auto-raised');
        $this->assertSame('IN_PROCESS_QC_FAIL', $issue->issueType->code);
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertSame(Issue::SOURCE_IN_PROCESS, $issue->source);

        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->batch->fresh()->workOrder->status);
        $this->assertFalse($this->step2->fresh()->canStart(), 'the NEXT step must now be blocked');
    }

    public function test_an_in_range_value_raises_no_issue(): void
    {
        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 3.87, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $this->assertSame(0, Issue::where('batch_step_id', $this->step1->id)->count());
        $this->assertTrue($this->step2->fresh()->canStart());
    }

    public function test_resolving_the_issue_unblocks_the_next_step(): void
    {
        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $this->gatedOutput->id,
            'value_number' => 5.50, 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);
        $this->assertFalse($this->step2->fresh()->canStart());

        $issue = Issue::where('batch_step_id', $this->step1->id)->firstOrFail();
        app(IssueService::class)->resolveIssue($issue, 'Retested, voltage now in spec.');

        $this->assertTrue($this->step2->fresh()->canStart(), 'resolving the blocking issue must unblock the next step');
    }

    public function test_an_output_with_no_configured_criterion_behaves_exactly_as_before(): void
    {
        $ungated = TemplateStepOutput::create([
            'process_template_id' => $this->gatedOutput->process_template_id,
            'template_step_id' => $this->gatedOutput->template_step_id,
            'key' => 'note', 'label' => 'Note', 'value_type' => 'text',
        ]);

        BatchStepOutputValue::create([
            'batch_step_id' => $this->step1->id, 'output_id' => $ungated->id,
            'value_text' => 'anything at all', 'recorded_by_id' => $this->operator->id, 'recorded_at' => now(),
        ]);

        $this->assertSame(0, Issue::count());
        $this->assertTrue($this->step2->fresh()->canStart());
    }
}
