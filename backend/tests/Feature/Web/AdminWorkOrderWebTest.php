<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkOrderWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_admin_can_view_work_orders_list(): void
    {
        WorkOrder::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/admin/work-orders');

        $response->assertStatus(200);
    }

    public function test_operator_cannot_access_admin_work_orders(): void
    {
        $response = $this->actingAs($this->operator)->get('/admin/work-orders');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_work_orders_list(): void
    {
        $response = $this->get('/admin/work-orders');

        $response->assertRedirect('/login');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_admin_can_view_single_work_order(): void
    {
        $wo = WorkOrder::factory()->create();

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}");

        $response->assertStatus(200);
    }

    public function test_admin_sees_work_order_number_on_show_page(): void
    {
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-2026-TEST']);

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}");

        $response->assertSee('WO-2026-TEST');
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_admin_can_view_create_work_order_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/work-orders/create');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_work_order(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(2)->create([
            'product_type_id' => $productType->id,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no'        => 'WO-WEB-001',
            'line_id'         => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty'     => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-WEB-001']);
    }

    public function test_create_work_order_requires_order_no_and_quantity(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/work-orders', []);

        $response->assertSessionHasErrors(['order_no', 'planned_qty']);
    }

    public function test_create_work_order_requires_unique_order_no(): void
    {
        WorkOrder::factory()->create(['order_no' => 'WO-EXISTING']);

        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no'        => 'WO-EXISTING',
            'line_id'         => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty'     => 50,
        ]);

        $response->assertSessionHasErrors(['order_no']);
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function test_admin_can_view_edit_form(): void
    {
        $wo = WorkOrder::factory()->create();

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}/edit");

        $response->assertStatus(200);
    }

    public function test_admin_can_update_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['planned_qty' => 100]);

        $response = $this->actingAs($this->admin)->put("/admin/work-orders/{$wo->id}", [
            'order_no'    => $wo->order_no,
            'planned_qty' => 200,
            'status'      => WorkOrder::STATUS_PENDING,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', [
            'id'          => $wo->id,
            'planned_qty' => 200,
        ]);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_pending_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)->delete("/admin/work-orders/{$wo->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('work_orders', ['id' => $wo->id]);
    }

    // ── Status transitions ───────────────────────────────────────────────────

    public function test_admin_can_cancel_pending_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$wo->id}/cancel");

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', [
            'id'     => $wo->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
    }

    public function test_admin_can_accept_pending_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$wo->id}/accept");

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', [
            'id'     => $wo->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
        ]);
    }
}
