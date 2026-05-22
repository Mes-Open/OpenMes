<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialLotControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'lot_number' => 'BOLT-M10-' . now()->format('Y') . '-0001',
            'material_id' => Material::factory()->create()->id,
            'quantity_received' => 100,
            'unit_of_measure' => 'pcs',
            'received_at' => now()->format('Y-m-d\TH:i'),
            'status' => MaterialLot::STATUS_RECEIVED,
        ], $overrides);
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.material-lots.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_index(): void
    {
        MaterialLot::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.material-lots.index'))
            ->assertOk()
            ->assertViewIs('admin.material-lots.index');
    }

    public function test_admin_can_create_lot(): void
    {
        $material = Material::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.material-lots.store'), $this->validPayload([
                'material_id' => $material->id,
                'lot_number' => 'NEW-LOT-001',
                'quantity_received' => 250,
            ]));

        $response->assertRedirect(route('admin.material-lots.index'));
        $this->assertDatabaseHas('material_lots', [
            'lot_number' => 'NEW-LOT-001',
            'material_id' => $material->id,
            'quantity_received' => 250,
        ]);

        $lot = MaterialLot::where('lot_number', 'NEW-LOT-001')->first();
        // quantity_available should default to quantity_received when omitted
        $this->assertEqualsWithDelta(250.0, (float) $lot->quantity_available, 0.0001);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.material-lots.store'), [])
            ->assertSessionHasErrors(['lot_number', 'material_id', 'quantity_received', 'unit_of_measure', 'received_at', 'status']);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.material-lots.store'), $this->validPayload(['status' => 'totally-bogus']))
            ->assertSessionHasErrors('status');
    }

    public function test_store_rejects_duplicate_lot_number_within_same_tenant(): void
    {
        $material = Material::factory()->create();
        MaterialLot::factory()->create([
            'lot_number' => 'DUP-001',
            'tenant_id' => $this->admin->tenant_id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.material-lots.store'), $this->validPayload([
                'lot_number' => 'DUP-001',
                'material_id' => $material->id,
            ]))
            ->assertSessionHasErrors('lot_number');
    }

    public function test_admin_can_update_lot(): void
    {
        $lot = MaterialLot::factory()->create(['tenant_id' => $this->admin->tenant_id]);

        $this->actingAs($this->admin)
            ->put(route('admin.material-lots.update', $lot), $this->validPayload([
                'lot_number' => $lot->lot_number,
                'material_id' => $lot->material_id,
                'status' => MaterialLot::STATUS_RELEASED,
                'quantity_received' => 500,
                'quantity_available' => 500,
            ]))
            ->assertRedirect(route('admin.material-lots.index'));

        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->fresh()->status);
    }

    public function test_show_loads_genealogy_relations(): void
    {
        $lot = MaterialLot::factory()->create(['tenant_id' => $this->admin->tenant_id]);

        $this->actingAs($this->admin)
            ->get(route('admin.material-lots.show', $lot))
            ->assertOk()
            ->assertSee($lot->lot_number);
    }

    public function test_destroy_blocks_lot_with_recorded_consumption(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->admin->tenant_id,
            'quantity_received' => 100,
            'quantity_available' => 75, // 25 already consumed
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.material-lots.destroy', $lot))
            ->assertRedirect(route('admin.material-lots.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('material_lots', ['id' => $lot->id]);
    }

    public function test_destroy_removes_untouched_lot(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->admin->tenant_id,
            'quantity_received' => 100,
            'quantity_available' => 100,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.material-lots.destroy', $lot))
            ->assertRedirect(route('admin.material-lots.index'));

        $this->assertDatabaseMissing('material_lots', ['id' => $lot->id]);
    }

    public function test_index_filters_by_status_and_material(): void
    {
        $materialA = Material::factory()->create();
        $materialB = Material::factory()->create();
        MaterialLot::factory()->create(['material_id' => $materialA->id, 'status' => MaterialLot::STATUS_RELEASED, 'tenant_id' => $this->admin->tenant_id]);
        MaterialLot::factory()->create(['material_id' => $materialA->id, 'status' => MaterialLot::STATUS_QUARANTINE, 'tenant_id' => $this->admin->tenant_id]);
        MaterialLot::factory()->create(['material_id' => $materialB->id, 'status' => MaterialLot::STATUS_RELEASED, 'tenant_id' => $this->admin->tenant_id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.material-lots.index', [
                'material_id' => $materialA->id,
                'status' => MaterialLot::STATUS_RELEASED,
            ]));

        $response->assertOk();
        $lots = $response->viewData('lots');
        $this->assertCount(1, $lots);
    }
}
