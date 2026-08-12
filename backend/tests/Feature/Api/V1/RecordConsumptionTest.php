<?php

namespace Tests\Feature\Api\V1;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partial (actual) consumption against a work-order allocation (#99). Recording
 * consumption is bookkeeping only — stock already left at allocation time — and
 * the over-issued leftover is returned by consumeForBatch at completion.
 */
class RecordConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private Batch $batch;

    private MaterialAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M1', 'name' => 'Material 1', 'material_type_id' => $type->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 500,
        ]);

        $wo = WorkOrder::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
            'process_snapshot' => ['bom' => [[
                'material_id' => $this->material->id, 'material_code' => 'M1', 'material_name' => 'Material 1',
                'unit_of_measure' => 'kg', 'quantity_per_unit' => 1.0, 'scrap_percentage' => 0,
            ]]],
        ]);
        $this->batch = Batch::factory()->create([
            'work_order_id' => $wo->id, 'target_qty' => 100, 'produced_qty' => 0, 'status' => Batch::STATUS_PENDING,
        ]);

        app(MaterialAllocationService::class)->allocateForBatch($this->batch, $this->admin());
        $this->allocation = MaterialAllocation::firstWhere('batch_id', $this->batch->id);
    }

    private function admin(): User
    {
        return once(fn () => tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin')));
    }

    private function token(User $u): string
    {
        return $u->createToken('test')->plainTextToken;
    }

    public function test_records_partial_consumption_and_returns_leftover_at_completion(): void
    {
        // Allocation pulled 100 from 500.
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);

        $this->withHeader('Authorization', 'Bearer '.$this->token($this->admin()))
            ->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => 60])
            ->assertOk();

        $this->allocation->refresh();
        $this->assertEqualsWithDelta(60.0, (float) $this->allocation->consumed_qty, 0.0001);
        $this->assertSame(MaterialAllocation::STATUS_ALLOCATED, $this->allocation->status);
        // Declaring consumption books no stock delta on its own.
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);

        // Completion reconciler returns the 40 leftover.
        app(MaterialAllocationService::class)->consumeForBatch($this->batch);
        $this->assertEqualsWithDelta(440.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_recorded_zero_consumption_returns_the_full_allocation(): void
    {
        // Explicitly recording zero usage must mean "nothing consumed" — the whole
        // allocation returns to stock — NOT the unrecorded fallback (consume all).
        $this->withHeader('Authorization', 'Bearer '.$this->token($this->admin()))
            ->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => 0])
            ->assertOk();

        $this->assertTrue((bool) $this->allocation->fresh()->consumption_recorded);

        app(MaterialAllocationService::class)->consumeForBatch($this->batch);
        $this->assertEqualsWithDelta(500.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_unrecorded_consumption_falls_back_to_planned_at_completion(): void
    {
        // No declaration at all → consumeForBatch assumes the planned qty was used.
        app(MaterialAllocationService::class)->consumeForBatch($this->batch);
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_negative_consumed_qty_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token($this->admin()))
            ->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('consumed_qty');
    }

    public function test_recording_on_a_non_allocated_row_is_rejected(): void
    {
        $this->allocation->update(['status' => MaterialAllocation::STATUS_CONSUMED]);

        $this->withHeader('Authorization', 'Bearer '.$this->token($this->admin()))
            ->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => 10])
            ->assertStatus(422);
    }

    public function test_guest_cannot_record_consumption(): void
    {
        $this->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => 10])
            ->assertUnauthorized();
    }

    public function test_operator_outside_the_line_cannot_record_consumption(): void
    {
        // An operator not assigned to this order's line fails the WorkOrder view policy.
        $operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        $this->withHeader('Authorization', 'Bearer '.$this->token($operator))
            ->postJson("/api/v1/material-allocations/{$this->allocation->id}/consume", ['consumed_qty' => 10])
            ->assertForbidden();
    }
}
