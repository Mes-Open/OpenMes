<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\ScheduleChangeLog;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The planner's Changes tab: every schedule write is logged with before/after
 * placement snapshots, listed at GET /admin/schedule/changes, and reversible
 * via POST /admin/schedule/changes/{change}/undo (which restores the order's
 * primary placement AND its extra segments, and is itself logged).
 */
class SchedulePlannerChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function createWO(Line $line, array $attrs = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => Carbon::now()->startOfWeek()->addDay(),
        ], $attrs));
    }

    public function test_a_schedule_edit_is_logged_with_snapshots(): void
    {
        $lineA = Line::factory()->create(['is_active' => true]);
        $lineB = Line::factory()->create(['is_active' => true]);
        $wo = $this->createWO($lineA);
        $oldDue = $wo->due_date->format('Y-m-d');

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $lineB->id,
            'due_date' => Carbon::now()->startOfWeek()->addDays(3)->format('Y-m-d'),
        ])->assertOk();

        $log = ScheduleChangeLog::where('work_order_id', $wo->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('reschedule', $log->action);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($lineA->id, $log->before['line_id']);
        $this->assertSame($oldDue, $log->before['due_date']);
        $this->assertSame($lineB->id, $log->after['line_id']);
    }

    public function test_a_noop_edit_is_not_logged(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $wo = $this->createWO($line);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $line->id,
            'due_date' => $wo->due_date->format('Y-m-d'),
        ])->assertOk();

        $this->assertSame(0, ScheduleChangeLog::where('work_order_id', $wo->id)->count());
    }

    public function test_changes_endpoint_lists_latest_edits(): void
    {
        $lineA = Line::factory()->create(['is_active' => true]);
        $lineB = Line::factory()->create(['is_active' => true]);
        $wo = $this->createWO($lineA);

        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $lineB->id,
            'due_date' => $wo->due_date->format('Y-m-d'),
        ])->assertOk();

        $response = $this->actingAs($this->admin)->getJson('/admin/schedule/changes');
        $response->assertOk();
        $entry = collect($response->json('changes'))->firstWhere('work_order_id', $wo->id);
        $this->assertNotNull($entry);
        $this->assertSame($wo->order_no, $entry['order_no']);
        $this->assertSame('reschedule', $entry['action']);
        $this->assertNull($entry['undone_at']);
    }

    public function test_undo_restores_primary_and_extra_segments(): void
    {
        $lineA = Line::factory()->create(['is_active' => true]);
        $lineB = Line::factory()->create(['is_active' => true]);
        $monday = Carbon::now()->startOfWeek();
        $wo = $this->createWO($lineA, ['due_date' => $monday]);
        $wo->extraPlacements()->create(['line_id' => $lineB->id, 'due_date' => $monday->copy()->addDay(), 'shift_number' => 2]);

        // Edit: move primary and drop the segment.
        $this->actingAs($this->admin)->putJson("/admin/schedule/{$wo->id}", [
            'line_id' => $lineB->id,
            'due_date' => $monday->copy()->addDays(4)->format('Y-m-d'),
            'extra_placements' => [],
        ])->assertOk();

        $wo->refresh();
        $this->assertSame($lineB->id, $wo->line_id);
        $this->assertCount(0, $wo->extraPlacements);

        $log = ScheduleChangeLog::where('work_order_id', $wo->id)->latest('id')->first();
        $this->actingAs($this->admin)->postJson("/admin/schedule/changes/{$log->id}/undo")
            ->assertOk()->assertJsonPath('success', true);

        $wo->refresh();
        $this->assertSame($lineA->id, $wo->line_id);
        $this->assertSame($monday->format('Y-m-d'), $wo->due_date->format('Y-m-d'));
        $this->assertCount(1, $wo->extraPlacements);
        $this->assertSame($lineB->id, $wo->extraPlacements->first()->line_id);
        $this->assertSame(2, $wo->extraPlacements->first()->shift_number);

        // The undone entry is flagged and the undo itself was logged.
        $this->assertNotNull($log->fresh()->undone_at);
        $this->assertSame('undo', ScheduleChangeLog::where('work_order_id', $wo->id)->latest('id')->first()->action);
    }

    public function test_guest_and_operator_cannot_use_changes(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $wo = $this->createWO($line);
        $log = ScheduleChangeLog::create([
            'work_order_id' => $wo->id, 'action' => 'reschedule',
            'before' => ['line_id' => null], 'after' => ['line_id' => $line->id],
        ]);

        $this->getJson('/admin/schedule/changes')->assertUnauthorized();
        $this->postJson("/admin/schedule/changes/{$log->id}/undo")->assertUnauthorized();

        $this->actingAs($this->operator)->getJson('/admin/schedule/changes')->assertStatus(403);
        $this->actingAs($this->operator)->postJson("/admin/schedule/changes/{$log->id}/undo")->assertStatus(403);
    }
}
