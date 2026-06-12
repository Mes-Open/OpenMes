<?php

namespace Tests\Feature\Supervisor;

use App\Models\Line;
use App\Models\PackagingScanLog;
use App\Models\Pallet;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $operator;

    private Line $line;

    private Shift $shift;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        // Freeze inside the morning shift on a Wednesday.
        $this->travelTo(Carbon::create(2026, 6, 10, 10, 0, 0));

        $this->shift = Shift::create([
            'code' => 'I', 'name' => 'Ranna', 'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 1, 'line_id' => null,
        ]);
        $this->line = Line::factory()->create();
        $this->wo = WorkOrder::factory()->create(['line_id' => $this->line->id]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function seedBalance(): void
    {
        // Produced 100 via shift entry; scrap 10 → good 90.
        WorkOrderShiftEntry::create([
            'work_order_id' => $this->wo->id, 'shift_id' => $this->shift->id,
            'quantity' => 100, 'production_date' => '2026-06-10', 'user_id' => $this->operator->id,
        ]);
        $reason = ScrapReason::factory()->create();
        ScrapEntry::create([
            'work_order_id' => $this->wo->id, 'scrap_reason_id' => $reason->id,
            'quantity' => 10, 'shift_id' => $this->shift->id, 'reported_at' => Carbon::create(2026, 6, 10, 9, 0, 0),
        ]);
        // Packed 3 scans in-window.
        foreach (range(1, 3) as $i) {
            PackagingScanLog::create([
                'work_order_id' => $this->wo->id, 'ean' => "e$i", 'product_name' => 'p',
                'scanned_at' => Carbon::create(2026, 6, 10, 9, 30, 0),
            ]);
        }
        // WIP: open pallet qty 5. Shipped pallet qty 20 (updated in-window).
        Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 5]);
        Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'shipped', 'qty' => 20]);
    }

    public function test_guest_redirected(): void
    {
        $this->get(route('supervisor.shift-handover.index'))->assertRedirect();
    }

    public function test_operator_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get(route('supervisor.shift-handover.index'))
            ->assertForbidden();
    }

    public function test_preview_computes_balance_for_line(): void
    {
        $this->seedBalance();

        $response = $this->actingAs($this->supervisor)
            ->getJson(route('supervisor.shift-handover.preview', ['line_id' => $this->line->id]));

        $response->assertOk()
            ->assertJsonPath('balance.produced_qty', 100)
            ->assertJsonPath('balance.scrap_qty', 10)
            ->assertJsonPath('balance.good_qty', 90)
            ->assertJsonPath('balance.packed_qty', 3)
            ->assertJsonPath('balance.wip_open_pallets_qty', 5)
            ->assertJsonPath('balance.wip_unpacked_qty', 87) // 90 good − 3 packed
            ->assertJsonPath('balance.shipped_qty', 20);
    }

    public function test_discrepancy_flagged_for_unpacked_output(): void
    {
        $this->seedBalance();

        $response = $this->actingAs($this->supervisor)
            ->getJson(route('supervisor.shift-handover.preview', ['line_id' => $this->line->id]));

        $this->assertArrayHasKey('unpacked', $response->json('balance.discrepancies'));
        $this->assertSame(87, $response->json('balance.discrepancies.unpacked.value'));
    }

    public function test_supervisor_can_close_shift_and_snapshot_is_saved(): void
    {
        $this->seedBalance();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.shift-handover.store'), ['line_id' => $this->line->id, 'notes' => 'all good'])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_handovers', [
            'line_id' => $this->line->id,
            'produced_qty' => 100,
            'good_qty' => 90,
            'packed_qty' => 3,
            'shipped_qty' => 20,
            'confirmed_by' => $this->supervisor->id,
            'notes' => 'all good',
        ]);
    }

    public function test_operator_cannot_close_shift(): void
    {
        $this->actingAs($this->operator)
            ->post(route('supervisor.shift-handover.store'), ['line_id' => $this->line->id])
            ->assertForbidden();

        $this->assertDatabaseCount('shift_handovers', 0);
    }
}
