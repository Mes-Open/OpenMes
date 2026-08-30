<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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

    // ── Show: the activity panel ─────────────────────────────────────────────

    public function test_show_page_reports_the_orders_activity_newest_first(): void
    {
        $wo = WorkOrder::factory()->create(['created_at' => now()->subDays(3)]);
        $batch = Batch::factory()->for($wo)->create([
            'batch_number' => 1,
            'started_at' => now()->subDay(),
        ]);
        BatchStep::factory()->for($batch)->create([
            'step_number' => 1,
            'name' => 'Cutting',
            'status' => 'DONE',
            'completed_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}");

        $response->assertInertia(function (AssertableInertia $page) {
            $activity = $page->toArray()['props']['workOrder']['activity'];

            // Newest first: the completed step, then the batch, then creation.
            $titles = array_column($activity, 'title');
            $this->assertSame(
                [__(':step completed', ['step' => 'Cutting']), __('Batch #:number started', ['number' => 1]), __('Order created')],
                $titles,
            );

            $timestamps = array_column($activity, 'at');
            $sorted = $timestamps;
            rsort($sorted);
            $this->assertSame($sorted, $timestamps);
        });
    }

    public function test_a_step_that_never_finished_is_not_in_the_activity(): void
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->for($wo)->create(['batch_number' => 1, 'started_at' => null]);
        BatchStep::factory()->for($batch)->create([
            'step_number' => 1,
            'name' => 'Cutting',
            'status' => 'IN_PROGRESS',
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}");

        $response->assertInertia(function (AssertableInertia $page) {
            $titles = array_column($page->toArray()['props']['workOrder']['activity'], 'title');

            // Only the one thing that has a timestamp to stand on.
            $this->assertSame([__('Order created')], $titles);
        });
    }

    public function test_the_activity_panel_is_capped(): void
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->for($wo)->create(['batch_number' => 1]);
        foreach (range(1, 12) as $n) {
            BatchStep::factory()->for($batch)->create([
                'step_number' => $n,
                'name' => "Step {$n}",
                'status' => 'DONE',
                'completed_at' => now()->subMinutes(60 - $n),
            ]);
        }

        $response = $this->actingAs($this->admin)->get("/admin/work-orders/{$wo->id}");

        $response->assertInertia(function (AssertableInertia $page) {
            $this->assertCount(8, $page->toArray()['props']['workOrder']['activity']);
        });
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
            'order_no' => 'WO-WEB-001',
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
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
            'order_no' => 'WO-EXISTING',
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 50,
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
            'order_no' => $wo->order_no,
            'planned_qty' => 200,
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'planned_qty' => 200,
        ]);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_pending_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)->delete("/admin/work-orders/{$wo->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    }

    // ── Status transitions ───────────────────────────────────────────────────

    public function test_admin_can_cancel_pending_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$wo->id}/cancel");

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
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
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
        ]);
    }

    /**
     * A failing live-sync broadcast (e.g. Reverb unreachable) must never break
     * the originating write — the status change still persists and no 500 is
     * returned. Guards against the production "accept errors out" report.
     */
    public function test_work_order_write_survives_a_broadcast_failure(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\CollectionChanged::class,
            function () {
                throw new \RuntimeException('Reverb unreachable');
            }
        );

        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$wo->id}/accept");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
        ]);
    }
}
