<?php

namespace Tests\Feature\Packaging;

use App\Models\LabelTemplate;
use App\Models\Line;
use App\Models\Pallet;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use App\Models\WorkOrderShiftEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end pallet process test, driven entirely through the HTTP endpoints
 * the real UI calls. One continuous scenario across two shifts:
 *
 *   morning   — operator opens a pallet at the station, scans pieces into it,
 *               a foreign EAN is rejected mid-flow
 *   afternoon — next shift finds the pallet on the open-pallets list, resumes
 *               it, scans more, closes it, prints PDF + ZPL labels
 *   dispatch  — admin marks the pallet shipped
 *   handover  — supervisor's balance reflects every step (packed/WIP/shipped)
 *               and closing the shift writes the audit snapshot
 *
 * Per-endpoint edge cases live in PalletStationTest / PalletCrudTest /
 * PalletLabelPrintTest / ShiftHandoverTest; this test guards the seams
 * between them.
 */
class PalletLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const EAN = '5901234123457';

    private const FOREIGN_EAN = '5909876543210';

    private User $operator;

    private User $admin;

    private User $supervisor;

    private Line $line;

    private Shift $morning;

    private Shift $afternoon;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->morning = Shift::create([
            'code' => 'I', 'name' => 'Morning', 'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 1, 'line_id' => null,
        ]);
        $this->afternoon = Shift::create([
            'code' => 'II', 'name' => 'Afternoon', 'start_time' => '14:00:00', 'end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => true, 'sort_order' => 2, 'line_id' => null,
        ]);

        $this->line = Line::factory()->create();
        $this->wo = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'status' => WorkOrder::STATUS_DONE,
            'planned_qty' => 100,
            'packed_qty' => 0,
        ]);
        WorkOrderEan::create(['work_order_id' => $this->wo->id, 'ean' => self::EAN]);

        LabelTemplate::create([
            'name' => 'Standard Pallet',
            'type' => LabelTemplate::TYPE_PALLET,
            'size' => '100x100',
            'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_PALLET),
            'barcode_format' => 'code128',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_full_pallet_process_from_station_to_shift_handover(): void
    {
        // ───── Morning shift, 10:00 ─────────────────────────────────────────
        $this->travelTo(Carbon::create(2026, 6, 10, 10, 0, 0));

        // Operator opens a pallet for the work order at the station.
        $palletId = $this->actingAs($this->operator)
            ->postJson(route('packaging.pallets.create'), ['work_order_id' => $this->wo->id])
            ->assertCreated()
            ->assertJsonPath('pallet.status', 'open')
            ->assertJsonPath('pallet.qty', 0)
            ->json('pallet.id');

        $palletNo = Pallet::findOrFail($palletId)->pallet_no;
        $this->assertMatchesRegularExpression('/^PAL-\d{6}$/', $palletNo);

        // Three pieces scanned into it; pallet qty and order packed_qty track.
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->operator)
                ->postJson(route('packaging.scan'), ['ean' => self::EAN, 'pallet_id' => $palletId])
                ->assertOk()
                ->assertJsonPath('pallet.qty', $i);
        }
        $this->assertSame(3, $this->wo->fresh()->packed_qty);

        // A piece from another order is rejected and changes nothing.
        $foreign = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_DONE, 'planned_qty' => 10, 'packed_qty' => 0,
        ]);
        WorkOrderEan::create(['work_order_id' => $foreign->id, 'ean' => self::FOREIGN_EAN]);

        $this->actingAs($this->operator)
            ->postJson(route('packaging.scan'), ['ean' => self::FOREIGN_EAN, 'pallet_id' => $palletId])
            ->assertStatus(422);
        $this->assertSame(3, Pallet::findOrFail($palletId)->qty);
        $this->assertSame(3, $this->wo->fresh()->packed_qty);

        // ───── Shift change → afternoon, 15:00 ──────────────────────────────
        $this->travelTo(Carbon::create(2026, 6, 10, 15, 0, 0));

        // The next shift's operator finds the pallet on the open list, with
        // its accumulated qty and line grouping intact.
        $nextOperator = User::factory()->create();
        $nextOperator->assignRole('Operator');

        $open = $this->actingAs($nextOperator)
            ->getJson(route('packaging.pallets.open', ['line_id' => $this->line->id]))
            ->assertOk()
            ->json('pallets');
        $this->assertCount(1, $open);
        $this->assertSame($palletId, $open[0]['id']);
        $this->assertSame(3, $open[0]['qty']);
        $this->assertSame($this->line->name, $open[0]['line_name']);

        // Resuming = scanning with the same pallet_id; the count keeps growing.
        foreach ([4, 5] as $expectedQty) {
            $this->actingAs($nextOperator)
                ->postJson(route('packaging.scan'), ['ean' => self::EAN, 'pallet_id' => $palletId])
                ->assertOk()
                ->assertJsonPath('pallet.qty', $expectedQty);
        }

        // Every accepted scan was logged against the pallet.
        $this->assertDatabaseCount('packaging_scan_logs', 5);
        $this->assertSame(5, Pallet::findOrFail($palletId)->scanLogs()->count());

        // Pallet is full — close it. Further scans into it are refused.
        $this->actingAs($nextOperator)
            ->postJson(route('packaging.pallets.close', $palletId))
            ->assertOk()
            ->assertJsonPath('pallet.status', 'closed');

        $this->actingAs($nextOperator)
            ->postJson(route('packaging.scan'), ['ean' => self::EAN, 'pallet_id' => $palletId])
            ->assertStatus(422);
        $this->assertSame(5, $this->wo->fresh()->packed_qty);

        // Labels print for the closed pallet (PDF stream + ZPL with its number).
        $this->actingAs($nextOperator)
            ->get(route('packaging.labels.pallet.pdf', $palletId))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $zpl = $this->actingAs($nextOperator)
            ->get(route('packaging.labels.pallet.zpl', $palletId))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($palletNo, $zpl);

        // ───── Dispatch, 15:30 — admin ships the pallet ─────────────────────
        $this->travelTo(Carbon::create(2026, 6, 10, 15, 30, 0));

        $this->actingAs($this->admin)
            ->put(route('admin.pallets.update', $palletId), [
                'work_order_id' => $this->wo->id,
                'pallet_no' => $palletNo,
                'qty' => 5,
                'status' => 'shipped',
                'location' => 'DOCK-2',
                'erp_reference' => 'ERP-789',
            ])
            ->assertRedirect(route('admin.pallets.index'));

        $this->assertDatabaseHas('pallets', [
            'id' => $palletId, 'status' => 'shipped', 'qty' => 5, 'location' => 'DOCK-2',
        ]);

        // ───── Shift handover, 16:00 — the balance reflects all of the above ─
        $this->travelTo(Carbon::create(2026, 6, 10, 16, 0, 0));

        // Production reported for the afternoon shift: 10 produced, 1 scrap.
        WorkOrderShiftEntry::create([
            'work_order_id' => $this->wo->id, 'shift_id' => $this->afternoon->id,
            'quantity' => 10, 'production_date' => '2026-06-10', 'user_id' => $nextOperator->id,
        ]);
        ScrapEntry::create([
            'work_order_id' => $this->wo->id, 'scrap_reason_id' => ScrapReason::factory()->create()->id,
            'quantity' => 1, 'shift_id' => $this->afternoon->id,
            'reported_at' => Carbon::create(2026, 6, 10, 15, 45, 0),
        ]);

        $this->actingAs($this->supervisor)
            ->getJson(route('supervisor.shift-handover.preview', ['line_id' => $this->line->id]))
            ->assertOk()
            ->assertJsonPath('balance.shift.name', 'Afternoon')
            ->assertJsonPath('balance.produced_qty', 10)
            ->assertJsonPath('balance.scrap_qty', 1)
            ->assertJsonPath('balance.good_qty', 9)
            // Only the 2 resume-scans fall in the afternoon window.
            ->assertJsonPath('balance.packed_qty', 2)
            // The pallet was shipped, so nothing is left open on the line...
            ->assertJsonPath('balance.wip_open_pallets_qty', 0)
            ->assertJsonPath('balance.wip_open_pallets_count', 0)
            // ...and its 5 pieces count as shipped this shift (good 9 − packed 2 = 7 unpacked).
            ->assertJsonPath('balance.wip_unpacked_qty', 7)
            ->assertJsonPath('balance.shipped_qty', 5)
            ->assertJsonPath('balance.discrepancies.unpacked.value', 7);

        // Supervisor confirms — the immutable snapshot is written.
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.shift-handover.store'), [
                'line_id' => $this->line->id, 'notes' => 'lifecycle e2e',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_handovers', [
            'line_id' => $this->line->id,
            'shift_id' => $this->afternoon->id,
            'produced_qty' => 10,
            'scrap_qty' => 1,
            'good_qty' => 9,
            'packed_qty' => 2,
            'shipped_qty' => 5,
            'confirmed_by' => $this->supervisor->id,
            'notes' => 'lifecycle e2e',
        ]);
    }
}
