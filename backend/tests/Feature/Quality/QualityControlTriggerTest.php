<?php

namespace Tests\Feature\Quality;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\DowntimeReason;
use App\Models\Issue;
use App\Models\Line;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Lot\BatchReleaseService;
use App\Services\Production\DowntimeService;
use App\Services\Quality\QualityTriggerService;
use App\Services\WorkOrder\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configurable quality-control triggers (#105): each trigger type fires the
 * relevant control, results are recorded against the work order / machine, and
 * blocking controls gate production. Existing checks are untouched.
 */
class QualityControlTriggerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function service(): QualityTriggerService
    {
        return app(QualityTriggerService::class);
    }

    // ── Firing: in_production ────────────────────────────────────────────────

    public function test_in_production_trigger_fires_once_when_batch_enters_production(): void
    {
        $trigger = QualityControlTrigger::factory()->create(); // in_production, active
        $batch = Batch::factory()->inProgress()->create();

        $this->service()->fireInProduction($batch);
        $this->service()->fireInProduction($batch); // idempotent — no duplicate

        $tasks = QualityControlTask::where('quality_control_trigger_id', $trigger->id)->get();
        $this->assertCount(1, $tasks);
        $this->assertSame($batch->id, $tasks->first()->batch_id);
        $this->assertSame($batch->work_order_id, $tasks->first()->work_order_id);
        $this->assertSame(QualityControlTask::STATUS_DUE, $tasks->first()->status);
    }

    public function test_inactive_or_out_of_scope_triggers_do_not_fire(): void
    {
        QualityControlTrigger::factory()->inactive()->create();
        QualityControlTrigger::factory()->create(['line_id' => Line::factory()->create()->id]); // other line

        $batch = Batch::factory()->inProgress()->create(); // its own line

        $this->service()->fireInProduction($batch);

        $this->assertDatabaseCount('quality_control_tasks', 0);
    }

    // ── Firing: every_n_units ────────────────────────────────────────────────

    public function test_every_n_units_trigger_fires_one_task_per_threshold(): void
    {
        $trigger = QualityControlTrigger::factory()->everyNUnits(50)->create();
        $wo = WorkOrder::factory()->create(['produced_qty' => 120]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);

        $this->service()->fireForUnits($batch->fresh());
        $this->service()->fireForUnits($batch->fresh()); // idempotent at the same count

        // floor(120 / 50) = 2 controls due.
        $this->assertSame(2, QualityControlTask::where('quality_control_trigger_id', $trigger->id)->count());
    }

    // ── Firing: every_n_minutes (scheduler) ──────────────────────────────────

    public function test_every_n_minutes_trigger_fires_via_scheduler_command(): void
    {
        $trigger = QualityControlTrigger::factory()->everyNMinutes(10)->create();
        Batch::factory()->inProgress()->create();

        $this->artisan('quality:fire-due-triggers')->assertExitCode(0);
        $this->assertSame(1, QualityControlTask::where('quality_control_trigger_id', $trigger->id)->count());

        // Running again immediately stays within the window — no new task.
        $this->artisan('quality:fire-due-triggers')->assertExitCode(0);
        $this->assertSame(1, QualityControlTask::where('quality_control_trigger_id', $trigger->id)->count());
    }

    // ── Firing: after_downtime / after_setup ─────────────────────────────────

    /** A downtime check needs a batch to record against; seed one in progress. */
    private function inProgressBatchOnLine(Line $line): Batch
    {
        $wo = WorkOrder::factory()->create(['line_id' => $line->id]);

        return Batch::factory()->inProgress()->create(['work_order_id' => $wo->id]);
    }

    public function test_after_downtime_trigger_fires_when_unplanned_downtime_stops(): void
    {
        $line = Line::factory()->create();
        $batch = $this->inProgressBatchOnLine($line);
        $reason = DowntimeReason::factory()->create(['kind' => 'unplanned']);
        $trigger = QualityControlTrigger::factory()->afterDowntime()->create();

        $downtime = app(DowntimeService::class)->start($line, $reason->id, $this->admin);
        app(DowntimeService::class)->stop($downtime);

        $task = QualityControlTask::where('quality_control_trigger_id', $trigger->id)->first();
        $this->assertNotNull($task);
        $this->assertSame($line->id, $task->line_id);
        $this->assertSame($batch->id, $task->batch_id); // recordable against the running batch
    }

    public function test_after_setup_trigger_fires_when_changeover_downtime_stops(): void
    {
        $line = Line::factory()->create();
        $this->inProgressBatchOnLine($line);
        $reason = DowntimeReason::factory()->create(['kind' => 'changeover']);
        $setupTrigger = QualityControlTrigger::factory()->afterSetup()->create();
        $downtimeTrigger = QualityControlTrigger::factory()->afterDowntime()->create();

        $downtime = app(DowntimeService::class)->start($line, $reason->id, $this->admin);
        app(DowntimeService::class)->stop($downtime);

        // Changeover fires after_setup, not after_downtime.
        $this->assertSame(1, QualityControlTask::where('quality_control_trigger_id', $setupTrigger->id)->count());
        $this->assertSame(0, QualityControlTask::where('quality_control_trigger_id', $downtimeTrigger->id)->count());
    }

    public function test_planned_downtime_never_fires_a_control(): void
    {
        $line = Line::factory()->create();
        $this->inProgressBatchOnLine($line);
        $reason = DowntimeReason::factory()->create(['kind' => 'planned']);
        QualityControlTrigger::factory()->afterDowntime()->create();
        QualityControlTrigger::factory()->afterSetup()->create();

        $downtime = app(DowntimeService::class)->start($line, $reason->id, $this->admin);
        app(DowntimeService::class)->stop($downtime);

        $this->assertDatabaseCount('quality_control_tasks', 0);
    }

    public function test_after_downtime_does_not_fire_without_an_active_batch(): void
    {
        $line = Line::factory()->create(); // no in-progress batch
        $reason = DowntimeReason::factory()->create(['kind' => 'unplanned']);
        QualityControlTrigger::factory()->afterDowntime()->create();

        $downtime = app(DowntimeService::class)->start($line, $reason->id, $this->admin);
        app(DowntimeService::class)->stop($downtime);

        $this->assertDatabaseCount('quality_control_tasks', 0);
    }

    // ── Firing: roaming (manual) ─────────────────────────────────────────────

    public function test_roaming_control_can_be_raised_manually(): void
    {
        $trigger = QualityControlTrigger::factory()->roaming()->create();
        $batch = Batch::factory()->inProgress()->create();

        $this->actingAs($this->admin)
            ->post('/admin/quality-tasks', ['quality_control_trigger_id' => $trigger->id, 'batch_id' => $batch->id])
            ->assertRedirect();

        $task = QualityControlTask::where('quality_control_trigger_id', $trigger->id)->first();
        $this->assertNotNull($task);
        $this->assertSame($batch->id, $task->batch_id);
    }

    public function test_roaming_control_requires_a_batch(): void
    {
        $trigger = QualityControlTrigger::factory()->roaming()->create();

        $this->actingAs($this->admin)
            ->post('/admin/quality-tasks', ['quality_control_trigger_id' => $trigger->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('quality_control_tasks', 0);
    }

    public function test_non_roaming_trigger_cannot_be_raised_manually(): void
    {
        $trigger = QualityControlTrigger::factory()->create(); // in_production

        $this->actingAs($this->admin)
            ->post('/admin/quality-tasks', ['quality_control_trigger_id' => $trigger->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('quality_control_tasks', 0);
    }

    // ── Recording ────────────────────────────────────────────────────────────

    public function test_performing_a_task_records_a_quality_check_against_the_batch(): void
    {
        $batch = Batch::factory()->inProgress()->create();
        $task = QualityControlTask::factory()->create([
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
        ]);

        $samples = [
            ['sample_number' => 1, 'parameter_name' => 'Length', 'parameter_type' => 'measurement', 'value_numeric' => 9.9, 'is_passed' => true],
        ];

        $task = $this->service()->performTask($task->fresh(), $this->admin, $samples);

        $this->assertSame(QualityControlTask::STATUS_DONE, $task->status);
        $this->assertNotNull($task->quality_check_id);
        $this->assertNull($task->issue_id);
        $this->assertDatabaseHas('quality_checks', ['id' => $task->quality_check_id, 'batch_id' => $batch->id, 'all_passed' => true]);
    }

    public function test_failing_blocking_control_raises_a_blocking_issue_and_blocks_the_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::factory()->inProgress()->create(['work_order_id' => $wo->id]);
        $trigger = QualityControlTrigger::factory()->blocking()->create();
        $task = QualityControlTask::factory()->create([
            'quality_control_trigger_id' => $trigger->id,
            'batch_id' => $batch->id,
            'work_order_id' => $wo->id,
        ]);

        $samples = [
            ['sample_number' => 1, 'parameter_name' => 'Length', 'parameter_type' => 'measurement', 'value_numeric' => 50, 'is_passed' => false],
        ];

        $task = $this->service()->performTask($task->fresh(), $this->admin, $samples);

        $this->assertNotNull($task->issue_id);
        $this->assertDatabaseHas('quality_checks', ['id' => $task->quality_check_id, 'all_passed' => false]);
        $this->assertSame(WorkOrder::STATUS_BLOCKED, $wo->fresh()->status);
        $this->assertSame(Issue::SOURCE_IN_PROCESS, Issue::find($task->issue_id)->source);
    }

    // ── Blocking gate ────────────────────────────────────────────────────────

    public function test_open_blocking_control_blocks_starting_the_next_step(): void
    {
        $batch = Batch::factory()->inProgress()->create();
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'status' => BatchStep::STATUS_READY]);
        $trigger = QualityControlTrigger::factory()->blocking()->create();
        QualityControlTask::factory()->create(['quality_control_trigger_id' => $trigger->id, 'batch_id' => $batch->id]);

        $this->expectException(\Exception::class);
        app(BatchService::class)->startStep($step, $this->admin);
    }

    public function test_open_blocking_control_blocks_batch_release(): void
    {
        $batch = Batch::factory()->done()->create(['lot_number' => 'LOT-QC-1']);
        $trigger = QualityControlTrigger::factory()->blocking()->create();
        QualityControlTask::factory()->create(['quality_control_trigger_id' => $trigger->id, 'batch_id' => $batch->id]);

        $this->expectException(\RuntimeException::class);
        app(BatchReleaseService::class)->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);
    }

    public function test_non_blocking_control_does_not_block_release(): void
    {
        $batch = Batch::factory()->done()->create(['lot_number' => 'LOT-QC-2']);
        $trigger = QualityControlTrigger::factory()->create(); // not blocking
        QualityControlTask::factory()->create(['quality_control_trigger_id' => $trigger->id, 'batch_id' => $batch->id]);

        $released = app(BatchReleaseService::class)->release($batch, $this->admin, Batch::RELEASE_FOR_PRODUCTION);
        $this->assertNotNull($released->released_at);
    }

    // ── Admin CRUD + validation + auth ───────────────────────────────────────

    public function test_admin_can_create_a_trigger(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/quality-control-triggers', [
                'name' => 'In-process check',
                'trigger_type' => QualityControlTrigger::TYPE_IN_PRODUCTION,
                'is_blocking' => true,
            ])
            ->assertRedirect('/admin/quality-control-triggers');

        $this->assertDatabaseHas('quality_control_triggers', ['name' => 'In-process check', 'is_blocking' => true]);
    }

    public function test_frequency_trigger_requires_a_threshold(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/quality-control-triggers', [
                'name' => 'Every N',
                'trigger_type' => QualityControlTrigger::TYPE_EVERY_N_UNITS,
            ])
            ->assertSessionHasErrors('threshold_n');
    }

    public function test_guest_cannot_manage_triggers(): void
    {
        $this->post('/admin/quality-control-triggers', [])->assertStatus(302);
        $this->get('/admin/quality-control-triggers')->assertStatus(302);
    }

    public function test_non_admin_cannot_manage_triggers(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get('/admin/quality-control-triggers')->assertForbidden();
        $this->actingAs($operator)->post('/admin/quality-control-triggers', [
            'name' => 'X',
            'trigger_type' => QualityControlTrigger::TYPE_IN_PRODUCTION,
        ])->assertForbidden();
    }

    // ── Regression: existing per-batch quality check still works ──────────────

    public function test_manual_quality_check_still_works(): void
    {
        $batch = Batch::factory()->inProgress()->create();

        $check = app(\App\Services\Production\QualityCheckService::class)->performCheck(
            $batch,
            $this->admin,
            [['sample_number' => 1, 'parameter_name' => 'Weight', 'parameter_type' => 'measurement', 'value_numeric' => 5, 'is_passed' => true]],
        );

        $this->assertTrue($check->all_passed);
        $this->assertDatabaseHas('quality_checks', ['id' => $check->id, 'batch_id' => $batch->id]);
    }
}
