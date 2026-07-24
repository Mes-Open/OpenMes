<?php

namespace Tests\Feature\Web\Logistics;

use App\Models\Pallet;
use App\Models\PalletMovement;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Physical pallet movement attribution (#103): a logistics operator moves a
 * pallet on the shop-floor terminal and the move is recorded against them.
 */
class PalletMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_operator_records_a_movement_attributed_to_the_logistics_worker(): void
    {
        $worker = Worker::factory()->logistics()->create(['code' => 'FL-04', 'name' => 'Anna Nowak']);
        $pallet = Pallet::factory()->create(['location' => 'A-12']);

        $response = $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'to_location' => 'B-07',
            'notes' => 'Moved to dispatch lane',
        ]);

        $response->assertRedirect(route('logistics.move-pallet'));
        $response->assertSessionHas('success');

        // The move is attributed to the performing operator and captures from→to.
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'from_location' => 'A-12',
            'to_location' => 'B-07',
            'notes' => 'Moved to dispatch lane',
            'performed_by' => $this->operator->id,
        ]);

        // The pallet's live location follows the move.
        $this->assertSame('B-07', $pallet->fresh()->location);

        // The attribution is reachable via the relationship (movement history).
        $movement = PalletMovement::first();
        $this->assertSame($worker->id, $movement->worker->id);
        $this->assertSame('Anna Nowak', $movement->worker->name);
    }

    public function test_to_location_is_required(): void
    {
        $worker = Worker::factory()->logistics()->create();
        $pallet = Pallet::factory()->create(['location' => 'A-1']);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'to_location' => '',
        ])->assertSessionHasErrors('to_location');

        $this->assertDatabaseCount('pallet_movements', 0);
        $this->assertSame('A-1', $pallet->fresh()->location);
    }

    public function test_operator_must_be_an_active_logistics_worker(): void
    {
        $nonLogistics = Worker::factory()->create(); // not flagged is_logistics
        $inactive = Worker::factory()->logistics()->inactive()->create();
        $pallet = Pallet::factory()->create();

        foreach ([$nonLogistics->id, $inactive->id] as $workerId) {
            $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
                'pallet_id' => $pallet->id,
                'worker_id' => $workerId,
                'to_location' => 'C-01',
            ])->assertSessionHasErrors('worker_id');
        }

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    public function test_a_shipped_pallet_cannot_be_moved(): void
    {
        $worker = Worker::factory()->logistics()->create();
        $pallet = Pallet::factory()->shipped()->create(['location' => 'DOCK-1']);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'to_location' => 'B-02',
        ])->assertSessionHasErrors('pallet_id');

        $this->assertDatabaseCount('pallet_movements', 0);
        // The dispatched pallet's location is left untouched.
        $this->assertSame('DOCK-1', $pallet->fresh()->location);
    }

    public function test_soft_deleted_operator_or_pallet_is_rejected_with_422_not_404(): void
    {
        $worker = Worker::factory()->logistics()->create();
        $pallet = Pallet::factory()->create();

        // A soft-deleted (still is_active) logistics worker must fail validation,
        // not slip past Rule::exists into a findOrFail 404.
        $goneWorker = Worker::factory()->logistics()->create();
        $goneWorker->delete();
        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $goneWorker->id,
            'to_location' => 'B-02',
        ])->assertSessionHasErrors('worker_id');

        // Likewise for a soft-deleted pallet.
        $gonePallet = Pallet::factory()->create();
        $gonePallet->delete();
        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $gonePallet->id,
            'worker_id' => $worker->id,
            'to_location' => 'B-02',
        ])->assertSessionHasErrors('pallet_id');

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    public function test_guest_cannot_record_a_movement(): void
    {
        $worker = Worker::factory()->logistics()->create();
        $pallet = Pallet::factory()->create();

        $this->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'to_location' => 'D-01',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    public function test_user_without_operator_role_cannot_access_terminal(): void
    {
        $plain = User::factory()->create(); // no roles

        $this->actingAs($plain)->get(route('logistics.move-pallet'))->assertForbidden();
    }

    public function test_terminal_lists_only_active_logistics_operators(): void
    {
        $eligible = Worker::factory()->logistics()->create(['name' => 'Eligible Driver']);
        Worker::factory()->create(['name' => 'Office Worker']);           // not logistics
        Worker::factory()->logistics()->inactive()->create(['name' => 'On Leave']); // inactive

        $this->actingAs($this->operator)->get(route('logistics.move-pallet'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('logistics/MovePallet')
                ->has('operators', 1)
                ->where('operators.0.id', $eligible->id));
    }

    public function test_admin_can_view_movement_history(): void
    {
        PalletMovement::factory()->create();

        // Rows arrive client-side via the Electric shape; assert the component.
        $this->actingAs($this->admin)->get(route('admin.pallet-movements.index'))
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/pallet-movements/Index'));
    }

    public function test_history_lookup_maps_are_bounded_to_the_recent_window(): void
    {
        // The lookup maps must only carry labels for movements the live shape
        // can actually deliver (its rolling window), so the payload stays
        // bounded as the append-only ledger grows.
        $recentWorker = Worker::factory()->logistics()->create(['code' => 'FL-01']);
        $recentPallet = Pallet::factory()->create(['pallet_no' => 'PAL-RECENT']);
        PalletMovement::factory()->create([
            'worker_id' => $recentWorker->id,
            'pallet_id' => $recentPallet->id,
            'moved_at' => now()->subDays(3),
        ]);

        $oldWorker = Worker::factory()->logistics()->create(['code' => 'FL-99']);
        $oldPallet = Pallet::factory()->create(['pallet_no' => 'PAL-OLD']);
        PalletMovement::factory()->create([
            'worker_id' => $oldWorker->id,
            'pallet_id' => $oldPallet->id,
            'moved_at' => now()->subDays(120),
        ]);

        $this->actingAs($this->admin)->get(route('admin.pallet-movements.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('operatorNames.'.$recentWorker->id)
                ->missing('operatorNames.'.$oldWorker->id)
                ->has('palletNumbers.'.$recentPallet->id)
                ->missing('palletNumbers.'.$oldPallet->id));
    }
}
