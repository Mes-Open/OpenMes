<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The change-request list (Production → Change requests) and how a request raised by
 * an ERP API key reads in the UI.
 */
class ChangeRequestListWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected WorkOrder $workOrder;

    protected ApiKey $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->workOrder = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->key = ApiKey::factory()->create(['name' => 'ERP test key']);
    }

    private function erpCancellation(): WorkOrderChangeRequest
    {
        return WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $this->workOrder->id,
            'type' => ChangeRequestType::Cancellation,
            'proposed' => [],
            'requested_by_id' => null,
            'requested_by_api_key_id' => $this->key->id,
        ]);
    }

    public function test_supervisor_sees_the_list_with_the_api_key_as_requester(): void
    {
        $erp = $this->erpCancellation();
        WorkOrderChangeRequest::factory()->create(['work_order_id' => $this->workOrder->id]);

        $this->actingAs($this->supervisor)
            ->get('/admin/work-order-change-requests')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/ChangeRequests')
                ->has('changeRequests.data', 2)
                ->where('changeRequests.data.1.id', $erp->id)
                ->where('changeRequests.data.1.requested_by', 'API key: ERP test key')
                ->where('changeRequests.data.1.requested_via_api', true)
                ->where('changeRequests.data.1.type', 'CANCEL')
                ->where('basePath', '/admin/work-order-change-requests')
            );
    }

    public function test_the_list_is_also_mounted_in_the_supervisor_section(): void
    {
        $this->erpCancellation();

        $this->actingAs($this->supervisor)
            ->get('/supervisor/work-order-change-requests')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/ChangeRequests')
                ->has('changeRequests.data', 1)
                ->where('basePath', '/supervisor/work-order-change-requests')
            );
    }

    public function test_open_requests_come_first_and_can_be_filtered(): void
    {
        $rejected = WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $this->workOrder->id,
            'status' => ChangeRequestStatus::Rejected,
        ]);
        $open = $this->erpCancellation();

        $this->actingAs($this->supervisor)
            ->get('/admin/work-order-change-requests')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('changeRequests.data.0.id', $open->id)
                ->where('changeRequests.data.1.id', $rejected->id)
            );

        $this->actingAs($this->supervisor)
            ->get('/admin/work-order-change-requests?status=OPEN')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('changeRequests.data', 1)
                ->where('changeRequests.data.0.id', $open->id)
            );

        $this->actingAs($this->supervisor)
            ->get('/admin/work-order-change-requests?status=BOGUS')
            ->assertSessionHasErrors('status');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/work-order-change-requests')->assertRedirect('/login');
    }

    public function test_operator_cannot_open_the_list(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get('/admin/work-order-change-requests')->assertForbidden();
        $this->actingAs($operator)->get('/supervisor/work-order-change-requests')->assertForbidden();
    }

    public function test_review_page_shows_the_api_key_as_requester_and_the_cancellation_type(): void
    {
        $cr = $this->erpCancellation();

        $this->actingAs($this->supervisor)
            ->get("/admin/work-order-change-requests/{$cr->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/ChangeRequest')
                ->where('changeRequest.requested_by', 'API key: ERP test key')
                ->where('changeRequest.requested_via_api', true)
                ->where('changeRequest.type', 'CANCEL')
                ->where('changeRequest.diff.0.field', 'status')
                ->where('changeRequest.diff.0.to', WorkOrder::STATUS_CANCELLED)
                ->where('can.approve', true)
            );
    }

    public function test_requester_label_survives_a_revoked_key(): void
    {
        $cr = $this->erpCancellation();
        $this->key->delete();

        $this->assertSame('API key: ERP test key', $cr->fresh()->requesterLabel());
    }

    public function test_a_user_without_the_approval_permission_cannot_approve_an_erp_request(): void
    {
        $cr = $this->erpCancellation();
        $planner = User::factory()->create();
        $planner->givePermissionTo(['view work orders', 'edit work orders', 'tab:orders']);

        $this->actingAs($planner)
            ->post("/admin/work-order-change-requests/{$cr->id}/approve")
            ->assertForbidden();

        $this->assertSame(ChangeRequestStatus::Submitted, $cr->fresh()->status);
    }
}
