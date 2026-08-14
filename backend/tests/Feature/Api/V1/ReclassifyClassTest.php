<?php

namespace Tests\Feature\Api\V1;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialReclassification;
use App\Models\MaterialType;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reclassifying a quantity between material classes (#99): a regrade that moves
 * stock from one material to another, booking two correlated stock movements.
 */
class ReclassifyClassTest extends TestCase
{
    use RefreshDatabase;

    private Material $source;

    private Material $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $gradeA = MaterialType::create(['code' => 'GRADE-A', 'name' => 'Grade A']);
        $gradeB = MaterialType::create(['code' => 'GRADE-B', 'name' => 'Grade B']);
        $this->source = Material::create([
            'code' => 'STEEL-A', 'name' => 'Steel A', 'material_type_id' => $gradeA->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 500,
        ]);
        $this->target = Material::create([
            'code' => 'STEEL-B', 'name' => 'Steel B', 'material_type_id' => $gradeB->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 0,
        ]);
    }

    private function user(string $role): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->assignRole($role));
    }

    private function submit(User $u, array $body)
    {
        return $this->withHeader('Authorization', 'Bearer '.$u->createToken('t')->plainTextToken)
            ->postJson('/api/v1/material-reclassifications/class', $body);
    }

    public function test_supervisor_reclassifies_between_classes(): void
    {
        $this->submit($this->user('Supervisor'), [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->target->id,
            'qty' => 40,
        ])->assertOk();

        $this->assertEqualsWithDelta(460.0, (float) $this->source->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(40.0, (float) $this->target->fresh()->stock_quantity, 0.0001);

        $record = MaterialReclassification::firstWhere('type', MaterialReclassification::TYPE_CLASS);
        $this->assertNotNull($record);

        $legs = StockMovement::where('source_type', StockMovement::SOURCE_RECLASSIFICATION)
            ->where('source_id', $record->id)
            ->where('movement_type', StockMovement::TYPE_RECLASSIFY)
            ->get();
        $this->assertCount(2, $legs);
        $this->assertEqualsWithDelta(-40.0, (float) $legs->firstWhere('material_id', $this->source->id)->quantity, 0.0001);
        $this->assertEqualsWithDelta(40.0, (float) $legs->firstWhere('material_id', $this->target->id)->quantity, 0.0001);
    }

    public function test_reclassify_with_source_lot_decrements_the_lot(): void
    {
        $lot = MaterialLot::factory()->create([
            'material_id' => $this->source->id, 'status' => MaterialLot::STATUS_RELEASED,
            'quantity_received' => 100, 'quantity_available' => 100,
        ]);

        $this->submit($this->user('Supervisor'), [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->target->id,
            'qty' => 40,
            'source_lot_id' => $lot->id,
        ])->assertOk();

        $this->assertEqualsWithDelta(60.0, (float) $lot->fresh()->quantity_available, 0.0001);
    }

    public function test_same_source_and_target_is_rejected(): void
    {
        $this->submit($this->user('Supervisor'), [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->source->id,
            'qty' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('target_material_id');
    }

    public function test_lot_not_belonging_to_source_is_rejected(): void
    {
        $otherLot = MaterialLot::factory()->create([
            'material_id' => $this->target->id, 'status' => MaterialLot::STATUS_RELEASED,
            'quantity_received' => 50, 'quantity_available' => 50,
        ]);

        $this->submit($this->user('Supervisor'), [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->target->id,
            'qty' => 10,
            'source_lot_id' => $otherLot->id,
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(500.0, (float) $this->source->fresh()->stock_quantity, 0.0001);
    }

    public function test_operator_cannot_reclassify(): void
    {
        $this->submit($this->user('Operator'), [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->target->id,
            'qty' => 10,
        ])->assertForbidden();
    }

    public function test_guest_cannot_reclassify(): void
    {
        $this->postJson('/api/v1/material-reclassifications/class', [
            'source_material_id' => $this->source->id,
            'target_material_id' => $this->target->id,
            'qty' => 10,
        ])->assertUnauthorized();
    }
}
