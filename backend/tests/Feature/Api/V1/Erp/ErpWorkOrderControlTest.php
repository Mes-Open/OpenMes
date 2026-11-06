<?php

namespace Tests\Feature\Api\V1\Erp;

use App\Enums\ApiScope;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Models\ApiKey;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-B: the ERP reads its order back by number, cancels it, and learns from the API
 * what a manager decided about the requests it raised for orders in production.
 */
class ErpWorkOrderControlTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private ApiKey $key;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->tenantId = Tenant::create(['name' => 'ERP Tenant'])->id;
        app(TenantContext::class)->set($this->tenantId);

        Line::factory()->create(['code' => 'L1']);
        ProductType::factory()->create(['code' => 'P1']);

        [$this->key, $this->secret] = $this->issueKey([ApiScope::OrdersImport, ApiScope::ProductionRead]);
    }

    /** @return array{0: ApiKey, 1: string} */
    private function issueKey(array $scopes, string $name = 'ERP test key'): array
    {
        return ApiKey::issue([
            'name' => $name,
            'scopes' => array_map(fn (ApiScope $s) => $s->value, $scopes),
            'tenant_id' => $this->tenantId,
        ]);
    }

    private function order(string $status, array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'order_no' => 'ERP/2026/1',
            'line_id' => Line::where('code', 'L1')->value('id'),
            'product_type_id' => ProductType::where('code', 'P1')->value('id'),
            'planned_qty' => 100,
            'status' => $status,
            'tenant_id' => $this->tenantId,
        ], $overrides));
    }

    private function api(?string $secret = null)
    {
        return $this->withHeader('X-Api-Key', $secret ?? $this->secret);
    }

    private function supervisor(): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $user->assignRole('Supervisor');

        return $user;
    }

    // ── Reading an order back ────────────────────────────────────────────────

    public function test_show_by_id_returns_the_id_and_change_requests(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);

        $this->api()->getJson("/api/v1/erp/work-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_no', 'ERP/2026/1')
            ->assertJsonPath('data.change_requests', []);
    }

    public function test_show_by_order_number_accepts_slashes(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);

        $this->api()->getJson('/api/v1/erp/work-orders/ERP%2F2026%2F1?by=order_no')
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_show_by_order_number_does_not_reach_another_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        app(TenantContext::class)->set($otherTenant);
        WorkOrder::factory()->create(['order_no' => 'FOREIGN-1', 'tenant_id' => $otherTenant]);
        app(TenantContext::class)->set($this->tenantId);

        $this->api()->getJson('/api/v1/erp/work-orders/FOREIGN-1?by=order_no')->assertNotFound();
    }

    public function test_show_answers_404_for_unknown_number_and_non_numeric_id(): void
    {
        $this->order(WorkOrder::STATUS_PENDING);

        $this->api()->getJson('/api/v1/erp/work-orders/NOPE?by=order_no')->assertNotFound();
        $this->api()->getJson('/api/v1/erp/work-orders/ERP%2F2026%2F1')->assertNotFound();
    }

    public function test_show_rejects_an_unknown_lookup_with_422(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);

        $this->api()->getJson("/api/v1/erp/work-orders/{$order->id}?by=xyz")
            ->assertStatus(422)
            ->assertJsonValidationErrors('by');
    }

    // ── Cancelling ──────────────────────────────────────────────────────────

    public function test_cancel_of_a_pending_order_cancels_at_once_with_audit_and_reason(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);

        $this->api()->postJson('/api/v1/erp/work-orders/ERP%2F2026%2F1/cancel?by=order_no', ['reason' => 'Customer withdrew'])
            ->assertOk()
            ->assertJsonPath('data.result', 'cancelled')
            ->assertJsonPath('data.work_order.status', WorkOrder::STATUS_CANCELLED);

        $fresh = $order->fresh();
        $this->assertSame(WorkOrder::STATUS_CANCELLED, $fresh->status);
        $this->assertSame('Customer withdrew', $fresh->extra_data['cancellation_reason']);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => WorkOrder::class,
            'entity_id' => $order->id,
            'action' => 'updated',
        ]);
        $this->assertSame(0, WorkOrderChangeRequest::count());
    }

    public function test_cancel_of_an_accepted_order_cancels_at_once(): void
    {
        $order = $this->order(WorkOrder::STATUS_ACCEPTED);

        $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.result', 'cancelled');

        $this->assertSame(WorkOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_cancel_of_an_in_progress_order_raises_a_cancellation_request(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);

        $response = $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel", ['reason' => 'Customer withdrew'])
            ->assertStatus(202)
            ->assertJsonPath('data.result', 'change_requested')
            ->assertJsonPath('data.change_request.type', 'CANCEL')
            ->assertJsonPath('data.change_request.status', 'SUBMITTED')
            ->assertJsonPath('data.change_request.origin', 'api_key');

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $cr = WorkOrderChangeRequest::findOrFail($response->json('data.change_request_id'));
        $this->assertSame(ChangeRequestType::Cancellation, $cr->type);
        $this->assertSame($this->key->id, $cr->requested_by_api_key_id);
        $this->assertSame('Customer withdrew', $cr->reason);

        // Asking again returns the same request instead of a second one.
        $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")
            ->assertStatus(202)
            ->assertJsonPath('data.change_request_id', $cr->id);
        $this->assertSame(1, WorkOrderChangeRequest::count());
    }

    public function test_an_approved_and_applied_cancellation_cancels_the_order(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);
        $sibling = WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $order->id,
            'tenant_id' => $this->tenantId,
        ]);

        $id = $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->json('data.change_request_id');

        $supervisor = $this->supervisor();
        $this->actingAs($supervisor)
            ->post("/admin/work-order-change-requests/{$id}/approve")
            ->assertSessionHas('success');
        $this->actingAs($supervisor)
            ->post("/admin/work-order-change-requests/{$id}/apply")
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_CANCELLED, $order->fresh()->status);

        $cr = WorkOrderChangeRequest::find($id);
        $this->assertSame(ChangeRequestStatus::Applied, $cr->status);
        $this->assertSame(['status' => WorkOrder::STATUS_IN_PROGRESS], $cr->previous_values);
        $this->assertSame($supervisor->id, $cr->applied_by_id);

        // The other open request on the cancelled order is dead.
        $this->assertSame(ChangeRequestStatus::Cancelled, $sibling->fresh()->status);
    }

    public function test_a_rejected_cancellation_leaves_the_order_running_and_the_erp_sees_why(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);
        $id = $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->json('data.change_request_id');

        $this->actingAs($this->supervisor())
            ->post("/admin/work-order-change-requests/{$id}/reject", ['reason' => 'Material already cut'])
            ->assertSessionHas('success');

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        // The API-key request is stateless; drop the web session before calling it.
        $this->app['auth']->forgetGuards();

        $this->api()->getJson("/api/v1/erp/work-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.change_requests.0.id', $id)
            ->assertJsonPath('data.change_requests.0.status', 'REJECTED')
            ->assertJsonPath('data.change_requests.0.rejection_reason', 'Material already cut')
            ->assertJsonPath('data.change_requests.0.type', 'CANCEL');

        $this->assertNotNull(
            $this->api()->getJson('/api/v1/erp/change-requests')->json('data.0.decided_at')
        );
    }

    public function test_applying_a_cancellation_on_an_order_held_for_a_change_is_refused(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);
        $id = $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->json('data.change_request_id');

        $supervisor = $this->supervisor();
        $this->actingAs($supervisor)->post("/admin/work-order-change-requests/{$id}/approve");
        $order->update(['status' => WorkOrder::STATUS_CHANGE_HOLD]);

        $this->actingAs($supervisor)
            ->post("/admin/work-order-change-requests/{$id}/apply")
            ->assertSessionHas('error');

        $this->assertSame(WorkOrder::STATUS_CHANGE_HOLD, $order->fresh()->status);
        $this->assertSame(ChangeRequestStatus::Approved, WorkOrderChangeRequest::find($id)->status);
    }

    public function test_cancel_of_a_finished_order_answers_409_with_its_status(): void
    {
        $done = $this->order(WorkOrder::STATUS_DONE, ['order_no' => 'DONE-1']);
        $cancelled = $this->order(WorkOrder::STATUS_CANCELLED, ['order_no' => 'CANC-1']);

        $this->api()->postJson("/api/v1/erp/work-orders/{$done->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('status', WorkOrder::STATUS_DONE);
        $this->api()->postJson("/api/v1/erp/work-orders/{$cancelled->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('status', WorkOrder::STATUS_CANCELLED);

        $this->assertSame(WorkOrder::STATUS_DONE, $done->fresh()->status);
    }

    public function test_cancel_of_another_tenants_order_is_404(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        app(TenantContext::class)->set($otherTenant);
        $foreign = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING, 'tenant_id' => $otherTenant]);
        app(TenantContext::class)->set($this->tenantId);

        $this->api()->postJson("/api/v1/erp/work-orders/{$foreign->id}/cancel")->assertNotFound();

        $this->assertSame(
            WorkOrder::STATUS_PENDING,
            WorkOrder::withoutGlobalScopes()->find($foreign->id)->status,
        );
    }

    public function test_cancel_rejects_an_overlong_reason(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);

        $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel", ['reason' => str_repeat('x', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    // ── Change-request feed ──────────────────────────────────────────────────

    public function test_feed_filters_by_since_and_walks_pages_with_the_cursor(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);
        $old = WorkOrderChangeRequest::factory()->create(['work_order_id' => $order->id, 'tenant_id' => $this->tenantId]);
        WorkOrderChangeRequest::whereKey($old->id)->update(['updated_at' => now()->subDays(10)]);

        $ids = collect(range(1, 3))->map(fn () => WorkOrderChangeRequest::factory()->submitted()->create([
            'work_order_id' => $order->id,
            'tenant_id' => $this->tenantId,
        ])->id)->all();

        $since = now()->subDay()->toIso8601String();
        $seen = [];
        $cursor = null;

        do {
            $response = $this->api()->getJson('/api/v1/erp/change-requests?'.http_build_query(array_filter([
                'since' => $since,
                'limit' => 1,
                'cursor' => $cursor,
            ])))->assertOk();

            $seen = array_merge($seen, array_column($response->json('data'), 'id'));
            $cursor = $response->json('meta.next_cursor');
        } while ($cursor !== null);

        $this->assertSame($ids, $seen);
    }

    public function test_feed_filters_by_status_and_type(): void
    {
        $order = $this->order(WorkOrder::STATUS_IN_PROGRESS);
        $cancel = $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->json('data.change_request_id');
        WorkOrderChangeRequest::factory()->create(['work_order_id' => $order->id, 'tenant_id' => $this->tenantId]);

        $this->api()->getJson('/api/v1/erp/change-requests?type=CANCEL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cancel)
            ->assertJsonPath('data.0.order_no', 'ERP/2026/1');

        $this->api()->getJson('/api/v1/erp/change-requests?status=DRAFT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.origin', 'user');
    }

    public function test_feed_validates_its_filters(): void
    {
        $this->api()->getJson('/api/v1/erp/change-requests?since=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('since');
        $this->api()->getJson('/api/v1/erp/change-requests?type=OTHER')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_feed_does_not_list_another_tenants_requests(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other'])->id;
        app(TenantContext::class)->set($otherTenant);
        $foreign = WorkOrder::factory()->create(['tenant_id' => $otherTenant]);
        WorkOrderChangeRequest::factory()->create(['work_order_id' => $foreign->id, 'tenant_id' => $otherTenant]);
        app(TenantContext::class)->set($this->tenantId);

        $this->api()->getJson('/api/v1/erp/change-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Authentication and scopes ────────────────────────────────────────────

    public function test_new_endpoints_require_an_api_key(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);

        $this->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->assertStatus(401);
        $this->getJson('/api/v1/erp/change-requests')->assertStatus(401);
        $this->getJson('/api/v1/erp/work-orders/ERP%2F2026%2F1?by=order_no')->assertStatus(401);
    }

    public function test_new_endpoints_refuse_a_revoked_key(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);
        $this->key->delete();

        $this->api()->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->assertStatus(401);
        $this->api()->getJson('/api/v1/erp/change-requests')->assertStatus(401);

        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_cancel_requires_the_orders_import_scope(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);
        [, $readOnly] = $this->issueKey([ApiScope::ProductionRead], 'Read only');

        $this->api($readOnly)->postJson("/api/v1/erp/work-orders/{$order->id}/cancel")->assertStatus(403);

        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_reading_requires_the_production_read_scope(): void
    {
        $order = $this->order(WorkOrder::STATUS_PENDING);
        [, $importOnly] = $this->issueKey([ApiScope::OrdersImport], 'Import only');

        $this->api($importOnly)->getJson('/api/v1/erp/change-requests')->assertStatus(403);
        $this->api($importOnly)->getJson("/api/v1/erp/work-orders/{$order->id}")->assertStatus(403);
    }
}
