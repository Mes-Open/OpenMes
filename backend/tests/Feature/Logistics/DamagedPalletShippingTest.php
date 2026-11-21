<?php

namespace Tests\Feature\Logistics;

use App\Enums\PalletCondition;
use App\Enums\PalletStatus;
use App\Models\Pallet;
use App\Models\PalletConditionEvent;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentScan;
use App\Models\UnitCarton;
use App\Models\User;
use App\Services\Logistics\PalletConditionService;
use App\Services\Logistics\ShipmentLoadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Damaged pallets cannot ship" (default on): a damaged pallet - or a carton
 * from one - is refused at the dock until its condition is restored to good.
 */
class DamagedPalletShippingTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentLoadingService $loading;

    private PalletConditionService $condition;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->loading = app(ShipmentLoadingService::class);
        $this->condition = app(PalletConditionService::class);
    }

    private function damagedPallet(): Pallet
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS]);
        $this->condition->record($pallet, PalletCondition::Damaged, 'Crushed cartons', $this->operator, PalletConditionEvent::SOURCE_RECEIPT);

        return $pallet->fresh();
    }

    private function shipmentWith(Pallet|UnitCarton $unit): Shipment
    {
        $shipment = Shipment::factory()->create();
        $factory = ShipmentItem::factory()->for($shipment);
        ($unit instanceof Pallet ? $factory->forPallet($unit) : $factory->forCarton($unit))->create();

        return $shipment;
    }

    private function setting(bool $on): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => PalletConditionService::SHIP_BLOCK_SETTING],
            ['value' => json_encode($on)],
        );
    }

    public function test_the_setting_is_on_by_default(): void
    {
        $this->assertTrue(PalletConditionService::blocksShipping());
    }

    public function test_a_damaged_pallet_scan_is_refused_with_a_clear_message(): void
    {
        $pallet = $this->damagedPallet();
        $shipment = $this->shipmentWith($pallet);

        $scan = $this->loading->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString($pallet->pallet_no, $scan->message);
        $this->assertStringContainsString('damaged', $scan->message);
        $this->assertNull($shipment->items()->first()->loaded_at);
    }

    public function test_a_carton_from_a_damaged_pallet_is_refused(): void
    {
        $pallet = $this->damagedPallet();
        $carton = UnitCarton::factory()->closed()->create(['pallet_id' => $pallet->id]);
        $shipment = $this->shipmentWith($carton);

        $scan = $this->loading->scan($shipment, $carton->carton_no, $this->operator);

        $this->assertSame(ShipmentScan::RESULT_BLOCKED, $scan->result);
        $this->assertStringContainsString('damaged', $scan->message);
    }

    public function test_a_restored_pallet_loads(): void
    {
        $pallet = $this->damagedPallet();
        $this->condition->record($pallet, PalletCondition::Good, 'Rewrapped', $this->admin, PalletConditionEvent::SOURCE_MANUAL);
        $shipment = $this->shipmentWith($pallet);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->loading->scan($shipment, $pallet->pallet_no, $this->operator)->result);
    }

    public function test_with_the_setting_off_a_damaged_pallet_loads_and_ships(): void
    {
        $this->setting(false);
        $pallet = $this->damagedPallet();
        $shipment = $this->shipmentWith($pallet);

        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->loading->scan($shipment, $pallet->pallet_no, $this->operator)->result);
        $this->loading->confirm($shipment, $this->operator);

        $this->assertSame(PalletStatus::Shipped, $pallet->fresh()->status);
        $this->assertTrue($pallet->fresh()->isDamaged());
    }

    public function test_damage_recorded_after_loading_stops_the_confirm(): void
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS]);
        $shipment = $this->shipmentWith($pallet);
        $this->loading->scan($shipment, $pallet->pallet_no, $this->operator);

        $this->condition->record($pallet, PalletCondition::Damaged, 'Dropped at the dock', $this->operator, PalletConditionEvent::SOURCE_MANUAL);

        try {
            $this->loading->confirm($shipment, $this->operator);
            $this->fail('A damaged pallet must not ship.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($pallet->pallet_no, $e->errors()['shipment'][0]);
        }
        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
    }

    public function test_a_loaded_carton_whose_pallet_is_damaged_afterwards_stops_the_confirm(): void
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS]);
        $carton = UnitCarton::factory()->closed()->create(['pallet_id' => $pallet->id]);
        $shipment = $this->shipmentWith($carton);
        $this->assertSame(ShipmentScan::RESULT_LOADED, $this->loading->scan($shipment, $carton->carton_no, $this->operator)->result);

        $this->condition->record($pallet, PalletCondition::Damaged, 'Forklift hit', $this->operator, PalletConditionEvent::SOURCE_MANUAL);

        try {
            $this->loading->confirm($shipment, $this->operator);
            $this->fail('A carton from a damaged pallet must not ship.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shipment', $e->errors());
        }
    }

    public function test_the_pallet_edit_cannot_mark_a_damaged_pallet_shipped(): void
    {
        $pallet = $this->damagedPallet();

        $this->actingAs($this->admin)->put(route('admin.pallets.update', $pallet), [
            'work_order_id' => $pallet->work_order_id,
            'status' => 'shipped',
        ])->assertSessionHasErrors(['status' => __('Pallet :code is damaged. Restore its condition to good before shipping.', ['code' => $pallet->pallet_no])]);

        $this->assertSame(PalletStatus::Closed, $pallet->fresh()->status);
    }

    public function test_condition_does_not_change_the_quality_status(): void
    {
        $pallet = $this->damagedPallet();

        $this->assertSame(Pallet::QUALITY_PASS, $pallet->quality_status);
        $pallet->recomputeQualityStatus();
        $this->assertTrue($pallet->fresh()->isDamaged());
    }

    // ── Settings → System ──────────────────────────────────────────────────

    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'production_period' => 'none', 'workflow_mode' => 'status', 'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1, 'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation', 'production_qty_edit_policy' => 'none', 'scanner_mode' => 'hid',
        ];
    }

    public function test_the_settings_page_shows_the_switch_on_by_default(): void
    {
        $this->actingAs($this->admin)->get('/settings/system')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('settings.damaged_pallets_cannot_ship', true));
    }

    public function test_admin_turns_the_switch_off_and_a_form_without_it_keeps_it(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->settingsPayload(['damaged_pallets_cannot_ship' => false]))
            ->assertSessionHasNoErrors();
        $this->assertFalse(PalletConditionService::blocksShipping());

        $this->actingAs($this->admin)->post('/settings/system', $this->settingsPayload())->assertSessionHasNoErrors();
        $this->assertFalse(PalletConditionService::blocksShipping());

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->settingsPayload(['damaged_pallets_cannot_ship' => 'maybe']))
            ->assertSessionHasErrors('damaged_pallets_cannot_ship');
    }

    public function test_an_operator_cannot_change_the_switch(): void
    {
        $this->actingAs($this->operator)
            ->post('/settings/system', $this->settingsPayload(['damaged_pallets_cannot_ship' => false]))
            ->assertForbidden();
        $this->assertTrue(PalletConditionService::blocksShipping());
    }
}
