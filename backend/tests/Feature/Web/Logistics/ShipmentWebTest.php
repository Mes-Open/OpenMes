<?php

namespace Tests\Feature\Web\Logistics;

use App\Enums\PalletStatus;
use App\Models\Pallet;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The shipment screens (#400): supervisors plan loads from a pasted list or a
 * CSV, the dock scans them on the loading terminal without leaving the page.
 */
class ShipmentWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function pallet(array $attributes = []): Pallet
    {
        return Pallet::factory()->closed()->create($attributes + ['quality_status' => Pallet::QUALITY_PASS]);
    }

    public function test_admin_and_supervisor_pages_render(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->admin)->get('/admin/shipments')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/shipments/Index')->where('basePath', '/admin/shipments'));
        $this->actingAs($this->admin)->get('/admin/shipments/create')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/shipments/Create'));
        $this->actingAs($this->admin)->get("/admin/shipments/{$shipment->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/shipments/Show')
                ->where('shipment.shipment_no', $shipment->shipment_no)
                ->where('terminalHref', route('logistics.loading.show', $shipment)));
        $this->actingAs($this->admin)->get("/admin/shipments/{$shipment->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/shipments/Edit'));

        $this->actingAs($this->supervisor)->get('/supervisor/shipments')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/shipments/Index')->where('basePath', '/supervisor/shipments'));
    }

    public function test_supervisor_creates_a_shipment_from_a_pasted_list(): void
    {
        $pallet = $this->pallet();
        $other = $this->pallet();

        $response = $this->actingAs($this->supervisor)->post('/supervisor/shipments', [
            'shipment_no' => 'LOAD-2026-0142',
            'carrier' => 'Fast Freight',
            'vehicle_no' => 'WX 12345',
            'destination' => 'Berlin DC',
            'items_text' => "type,identifier\n{$pallet->pallet_no}\n\npallet;{$other->pallet_no}\ncarton,CTN-EXT-1\n",
            'default_unit_type' => 'auto',
        ]);

        $shipment = Shipment::where('shipment_no', 'LOAD-2026-0142')->firstOrFail();
        $response->assertRedirect(route('supervisor.shipments.show', $shipment))->assertSessionHas('success');
        $this->assertSame(
            [[$pallet->pallet_no, 'pallet'], [$other->pallet_no, 'pallet'], ['CTN-EXT-1', 'carton']],
            $shipment->items()->orderBy('id')->get()->map(fn ($i) => [$i->identifier, $i->unit_type])->all(),
        );
        $this->assertSame($pallet->id, $shipment->items()->orderBy('id')->first()->pallet_id);
    }

    public function test_the_form_reports_bad_lines_and_a_missing_list(): void
    {
        $this->actingAs($this->supervisor)->post('/supervisor/shipments', [
            'shipment_no' => 'LOAD-1',
            'items_text' => "PAL-1\nPAL 2 <b>\n",
        ])->assertSessionHasErrors(['items.1.identifier']);

        $this->actingAs($this->supervisor)->post('/supervisor/shipments', [
            'shipment_no' => 'LOAD-1',
            'items_text' => '   ',
        ])->assertSessionHasErrors(['items']);

        $this->actingAs($this->supervisor)->post('/supervisor/shipments', [
            'items_text' => 'PAL-1',
        ])->assertSessionHasErrors(['shipment_no']);
    }

    public function test_editing_replaces_the_list(): void
    {
        $shipment = Shipment::factory()->create(['shipment_no' => 'LOAD-5']);
        ShipmentItem::factory()->for($shipment)->create(['identifier' => 'PAL-OLD']);

        $this->actingAs($this->admin)->put("/admin/shipments/{$shipment->id}", [
            'shipment_no' => 'LOAD-5',
            'carrier' => 'New carrier',
            'items_text' => "PAL-NEW\n",
        ])->assertRedirect(route('admin.shipments.show', $shipment));

        $this->assertSame('New carrier', $shipment->fresh()->carrier);
        $this->assertSame(['PAL-NEW'], $shipment->items()->pluck('identifier')->all());
        $this->assertSoftDeleted('shipment_items', ['shipment_id' => $shipment->id, 'identifier' => 'PAL-OLD']);
    }

    public function test_delete_is_soft_and_the_number_can_be_used_again(): void
    {
        $shipment = Shipment::factory()->create(['shipment_no' => 'LOAD-REUSE']);
        ShipmentItem::factory()->for($shipment)->create();

        $this->actingAs($this->admin)->delete("/admin/shipments/{$shipment->id}")
            ->assertRedirect(route('admin.shipments.index'));

        $this->assertSoftDeleted('shipments', ['id' => $shipment->id, 'deleted_by_id' => $this->admin->id]);
        $this->assertSoftDeleted('shipment_items', ['shipment_id' => $shipment->id]);

        $this->actingAs($this->admin)->post('/admin/shipments', [
            'shipment_no' => 'LOAD-REUSE',
            'items_text' => 'PAL-1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Shipment::where('shipment_no', 'LOAD-REUSE')->count());
    }

    public function test_a_shipment_being_loaded_is_not_deleted_and_can_be_cancelled(): void
    {
        $shipment = Shipment::factory()->loading()->create();

        $this->actingAs($this->admin)->delete("/admin/shipments/{$shipment->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('shipments', ['id' => $shipment->id]);

        $this->actingAs($this->admin)->post("/admin/shipments/{$shipment->id}/cancel")->assertSessionHas('success');
        $this->assertSame('cancelled', $shipment->fresh()->status->value);
    }

    public function test_operators_cannot_manage_shipments(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->operator)->get('/admin/shipments')->assertForbidden();
        $this->actingAs($this->operator)->get('/supervisor/shipments')->assertForbidden();
        $this->actingAs($this->operator)->post('/supervisor/shipments', ['shipment_no' => 'X-1', 'items_text' => 'PAL-1'])->assertForbidden();
        $this->actingAs($this->operator)->post("/supervisor/shipments/{$shipment->id}/cancel")->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $shipment = Shipment::factory()->create();

        $this->get('/admin/shipments')->assertRedirect();
        $this->get('/logistics/loading')->assertRedirect();
        $this->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => 'PAL-1'])->assertUnauthorized();
    }

    public function test_operator_terminal_scans_and_confirms_a_full_load(): void
    {
        $pallet = $this->pallet();
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->for($shipment)->forPallet($pallet)->create();

        $this->actingAs($this->operator)->get('/logistics/loading')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('logistics/LoadingPicker'));
        $this->actingAs($this->operator)->get("/logistics/loading/{$shipment->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('logistics/Loading')
                ->where('shipment.id', $shipment->id)
                ->where('canConfirmShort', false));

        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => $pallet->pallet_no])
            ->assertOk()->assertJsonPath('data.scan.result', 'loaded')->assertJsonPath('data.totals.missing', 0);
        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => $pallet->pallet_no])
            ->assertOk()->assertJsonPath('data.scan.result', 'duplicate');

        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame(PalletStatus::Shipped, $pallet->fresh()->status);

        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => $pallet->pallet_no])
            ->assertUnprocessable();
    }

    public function test_terminal_short_load_is_refused_for_operators_and_needs_a_reason_from_supervisors(): void
    {
        $a = $this->pallet();
        $b = $this->pallet();
        $shipment = Shipment::factory()->create();
        ShipmentItem::factory()->for($shipment)->forPallet($a)->create();
        $missing = ShipmentItem::factory()->for($shipment)->forPallet($b)->create();
        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => $a->pallet_no])->assertOk();

        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/confirm")->assertForbidden();
        $this->actingAs($this->supervisor)->postJson("/logistics/loading/{$shipment->id}/confirm", ['short_reason' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['short_reason']);
        $this->actingAs($this->supervisor)->postJson("/logistics/loading/{$shipment->id}/confirm", ['short_reason' => str_repeat('x', 1001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['short_reason']);
        $this->actingAs($this->supervisor)->postJson("/logistics/loading/{$shipment->id}/confirm", ['short_reason' => 'Damaged on the forklift'])
            ->assertOk()->assertJsonPath('data.missing.0.identifier', $missing->identifier);
    }

    public function test_terminal_undo(): void
    {
        $pallet = $this->pallet();
        $shipment = Shipment::factory()->create();
        $item = ShipmentItem::factory()->for($shipment)->forPallet($pallet)->create();
        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => $pallet->pallet_no]);

        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/items/{$item->id}/unload")
            ->assertOk()->assertJsonPath('data.scan.result', 'removed')->assertJsonPath('data.totals.loaded', 0);
        $this->actingAs($this->operator)->postJson("/logistics/loading/{$shipment->id}/items/{$item->id}/unload")
            ->assertUnprocessable();
    }

    public function test_users_without_a_dock_role_cannot_use_the_terminal(): void
    {
        $nobody = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($nobody)->get('/logistics/loading')->assertForbidden();
        $this->actingAs($nobody)->postJson("/logistics/loading/{$shipment->id}/scan", ['code' => 'PAL-1'])->assertForbidden();
    }

    public function test_switching_packaging_off_hides_the_shipment_screens(): void
    {
        $shipment = Shipment::factory()->create();
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['packaging'])));

        $this->actingAs($this->operator)->get('/logistics/loading')->assertNotFound();
        $this->actingAs($this->supervisor)->get('/supervisor/shipments')->assertNotFound();
        $this->actingAs($this->operator)->postJson("/api/v1/shipments/{$shipment->id}/scan", ['code' => 'PAL-1'])->assertNotFound();
    }

    public function test_collections_are_readable_by_the_dock_only(): void
    {
        foreach (['shipments', 'shipment_items', 'shipment_scans'] as $name) {
            $this->actingAs($this->operator)->getJson("/api/collections/{$name}")->assertOk();
        }

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->getJson('/api/collections/shipments')->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/collections/shipment_scans')->assertForbidden();
    }
}
