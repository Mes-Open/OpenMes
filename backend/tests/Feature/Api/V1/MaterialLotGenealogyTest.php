<?php

namespace Tests\Feature\Api\V1;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialLotGenealogyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
    }

    public function test_forward_genealogy_returns_consumptions_with_batch_chain(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'quantity_received' => 100,
            'quantity_available' => 60,
        ]);

        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id]);

        BatchStepLotConsumption::create([
            'batch_step_id' => $step->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => 25,
            'consumed_at' => now(),
            'recorded_by_id' => $this->user->id,
        ]);
        BatchStepLotConsumption::create([
            'batch_step_id' => $step->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => 15,
            'consumed_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/material-lots/{$lot->id}/genealogy/forward");

        $response->assertOk();
        $payload = $response->json('data');
        $this->assertSame($lot->id, $payload['lot']['id']);
        $this->assertCount(2, $payload['consumptions']);
        $this->assertEqualsWithDelta(40.0, (float) $payload['total_consumed'], 0.0001);
        $this->assertEquals([$step->id], $payload['consumed_in_steps']);
    }

    public function test_backward_genealogy_returns_inspection_and_supplier(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'supplier_lot_no' => 'SUP-XYZ',
            'supplier_reference' => 'PO-12345',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/material-lots/{$lot->id}/genealogy/backward");

        $response->assertOk()->assertJsonPath('data.supplier_lot_no', 'SUP-XYZ');
        $response->assertJsonPath('data.supplier_reference', 'PO-12345');
    }

    public function test_consume_endpoint_decrements_quantity_and_writes_genealogy_row(): void
    {
        $material = Material::factory()->create();
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'material_id' => $material->id,
            'quantity_received' => 100,
            'quantity_available' => 100,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);

        $step = BatchStep::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/material-lots/{$lot->id}/consume", [
                'batch_step_id' => $step->id,
                'quantity' => 30,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('batch_step_lot_consumption', [
            'batch_step_id' => $step->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => 30.0000,
        ]);
        $this->assertEqualsWithDelta(70.0, (float) $lot->fresh()->quantity_available, 0.0001);
    }

    public function test_consume_endpoint_rejects_quarantine_lot(): void
    {
        $lot = MaterialLot::factory()->quarantine()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);
        $step = BatchStep::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/material-lots/{$lot->id}/consume", [
                'batch_step_id' => $step->id,
                'quantity' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_consume_endpoint_rejects_overflow(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);
        $step = BatchStep::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/material-lots/{$lot->id}/consume", [
                'batch_step_id' => $step->id,
                'quantity' => 50,
            ])
            ->assertStatus(422);

        // Lot must not be partially decremented when the call fails.
        $this->assertEqualsWithDelta(10.0, (float) $lot->fresh()->quantity_available, 0.0001);
    }

    public function test_consume_full_quantity_transitions_status(): void
    {
        $lot = MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'quantity_received' => 50,
            'quantity_available' => 50,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);
        $step = BatchStep::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/material-lots/{$lot->id}/consume", [
                'batch_step_id' => $step->id,
                'quantity' => 50,
            ])
            ->assertCreated();

        $fresh = $lot->fresh();
        $this->assertEqualsWithDelta(0.0, (float) $fresh->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $fresh->status);
    }
}
