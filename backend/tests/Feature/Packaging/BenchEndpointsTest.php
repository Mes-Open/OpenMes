<?php

namespace Tests\Feature\Packaging;

use App\Models\Pallet;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The "bench": the pallet and carton an operator is filling. Held in the
 * database so a refresh, another device or a shift change sees the same thing.
 */
class BenchEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $anna;

    private User $ben;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->anna = User::factory()->create();
        $this->anna->assignRole('Operator');
        $this->ben = User::factory()->create();
        $this->ben->assignRole('Operator');
        $this->wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
    }

    public function test_a_pallet_is_taken_onto_a_bench_and_taken_over_visibly(): void
    {
        $first = Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 0]);
        $second = Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 0]);

        $this->actingAs($this->anna)->postJson(route('packaging.pallets.activate', $first))
            ->assertOk()->assertJsonPath('pallet.active_by_id', $this->anna->id);

        // One pallet per operator: taking the second lets go of the first.
        $this->actingAs($this->anna)->postJson(route('packaging.pallets.activate', $second))->assertOk();
        $this->assertNull($first->fresh()->active_by_id);
        $this->assertSame($this->anna->id, $second->fresh()->active_by_id);

        // Another operator may take it over - the list names who had it, so it is visible, not silent.
        $this->actingAs($this->ben)->postJson(route('packaging.pallets.activate', $second))
            ->assertOk()->assertJsonPath('pallet.active_by_id', $this->ben->id);
        $this->assertSame($this->ben->id, $second->fresh()->active_by_id);
    }

    public function test_only_the_holder_releases_a_pallet_and_a_closed_pallet_cannot_be_taken(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 0]);
        $this->actingAs($this->anna)->postJson(route('packaging.pallets.activate', $pallet))->assertOk();

        $this->actingAs($this->ben)->postJson(route('packaging.pallets.release', $pallet))->assertOk();
        $this->assertSame($this->anna->id, $pallet->fresh()->active_by_id, 'someone else\'s release changes nothing');

        $this->actingAs($this->anna)->postJson(route('packaging.pallets.release', $pallet))->assertOk();
        $this->assertNull($pallet->fresh()->active_by_id);

        $pallet->update(['status' => 'closed']);
        $this->actingAs($this->anna)->postJson(route('packaging.pallets.activate', $pallet))->assertStatus(422);
    }

    public function test_cartons_follow_the_same_bench_rules(): void
    {
        $first = UnitCarton::create(['work_order_id' => $this->wo->id, 'status' => UnitCarton::STATUS_OPEN, 'created_by_id' => $this->anna->id]);
        $second = UnitCarton::create(['work_order_id' => $this->wo->id, 'status' => UnitCarton::STATUS_OPEN, 'created_by_id' => $this->anna->id]);

        $this->actingAs($this->anna)->postJson(route('packaging.cartons.activate', $first))
            ->assertOk()->assertJsonPath('carton.active_by_id', $this->anna->id);
        $this->actingAs($this->anna)->postJson(route('packaging.cartons.activate', $second))->assertOk();
        $this->assertNull($first->fresh()->active_by_id);

        $this->actingAs($this->ben)->postJson(route('packaging.cartons.release', $second))->assertOk();
        $this->assertSame($this->anna->id, $second->fresh()->active_by_id);
        $this->actingAs($this->anna)->postJson(route('packaging.cartons.release', $second))->assertOk();
        $this->assertNull($second->fresh()->active_by_id);

        $second->update(['status' => UnitCarton::STATUS_CLOSED]);
        $this->actingAs($this->anna)->postJson(route('packaging.cartons.activate', $second))->assertStatus(422);
    }

    public function test_the_bench_needs_a_signed_in_user(): void
    {
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 0]);
        $carton = UnitCarton::create(['work_order_id' => $this->wo->id, 'status' => UnitCarton::STATUS_OPEN, 'created_by_id' => $this->anna->id]);

        $this->postJson(route('packaging.pallets.activate', $pallet))->assertUnauthorized();
        $this->postJson(route('packaging.pallets.release', $pallet))->assertUnauthorized();
        $this->postJson(route('packaging.cartons.activate', $carton))->assertUnauthorized();
        $this->postJson(route('packaging.cartons.release', $carton))->assertUnauthorized();
    }
}
