<?php

namespace Tests\Feature\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order a quality hold keeps from going on — an open blocking quality
 * control or a blocking issue — is not placed by the finite scheduler, and
 * says why. Non-blocking controls, finished ones and resolved issues do not
 * hold anything.
 */
class QualityHoldSchedulingTest extends TestCase
{
    use RefreshDatabase;
    use SchedulesFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
    }

    private function control(WorkOrder $order, bool $blocking = true, array $attrs = []): QualityControlTask
    {
        $trigger = QualityControlTrigger::factory()->create(['name' => 'First-article check', 'is_blocking' => $blocking]);

        return QualityControlTask::factory()->create($attrs + [
            'quality_control_trigger_id' => $trigger->id,
            'work_order_id' => $order->id,
            'line_id' => $order->line_id,
        ]);
    }

    public function test_open_blocking_control_holds_the_order_and_names_the_control(): void
    {
        $line = $this->line();
        $held = $this->order($line, [['estimated_duration_minutes' => 60], ['estimated_duration_minutes' => 60]]);
        $free = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $task = $this->control($held);

        $result = $this->schedule();
        $ops = $this->opsFor($result, $held);

        $this->assertCount(2, $ops);
        foreach ($ops as $op) {
            $this->assertSame(ScheduledOperationStatus::InfeasibleQuality, $op['status']);
            $this->assertNull($op['planned_start_at']);
            $this->assertSame('quality', $op['reasons'][0]['code']);
            $this->assertSame($task->id, $op['reasons'][0]['holds'][0]['id']);
            $this->assertStringContainsString('First-article check', $op['reasons'][0]['message']);
        }
        // The held order leaves its slot to the next one.
        $this->assertEquals($this->at('2026-10-12 06:00'), $this->opsFor($result, $free)[0]['planned_start_at']);

        $summary = collect($result['work_orders'])->firstWhere('work_order_id', $held->id);
        $this->assertTrue($summary['quality_hold']);
        $this->assertFalse($summary['feasible']);
        $this->assertSame(2, $result['counts']['infeasible_quality']);
    }

    public function test_switching_quality_off_plans_the_held_order(): void
    {
        $line = $this->line();
        $order = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $this->control($order);

        $op = $this->opsFor($this->schedule(['respectQuality' => false]), $order)[0];

        $this->assertSame(ScheduledOperationStatus::Scheduled, $op['status']);
    }

    public function test_non_blocking_done_and_skipped_controls_hold_nothing(): void
    {
        $line = $this->line();
        $a = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $b = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $c = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $this->control($a, blocking: false);
        $this->control($b, attrs: ['status' => QualityControlTask::STATUS_DONE, 'completed_at' => now()]);
        $this->control($c, attrs: ['status' => QualityControlTask::STATUS_SKIPPED, 'completed_at' => now()]);

        $result = $this->schedule();

        foreach ([$a, $b, $c] as $order) {
            $this->assertTrue($this->opsFor($result, $order)[0]['status']->isScheduled());
        }
    }

    public function test_blocking_issue_holds_the_order_until_resolved(): void
    {
        $line = $this->line();
        $order = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $issue = Issue::factory()->create([
            'work_order_id' => $order->id,
            'issue_type_id' => IssueType::factory()->create(['is_blocking' => true])->id,
            'title' => 'Burr on the flange',
            'blocks_work_order' => true,
        ]);
        // The issue may have moved the order to BLOCKED; the hold is about the issue.
        $order->update(['status' => WorkOrder::STATUS_PENDING]);

        $op = $this->opsFor($this->schedule(), $order)[0];
        $this->assertSame(ScheduledOperationStatus::InfeasibleQuality, $op['status']);
        $this->assertSame('blocking_issue', $op['reasons'][0]['holds'][0]['kind']);
        $this->assertStringContainsString('Burr on the flange', $op['reasons'][0]['message']);

        $issue->update(['status' => Issue::STATUS_RESOLVED]);
        $this->assertTrue($this->opsFor($this->schedule(), $order)[0]['status']->isScheduled());
    }

    public function test_material_shortage_wins_over_a_quality_hold(): void
    {
        $line = $this->line();
        $material = \App\Models\Material::factory()->create(['stock_quantity' => 0]);
        $order = $this->order($line, [['estimated_duration_minutes' => 60]]);
        $order->update(['process_snapshot' => array_merge($order->process_snapshot, ['bom' => [[
            'material_id' => $material->id, 'material_code' => $material->code, 'quantity_per_unit' => 1, 'scrap_percentage' => 0,
        ]]])]);
        $this->control($order);

        $result = $this->schedule();

        $this->assertSame(ScheduledOperationStatus::InfeasibleMaterial, $this->opsFor($result, $order)[0]['status']);
        $summary = collect($result['work_orders'])->firstWhere('work_order_id', $order->id);
        $this->assertTrue($summary['material_constrained']);
        $this->assertFalse($summary['quality_hold']);
    }

    public function test_a_running_step_stays_pinned_and_only_later_steps_are_held(): void
    {
        $line = $this->line();
        $order = $this->order($line, [['estimated_duration_minutes' => 60], ['estimated_duration_minutes' => 60]], ['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::factory()->create(['work_order_id' => $order->id, 'status' => Batch::STATUS_IN_PROGRESS, 'target_qty' => 10]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 10, 'name' => 'Step 1', 'status' => BatchStep::STATUS_IN_PROGRESS, 'started_at' => $this->monday->copy()->subMinutes(10)]);
        BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 20, 'name' => 'Step 2', 'status' => BatchStep::STATUS_PENDING]);
        $this->control($order, attrs: ['batch_id' => $batch->id]);

        $ops = $this->opsFor($this->schedule(['scope' => \App\Services\Schedule\Finite\SchedulingRequest::SCOPE_ALL]), $order);

        $this->assertTrue($ops[0]['status']->isScheduled());
        $this->assertSame(ScheduledOperationStatus::InfeasibleQuality, $ops[1]['status']);
    }

    public function test_the_simulate_form_passes_respect_quality(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post('/admin/schedule/simulations', ['horizon_days' => 7, 'respect_quality' => false])
            ->assertRedirect('/admin/schedule/auto');
        $this->assertFalse(\App\Models\ScheduleSimulation::latest('id')->first()->parameters['respect_quality']);

    }
}
