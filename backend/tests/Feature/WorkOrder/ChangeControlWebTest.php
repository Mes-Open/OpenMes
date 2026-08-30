<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\ChangeRequestStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Models\WorkOrderStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Change control in the admin UI (#182) — the Inertia pages and the flash-based
 * transitions the work-order screen drives.
 */
class ChangeControlWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->workOrder = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 100,
            'produced_qty' => 35,
        ]);
    }

    public function test_work_order_page_carries_stops_change_requests_and_form_options(): void
    {
        WorkOrderStop::factory()->requiringChange()->create(['work_order_id' => $this->workOrder->id]);
        WorkOrderChangeRequest::factory()->create(['work_order_id' => $this->workOrder->id]);

        $this->actingAs($this->supervisor)
            ->get("/admin/work-orders/{$this->workOrder->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/Show')
                ->has('stops', 1)
                ->has('changeRequests', 1)
                ->where('changeControl.requires_change', true)
                ->has('changeControl.stop_types', 6)
                ->has('changeControl.effective_points', 3)
                ->where('workOrder.snapshot_version', 1)
            );
    }

    public function test_stopping_production_from_the_ui_flashes_and_holds_the_order(): void
    {
        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'ENGINEERING_CHANGE',
                'reason' => 'Revision must change.',
                'requires_change' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $this->workOrder->fresh()->status);
    }

    public function test_a_refused_stop_comes_back_as_an_error_flash_not_an_exception(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_PENDING]);

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'OPERATIONAL',
                'reason' => 'Too early.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only IN_PROGRESS work orders can be stopped.');
    }

    public function test_creating_a_change_request_redirects_to_its_review_page(): void
    {
        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/change-requests", [
                'title' => 'Raise the quantity',
                'reason' => 'Customer extended the order.',
                'proposed' => ['planned_qty' => 150],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $changeRequest = WorkOrderChangeRequest::where('work_order_id', $this->workOrder->id)->firstOrFail();
        $this->assertSame(ChangeRequestStatus::Draft, $changeRequest->status);
    }

    public function test_review_page_shows_the_diff_live_impact_and_permissions(): void
    {
        $changeRequest = WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $this->workOrder->id,
            'proposed' => ['planned_qty' => 150],
        ]);

        $this->actingAs($this->supervisor)
            ->get("/admin/work-order-change-requests/{$changeRequest->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/ChangeRequest')
                ->where('changeRequest.status', 'SUBMITTED')
                ->has('changeRequest.diff', 1)
                ->where('changeRequest.diff.0.field', 'planned_qty')
                ->where('changeRequest.live_impact.remaining_qty', 65)
                ->where('can.approve', true)
                ->etc()
            );
    }

    public function test_the_full_ui_workflow_holds_and_then_releases_the_order(): void
    {
        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'ENGINEERING_CHANGE',
                'reason' => 'Revision must change.',
                'requires_change' => true,
            ]);

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/change-requests", [
                'title' => 'Raise the quantity',
                'reason' => 'Customer extended the order.',
                'proposed' => ['planned_qty' => 150],
            ]);

        $cr = WorkOrderChangeRequest::where('work_order_id', $this->workOrder->id)->firstOrFail();

        // Resume is refused while the change has not been applied.
        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/resume")
            ->assertSessionHas('error');

        $this->actingAs($this->supervisor)->post("/admin/work-order-change-requests/{$cr->id}/submit");
        $this->actingAs($this->supervisor)->post("/admin/work-order-change-requests/{$cr->id}/approve");
        $this->actingAs($this->supervisor)
            ->post("/admin/work-order-change-requests/{$cr->id}/apply")
            ->assertSessionHas('success');

        $this->assertSame(ChangeRequestStatus::Applied, $cr->fresh()->status);
        $this->assertSame(2, $this->workOrder->fresh()->snapshot_version);

        // The page now offers the applied request for resume.
        $this->actingAs($this->supervisor)
            ->get("/admin/work-orders/{$this->workOrder->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('changeControl.applied_change_request_id', $cr->id)
                ->etc()
            );

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/resume", ['change_request_id' => $cr->id])
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_legacy_pause_and_resume_still_work_from_the_ui(): void
    {
        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/pause")
            ->assertSessionHas('success');

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/resume")
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
        $this->assertDatabaseCount('work_order_stops', 0);
    }

    /**
     * Every resume path goes through the stop service. A path that flipped the status
     * itself would leave the stop open forever — blocking the next stop and inflating
     * downtime — which is exactly what the supervisor screen used to do.
     */
    public function test_supervisor_resume_closes_the_open_stop_too(): void
    {
        $this->supervisor->lines()->attach($this->workOrder->line_id);

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'MATERIAL_SHORTAGE',
                'reason' => 'Waiting for steel.',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$this->workOrder->id}/resume")
            ->assertSessionHas('success');

        $stop = WorkOrderStop::where('work_order_id', $this->workOrder->id)->firstOrFail();
        $this->assertNotNull($stop->resumed_at, 'The supervisor screen must close the stop, not just flip the status.');
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->fresh()->status);
    }

    public function test_supervisor_cannot_resume_an_order_held_for_a_change(): void
    {
        $this->supervisor->lines()->attach($this->workOrder->line_id);

        $this->actingAs($this->supervisor)
            ->post("/admin/work-orders/{$this->workOrder->id}/stop", [
                'type' => 'ENGINEERING_CHANGE',
                'reason' => 'Revision must change.',
                'requires_change' => true,
            ]);

        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$this->workOrder->id}/resume")
            ->assertSessionHas('error');

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $this->workOrder->fresh()->status);
    }

    public function test_a_user_without_the_approval_permission_cannot_approve_from_the_ui(): void
    {
        $planner = User::factory()->create();
        $planner->givePermissionTo(['view work orders', 'edit work orders']);

        $changeRequest = WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $this->workOrder->id,
        ]);

        $this->actingAs($planner)
            ->post("/admin/work-order-change-requests/{$changeRequest->id}/approve")
            ->assertForbidden();
    }
}
