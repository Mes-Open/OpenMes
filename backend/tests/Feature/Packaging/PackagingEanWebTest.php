<?php

namespace Tests\Feature\Packaging;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EAN management + scan moved from a (removed) /api/v1/packaging surface to the
 * web packaging controllers. EAN CRUD is Supervisor|Admin-gated and redirects;
 * scan returns JSON. The pallet/scan station flow itself is covered by
 * PalletStationTest / PalletLifecycleTest.
 */
class PackagingEanWebTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── EAN management ───────────────────────────────────────────────────────

    public function test_supervisor_can_create_ean(): void
    {
        $wo = WorkOrder::factory()->create();

        $this->actingAs($this->supervisor)
            ->post('/packaging/eans', ['work_order_id' => $wo->id, 'ean' => '5901234567890'])
            ->assertRedirect();

        $this->assertDatabaseHas('work_order_eans', ['ean' => '5901234567890']);
    }

    public function test_operator_cannot_create_ean(): void
    {
        $wo = WorkOrder::factory()->create();

        $this->actingAs($this->operator)
            ->post('/packaging/eans', ['work_order_id' => $wo->id, 'ean' => '5901234567890'])
            ->assertForbidden();
    }

    public function test_unique_ean_required(): void
    {
        $wo = WorkOrder::factory()->create();
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'DUP']);

        $this->actingAs($this->supervisor)
            ->post('/packaging/eans', ['work_order_id' => $wo->id, 'ean' => 'DUP'])
            ->assertSessionHasErrors('ean');
    }

    public function test_supervisor_can_delete_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $ean = WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'E1']);

        $this->actingAs($this->supervisor)
            ->delete("/packaging/eans/{$ean->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('work_order_eans', ['id' => $ean->id]);
    }

    public function test_operator_cannot_delete_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $ean = WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'E1']);

        $this->actingAs($this->operator)
            ->delete("/packaging/eans/{$ean->id}")
            ->assertForbidden();
    }

    // ── Scan ─────────────────────────────────────────────────────────────────

    public function test_operator_can_scan_in_progress_wo(): void
    {
        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 10,
            'packed_qty' => 0,
        ]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => '12345']);

        $this->actingAs($this->operator)
            ->postJson('/packaging/scan', ['ean' => '12345'])
            ->assertOk();

        $this->assertSame(1, (int) $wo->fresh()->packed_qty);
        $this->assertDatabaseCount('packaging_scan_logs', 1);
    }

    public function test_scan_unknown_ean_returns_404(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/packaging/scan', ['ean' => 'NONEXISTENT'])
            ->assertStatus(404);
    }

    public function test_scan_pending_wo_rejected(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING, 'planned_qty' => 10]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'X']);

        $this->actingAs($this->operator)
            ->postJson('/packaging/scan', ['ean' => 'X'])
            ->assertStatus(422);
    }

    public function test_scan_fully_packed_wo_rejected(): void
    {
        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_DONE,
            'planned_qty' => 5,
            'packed_qty' => 5,
        ]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'X']);

        $this->actingAs($this->operator)
            ->postJson('/packaging/scan', ['ean' => 'X'])
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_scan(): void
    {
        $this->postJson('/packaging/scan', ['ean' => 'X'])->assertStatus(401);
    }
}
