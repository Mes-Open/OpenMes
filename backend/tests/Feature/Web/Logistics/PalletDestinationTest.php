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
 * Pallet destination tracking (#101): a pallet carries a status, a current
 * location and a destination; all three can be updated as it moves, and every
 * change lands on the same append-only movement ledger.
 */
class PalletDestinationTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $admin;

    private Worker $driver;

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

        $this->driver = Worker::factory()->logistics()->create(['code' => 'FL-09', 'name' => 'Piotr Zieliński']);
    }

    // ── Moving with a destination ────────────────────────────────────────────

    public function test_a_move_can_assign_a_destination_and_records_it_in_history(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01', 'destination' => null]);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'A-05',
            'to_destination' => 'DOCK-01',
        ])->assertRedirect(route('logistics.move-pallet'));

        $pallet->refresh();
        $this->assertSame('A-05', $pallet->location);
        $this->assertSame('DOCK-01', $pallet->destination);
        $this->assertTrue($pallet->isInTransit());
        $this->assertNull($pallet->arrived_at);

        // Both axes of the change are on the one ledger row.
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'from_location' => 'A-01',
            'to_location' => 'A-05',
            'from_destination' => null,
            'to_destination' => 'DOCK-01',
        ]);
    }

    public function test_a_blank_destination_on_a_move_keeps_the_standing_destination(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01', 'destination' => 'DOCK-01']);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'A-09',
            'to_destination' => '',
        ]);

        // Still heading for the dock — an intermediate move must not drop the target.
        $this->assertSame('DOCK-01', $pallet->fresh()->destination);
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'from_destination' => 'DOCK-01',
            'to_destination' => 'DOCK-01',
        ]);
    }

    public function test_moving_onto_the_destination_clears_it_and_stamps_the_arrival(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01', 'destination' => 'DOCK-01']);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'DOCK-01',
        ]);

        $pallet->refresh();
        $this->assertSame('DOCK-01', $pallet->location);
        $this->assertNull($pallet->destination, 'A reached destination is satisfied, not pending.');
        $this->assertNotNull($pallet->arrived_at);
        $this->assertFalse($pallet->isInTransit());

        // The ledger reads as an arrival: destination went from set to unset.
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'to_location' => 'DOCK-01',
            'from_destination' => 'DOCK-01',
            'to_destination' => null,
        ]);
    }

    public function test_moving_on_after_an_arrival_clears_the_stale_arrival_stamp(): void
    {
        $pallet = Pallet::factory()->create([
            'location' => 'DOCK-01',
            'destination' => null,
            'arrived_at' => now()->subHour(),
        ]);

        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'A-02',
        ]);

        // arrived_at describes standing at a reached destination; once the pallet
        // leaves, keeping it would misreport where it is.
        $this->assertNull($pallet->fresh()->arrived_at);
    }

    // ── Re-routing without moving ────────────────────────────────────────────

    public function test_a_pallet_can_be_re_routed_without_being_moved(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01', 'destination' => 'DOCK-01']);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-07',
            'worker_id' => $this->driver->id,
            'notes' => 'Truck swapped',
        ])->assertRedirect(route('logistics.pallets'));

        $pallet->refresh();
        $this->assertSame('A-01', $pallet->location, 'Re-routing must not move the pallet.');
        $this->assertSame('DOCK-07', $pallet->destination);

        // from == to location marks this as a re-route rather than a move.
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'from_location' => 'A-01',
            'to_location' => 'A-01',
            'from_destination' => 'DOCK-01',
            'to_destination' => 'DOCK-07',
            'notes' => 'Truck swapped',
            'performed_by' => $this->operator->id,
        ]);
    }

    public function test_re_routing_an_arrived_pallet_voids_the_previous_arrival(): void
    {
        $pallet = Pallet::factory()->create([
            'location' => 'DOCK-01',
            'destination' => null,
            'arrived_at' => now()->subHour(),
        ]);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-07',
        ]);

        $pallet->refresh();
        $this->assertSame('DOCK-07', $pallet->destination);
        $this->assertNull($pallet->arrived_at, 'A fresh target means the pallet is in transit again.');
    }

    public function test_a_blank_destination_clears_the_target_and_leaves_the_pallet_put(): void
    {
        $arrivedAt = now()->subDay()->startOfSecond();
        $pallet = Pallet::factory()->create([
            'location' => 'A-01',
            'destination' => 'DOCK-01',
            'arrived_at' => $arrivedAt,
        ]);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => '',
        ]);

        $pallet->refresh();
        $this->assertNull($pallet->destination);
        $this->assertSame('A-01', $pallet->location);
        // The pallet never moved, so its last arrival still stands.
        $this->assertTrue($arrivedAt->equalTo($pallet->arrived_at));

        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'from_destination' => 'DOCK-01',
            'to_destination' => null,
        ]);
    }

    public function test_assigning_the_current_location_as_the_destination_counts_as_an_arrival(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'DOCK-01', 'destination' => null]);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-01',
        ]);

        $pallet->refresh();
        // Leaving it pending would create a target no move could ever satisfy.
        $this->assertNull($pallet->destination);
        $this->assertNotNull($pallet->arrived_at);
    }

    public function test_re_routing_is_allowed_without_an_operator(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01']);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-02',
        ])->assertSessionHasNoErrors();

        // Dispatch planning has no forklift operator to credit.
        $this->assertDatabaseHas('pallet_movements', [
            'pallet_id' => $pallet->id,
            'worker_id' => null,
            'to_destination' => 'DOCK-02',
        ]);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_destination_is_length_capped(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01']);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => str_repeat('X', 101),
        ])->assertSessionHasErrors('destination');

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    public function test_a_shipped_pallet_cannot_be_re_routed(): void
    {
        $pallet = Pallet::factory()->shipped()->create(['location' => 'DOCK-01', 'destination' => null]);

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-09',
        ])->assertSessionHasErrors('pallet_id');

        $this->assertDatabaseCount('pallet_movements', 0);
        $this->assertNull($pallet->fresh()->destination);
    }

    public function test_a_soft_deleted_pallet_is_rejected_with_422_not_404(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01']);
        $pallet->delete();

        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-09',
        ])->assertSessionHasErrors('pallet_id');

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_cannot_view_the_logistics_view_or_re_route(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01']);

        $this->get(route('logistics.pallets'))->assertRedirect(route('login'));
        $this->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-09',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('pallet_movements', 0);
    }

    public function test_user_without_a_logistics_role_is_forbidden(): void
    {
        $plain = User::factory()->create(); // no roles

        $this->actingAs($plain)->get(route('logistics.pallets'))->assertForbidden();
        $this->actingAs($plain)->post(route('logistics.pallets.destination'), [
            'pallet_id' => Pallet::factory()->create()->id,
            'destination' => 'DOCK-09',
        ])->assertForbidden();
    }

    // ── The logistics view ───────────────────────────────────────────────────

    public function test_logistics_view_renders_with_status_labels_and_movable_pallets(): void
    {
        Pallet::factory()->create(['location' => 'A-01', 'destination' => 'DOCK-01']);
        Pallet::factory()->shipped()->create(['location' => 'DOCK-09']);

        // Rows themselves stream from the `pallets` shape; the props carry the
        // status labels and the re-route picker's options.
        $this->actingAs($this->operator)->get(route('logistics.pallets'))
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('logistics/Pallets')
                ->where('statusLabels.open', __('Pallet open'))
                // Shipped pallets can't be re-routed, so they're not offered.
                ->has('movablePallets', 1)
                ->where('movablePallets.0.destination', 'DOCK-01')
                ->has('operators', 1));
    }

    public function test_destination_is_exposed_to_the_pallets_shape(): void
    {
        // A column missing from the allowlist never reaches the browser, so the
        // logistics table would render an empty destination for every row.
        $columns = app(\App\Sync\ShapeRegistry::class)->find('pallets')->columns();

        $this->assertContains('destination', $columns);
        $this->assertContains('arrived_at', $columns);

        // Destination changes ride the movement ledger, so the history table
        // needs them on its shape too.
        $movementColumns = app(\App\Sync\ShapeRegistry::class)->find('pallet_movements')->columns();
        $this->assertContains('from_destination', $movementColumns);
        $this->assertContains('to_destination', $movementColumns);
    }

    public function test_admin_can_edit_a_pallet_destination_directly(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-01', 'destination' => null]);

        $this->actingAs($this->admin)->put(route('admin.pallets.update', $pallet), [
            'work_order_id' => $pallet->work_order_id,
            'qty' => $pallet->qty,
            'status' => $pallet->status->value,
            'location' => 'A-01',
            'destination' => 'DOCK-04',
        ])->assertRedirect(route('admin.pallets.index'));

        $this->assertSame('DOCK-04', $pallet->fresh()->destination);
    }

    public function test_history_survives_a_pallets_full_journey(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'LINE-1', 'destination' => null]);

        // Assigned a target, moved once on the way, then delivered.
        $this->actingAs($this->operator)->post(route('logistics.pallets.destination'), [
            'pallet_id' => $pallet->id,
            'destination' => 'DOCK-01',
        ]);
        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'BUFFER-3',
        ]);
        $this->actingAs($this->operator)->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id,
            'worker_id' => $this->driver->id,
            'to_location' => 'DOCK-01',
        ]);

        $trail = PalletMovement::where('pallet_id', $pallet->id)->orderBy('id')->get();
        $this->assertCount(3, $trail, 'Re-route and both moves are all on the ledger.');

        $this->assertSame(
            [['LINE-1', 'LINE-1'], ['LINE-1', 'BUFFER-3'], ['BUFFER-3', 'DOCK-01']],
            $trail->map(fn (PalletMovement $m) => [$m->from_location, $m->to_location])->all(),
        );
        $this->assertSame(
            [[null, 'DOCK-01'], ['DOCK-01', 'DOCK-01'], ['DOCK-01', null]],
            $trail->map(fn (PalletMovement $m) => [$m->from_destination, $m->to_destination])->all(),
        );

        $pallet->refresh();
        $this->assertSame('DOCK-01', $pallet->location);
        $this->assertNull($pallet->destination);
        $this->assertNotNull($pallet->arrived_at);
    }
}
