<?php

namespace Tests\Feature\Quality;

use App\Models\Batch;
use App\Models\Issue;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\QualityCheck;
use App\Models\QualityCheckTemplate;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\QualityCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An in-process quality check's verdict is decided on the server from the QC
 * template - a measurement within the parameter's min/max (inclusive), a
 * pass/fail parameter by its recorded answer - never taken from the client's
 * `is_passed`. The check passes only when every sample does.
 */
class QualityCheckVerdictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    private Line $line;

    private ProcessTemplate $process;

    private WorkOrder $workOrder;

    private Batch $batch;

    private QualityCheckTemplate $qcTemplate;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->line = Line::factory()->create();
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
        $this->supervisor = tap(User::factory()->create(), fn ($u) => $u->assignRole('Supervisor'));
        $this->operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
        $this->operator->lines()->attach($this->line->id);

        $this->process = ProcessTemplate::factory()->create();
        $this->workOrder = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'process_snapshot' => ['template_id' => $this->process->id],
        ]);
        $this->batch = Batch::factory()->inProgress()->create(['work_order_id' => $this->workOrder->id]);
        $this->qcTemplate = QualityCheckTemplate::create([
            'process_template_id' => $this->process->id,
            'name' => 'Moisture',
            'min_checks_per_batch' => 1,
            'samples_per_check' => 2,
            'parameters' => [
                ['name' => 'Moisture', 'type' => 'measurement', 'unit' => '%', 'min' => 5, 'max' => 10],
                ['name' => 'Seal intact', 'type' => 'pass_fail'],
            ],
        ]);
    }

    private function moisture(float $value, int $sample = 1, array $extra = []): array
    {
        return ['sample_number' => $sample, 'parameter_name' => 'Moisture', 'parameter_type' => 'measurement', 'value_numeric' => $value] + $extra;
    }

    private function seal(bool|int $value, int $sample = 1, array $extra = []): array
    {
        return ['sample_number' => $sample, 'parameter_name' => 'Seal intact', 'parameter_type' => 'pass_fail', 'value_boolean' => $value] + $extra;
    }

    /**
     * A complete check for the test template: both parameters for samples 1
     * and 2, with the given moisture values and seal answers.
     */
    private function grid(array $moisture = [7, 7], array $seal = [true, true], array $extra = []): array
    {
        return [
            $this->moisture($moisture[0], 1, $extra), $this->seal($seal[0], 1),
            $this->moisture($moisture[1], 2), $this->seal($seal[1], 2),
        ];
    }

    private function service(): QualityCheckService
    {
        return app(QualityCheckService::class);
    }

    private function apiUrl(): string
    {
        return "/api/v1/batches/{$this->batch->id}/quality-checks";
    }

    private function webUrl(): string
    {
        return route('operator.batch.quality-check', $this->batch);
    }

    private function asOperator()
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    // ── The verdict rule ─────────────────────────────────────────────────────

    public function test_measurements_within_limits_pass_and_limits_are_inclusive(): void
    {
        $check = $this->service()->performCheck($this->batch, $this->operator, [
            $this->moisture(5, 1), $this->seal(true, 1),
            $this->moisture(10, 2), $this->seal(true, 2),
        ], null, $this->qcTemplate);

        $this->assertTrue($check->all_passed);
        $this->assertTrue($check->samples->every(fn ($s) => $s->is_passed));
    }

    public function test_a_measurement_outside_the_limits_fails_the_check(): void
    {
        foreach ([4.99, 10.01] as $value) {
            $check = $this->service()->performCheck($this->batch, $this->operator, $this->grid([7, $value]), null, $this->qcTemplate);

            $this->assertFalse($check->all_passed, "Moisture $value % passed a 5-10 % limit.");
            $this->assertFalse($check->samples->where('sample_number', 2)->firstWhere('parameter_name', 'Moisture')->is_passed);
            $this->assertTrue($check->samples->where('sample_number', 1)->firstWhere('parameter_name', 'Moisture')->is_passed);
        }
    }

    public function test_a_failed_pass_fail_answer_fails_the_check(): void
    {
        $check = $this->service()->performCheck($this->batch, $this->operator, $this->grid([7, 7], [true, false]), null, $this->qcTemplate);

        $this->assertFalse($check->all_passed);
        $this->assertFalse($check->samples->where('sample_number', 2)->firstWhere('parameter_name', 'Seal intact')->is_passed);
    }

    public function test_an_open_limit_only_bounds_one_side(): void
    {
        $this->qcTemplate->update(['samples_per_check' => 1, 'parameters' => [['name' => 'Moisture', 'type' => 'measurement', 'min' => 5]]]);

        $this->assertTrue($this->service()->performCheck($this->batch, $this->operator, [$this->moisture(9999)], null, $this->qcTemplate)->all_passed);
        $this->assertFalse($this->service()->performCheck($this->batch, $this->operator, [$this->moisture(4)], null, $this->qcTemplate)->all_passed);
    }

    public function test_the_parameter_type_comes_from_the_template(): void
    {
        // Posted as pass_fail with is_passed=1: the template says it is measured.
        $samples = $this->grid();
        $samples[0] = ['sample_number' => 1, 'parameter_name' => 'Moisture', 'parameter_type' => 'pass_fail', 'value_numeric' => 42, 'value_boolean' => true, 'is_passed' => true];
        $check = $this->service()->performCheck($this->batch, $this->operator, $samples, null, $this->qcTemplate);

        $this->assertFalse($check->all_passed);
        $this->assertSame('measurement', $check->samples->first()->parameter_type);
    }

    public function test_without_a_template_only_pass_fail_samples_can_be_judged(): void
    {
        $check = $this->service()->performCheck($this->batch, $this->operator, [$this->seal(false)]);
        $this->assertFalse($check->all_passed);

        $this->expectException(ValidationException::class);
        $this->service()->performCheck($this->batch, $this->operator, [$this->moisture(7, 1, ['is_passed' => true])]);
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function test_api_ignores_the_client_verdict_for_an_out_of_limit_measurement(): void
    {
        $this->actingAs($this->operator, 'sanctum')
            ->postJson($this->apiUrl(), [
                'quality_check_template_id' => $this->qcTemplate->id,
                'samples' => $this->grid([42, 7], [true, true], ['is_passed' => true]),
            ])
            ->assertCreated()
            ->assertJsonPath('data.all_passed', false)
            ->assertJsonPath('data.samples.0.is_passed', false);
    }

    public function test_api_uses_the_batch_template_when_none_is_named(): void
    {
        $this->actingAs($this->operator, 'sanctum')
            ->postJson($this->apiUrl(), ['samples' => $this->grid([7, 7], [1, 1])])
            ->assertCreated()
            ->assertJsonPath('data.all_passed', true);

        $this->assertSame($this->qcTemplate->id, QualityCheck::sole()->quality_check_template_id);
    }

    public function test_api_refuses_a_parameter_not_in_the_template(): void
    {
        $this->actingAs($this->operator, 'sanctum')
            ->postJson($this->apiUrl(), ['samples' => [$this->moisture(7), [
                'sample_number' => 1, 'parameter_name' => 'Colour', 'parameter_type' => 'pass_fail', 'value_boolean' => true,
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('samples.1.parameter_name');

        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_refuses_samples_it_cannot_judge(): void
    {
        $cases = [
            'samples.0.value_numeric' => [['sample_number' => 1, 'parameter_name' => 'Moisture', 'parameter_type' => 'measurement', 'is_passed' => true]],
            'samples.0.value_boolean' => [['sample_number' => 1, 'parameter_name' => 'Seal intact', 'parameter_type' => 'pass_fail']],
            'samples.0.sample_number' => [$this->moisture(7, 3)],
            'samples.1.parameter_name' => [$this->moisture(7), $this->moisture(8)],
            'samples' => [],
        ];

        foreach ($cases as $field => $samples) {
            $this->actingAs($this->operator, 'sanctum')
                ->postJson($this->apiUrl(), ['samples' => $samples])
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }

        // An incomplete check: leaving out a (failing) value must not pass it.
        $this->actingAs($this->operator, 'sanctum')
            ->postJson($this->apiUrl(), ['samples' => [$this->moisture(7, 1), $this->seal(true, 1), $this->moisture(7, 2)]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['samples' => 'Missing: #2 Seal intact']);

        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_refuses_a_template_of_another_process(): void
    {
        $foreign = QualityCheckTemplate::create([
            'process_template_id' => ProcessTemplate::factory()->create()->id,
            'name' => 'Lenient',
            'parameters' => [['name' => 'Moisture', 'type' => 'measurement', 'min' => 0, 'max' => 100]],
        ]);

        $this->actingAs($this->operator, 'sanctum')
            ->postJson($this->apiUrl(), ['quality_check_template_id' => $foreign->id, 'samples' => [$this->moisture(42)]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quality_check_template_id');

        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_guest_is_unauthenticated(): void
    {
        $this->postJson($this->apiUrl(), ['samples' => [$this->moisture(7)]])->assertUnauthorized();
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_user_without_a_shop_floor_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson($this->apiUrl(), ['samples' => [$this->moisture(7)]])
            ->assertForbidden();
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_operator_off_the_batch_line_is_forbidden(): void
    {
        $outsider = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));
        $outsider->lines()->attach(Line::factory()->create()->id);

        $this->actingAs($outsider, 'sanctum')
            ->postJson($this->apiUrl(), ['samples' => [$this->moisture(7)]])
            ->assertForbidden();
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_api_supervisor_and_admin_may_record_on_any_line(): void
    {
        foreach ([$this->supervisor, $this->admin] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson($this->apiUrl(), ['samples' => $this->grid()])
                ->assertCreated();
        }
        $this->assertDatabaseCount('quality_checks', 2);
    }

    // ── Operator page ────────────────────────────────────────────────────────

    public function test_web_passing_check_is_recorded_as_passed(): void
    {
        $this->asOperator()->post($this->webUrl(), [
            'production_quantity' => 40,
            'samples' => $this->grid([6, 9], [1, 1]),
        ])->assertRedirect()->assertSessionHas('success');

        $check = QualityCheck::sole();
        $this->assertTrue($check->all_passed);
        $this->assertSame($this->qcTemplate->id, $check->quality_check_template_id);
        $this->assertCount(4, $check->samples);
    }

    public function test_web_failed_pass_fail_answer_is_recorded_as_failed(): void
    {
        // What the old operator form posted for a "Fail": value_boolean 0 with is_passed 1.
        $this->asOperator()->post($this->webUrl(), [
            'samples' => [$this->moisture(7, 1), $this->seal(0, 1, ['is_passed' => 1]), $this->moisture(7, 2), $this->seal(1, 2)],
        ])->assertSessionHas('warning');

        $check = QualityCheck::sole();
        $this->assertFalse($check->all_passed);
        $this->assertFalse($check->samples->where('sample_number', 1)->firstWhere('parameter_name', 'Seal intact')->is_passed);
    }

    public function test_web_refuses_invalid_samples(): void
    {
        $this->asOperator()->post($this->webUrl(), ['samples' => []])->assertSessionHasErrors('samples');
        $this->asOperator()->post($this->webUrl(), ['samples' => [[
            'sample_number' => 1, 'parameter_name' => 'Dimension', 'parameter_type' => 'measurement', 'value_numeric' => 1,
        ]]])->assertSessionHasErrors('samples.0.parameter_name');

        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_web_guest_is_redirected_to_login(): void
    {
        $this->post($this->webUrl(), ['samples' => [$this->seal(1)]])->assertRedirect(route('login'));
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_web_user_without_a_shop_floor_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['selected_line_id' => $this->line->id])
            ->post($this->webUrl(), ['samples' => [$this->seal(1)]])
            ->assertForbidden();
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_web_check_on_a_batch_of_another_line_is_forbidden(): void
    {
        $this->actingAs($this->operator)->withSession(['selected_line_id' => Line::factory()->create()->id])
            ->post($this->webUrl(), ['samples' => [$this->seal(1)]])
            ->assertForbidden();
        $this->assertDatabaseCount('quality_checks', 0);
    }

    public function test_operator_page_carries_the_batch_qc_template(): void
    {
        $this->asOperator()->get(route('operator.work-order.detail', $this->workOrder))
            ->assertInertia(fn ($page) => $page
                ->where('qualityCheckTemplate.id', $this->qcTemplate->id)
                ->where('qualityCheckTemplate.samples_per_check', 2)
                ->has('qualityCheckTemplate.parameters', 2));
    }

    // ── Quality control tasks keep raising a non-conformance on failure ──────

    public function test_failing_control_task_raises_a_non_conformance_despite_a_client_pass(): void
    {
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);
        $trigger = QualityControlTrigger::factory()->blocking()->create(['quality_check_template_id' => $this->qcTemplate->id]);
        $task = QualityControlTask::factory()->create([
            'quality_control_trigger_id' => $trigger->id,
            'batch_id' => $this->batch->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/quality-control-tasks/{$task->id}/perform", [
                'samples' => $this->grid([42, 7], [true, true], ['is_passed' => true]),
            ])
            ->assertOk()
            ->assertJsonPath('data.all_passed', false);

        $task->refresh();
        $this->assertNotNull($task->issue_id);
        $this->assertSame(Issue::SOURCE_IN_PROCESS, Issue::find($task->issue_id)->source);
        $this->assertSame(WorkOrder::STATUS_BLOCKED, $this->workOrder->fresh()->status);
    }
}
