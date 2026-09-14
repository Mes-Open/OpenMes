<?php

namespace Tests\Feature\Web\Admin;

use App\Events\CollectionChanged;
use App\Models\ProductType;
use App\Models\SerialSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin CRUD for Serial Sequences (#290) — parallel to the LOT Sequences admin page. */
class SerialSequenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_serial_sequences(): void
    {
        SerialSequence::factory()->create(['name' => 'Default Serials']);

        $response = $this->actingAs($this->admin)->get(route('admin.serial-sequences.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_a_simple_prefix_sequence(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'Solenoid Serials',
            'prefix' => 'SN',
            'pad_size' => 4,
            'year_prefix' => '1',
        ]);

        $response->assertRedirect(route('admin.serial-sequences.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('serial_sequences', [
            'name' => 'Solenoid Serials',
            'prefix' => 'SN',
            'pad_size' => 4,
            'year_prefix' => true,
            'next_number' => 1,
        ]);
    }

    public function test_admin_can_create_a_pattern_sequence(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'Token Pattern Serials',
            'pattern' => 'SN-[date]-[seq]',
            'pad_size' => 5,
        ]);

        $response->assertRedirect(route('admin.serial-sequences.index'));
        $this->assertDatabaseHas('serial_sequences', [
            'name' => 'Token Pattern Serials',
            'pattern' => 'SN-[date]-[seq]',
            'prefix' => '', // legacy column stays NOT NULL, unused in pattern mode
        ]);
    }

    public function test_invalid_pattern_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'Bad Pattern',
            'pattern' => 'SN-no-seq-token',
        ]);

        $response->assertSessionHasErrors('pattern');
        $this->assertDatabaseMissing('serial_sequences', ['name' => 'Bad Pattern']);
    }

    public function test_prefix_is_required_without_a_pattern(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'No Prefix No Pattern',
        ]);

        $response->assertSessionHasErrors('prefix');
    }

    public function test_at_most_one_sequence_per_product_type(): void
    {
        $productType = ProductType::factory()->create();
        SerialSequence::factory()->forProductType($productType)->create();

        $response = $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'Duplicate',
            'prefix' => 'DUP',
            'product_type_id' => $productType->id,
        ]);

        $response->assertSessionHasErrors('product_type_id');
    }

    public function test_admin_can_update_a_sequence(): void
    {
        $sequence = SerialSequence::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin)->put(route('admin.serial-sequences.update', $sequence), [
            'name' => 'New Name',
            'prefix' => $sequence->prefix,
        ]);

        $response->assertRedirect(route('admin.serial-sequences.index'));
        $this->assertDatabaseHas('serial_sequences', ['id' => $sequence->id, 'name' => 'New Name']);
    }

    /**
     * The exact transition reported as still broken after the broadcast fix:
     * an EXISTING simple/prefix-mode row switched to Pattern mode. The React
     * form's tab switch doesn't clear `prefix` when moving to Pattern mode
     * (only leaving Pattern clears `pattern`), so the real request still
     * carries the row's old prefix value alongside the newly typed pattern —
     * reproduced here exactly as the browser would send it.
     */
    public function test_admin_can_switch_an_existing_simple_sequence_to_pattern_mode(): void
    {
        $sequence = SerialSequence::factory()->create(['prefix' => 'SN', 'pattern' => null]);

        $response = $this->actingAs($this->admin)->put(route('admin.serial-sequences.update', $sequence), [
            'name' => $sequence->name,
            'pattern' => 'SN-[date]-[seq]',
            'prefix' => $sequence->prefix, // stale leftover, not cleared by the tab switch
            'pad_size' => 4,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('serial_sequences', [
            'id' => $sequence->id,
            'pattern' => 'SN-[date]-[seq]',
        ]);
    }

    public function test_update_with_stay_redirects_back_instead_of_to_index(): void
    {
        $sequence = SerialSequence::factory()->create();

        $response = $this->actingAs($this->admin)->from('/admin/serial-sequences')->put(route('admin.serial-sequences.update', $sequence), [
            'name' => 'Renamed In Drawer',
            'prefix' => $sequence->prefix,
            'stay' => 1,
        ]);

        $response->assertRedirect('/admin/serial-sequences');
    }

    public function test_admin_can_delete_an_unused_sequence(): void
    {
        $sequence = SerialSequence::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.serial-sequences.destroy', $sequence));

        $this->assertSoftDeleted('serial_sequences', ['id' => $sequence->id]);
    }

    public function test_preview_renders_a_pattern_without_persisting(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.serial-sequences.preview'), [
            'pattern' => 'SN-[seq]',
            'pad_size' => 3,
        ]);

        $response->assertOk()->assertJson(['preview' => 'SN-001']);
        $this->assertDatabaseCount('serial_sequences', 0);
    }

    public function test_non_admin_cannot_manage_serial_sequences(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.serial-sequences.index'))->assertForbidden();
    }

    public function test_guest_cannot_manage_serial_sequences(): void
    {
        $this->get(route('admin.serial-sequences.index'))->assertRedirect(route('login'));
    }

    /**
     * Regression for known-bugs item 1: the DB layer always persisted `pattern`
     * correctly, but serial_sequences was never registered in
     * CollectionBroadcaster::map() — only in ShapeRegistry (the read path) — so
     * no CollectionChanged delta ever fired on save. The browser's live-synced
     * table row (and the drawer reopened from it) stayed on stale pre-save data
     * forever, which is what actually looked like "pattern mode doesn't stick".
     */
    public function test_creating_a_sequence_broadcasts_a_collection_change_with_the_pattern(): void
    {
        $captured = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$captured) {
            $captured[] = $e;
        });

        $this->actingAs($this->admin)->post(route('admin.serial-sequences.store'), [
            'name' => 'Broadcast Probe',
            'pattern' => 'BP-[date]-[seq]',
        ]);

        $events = collect($captured)->filter(fn ($e) => $e->collection === 'serial_sequences');
        $this->assertGreaterThan(0, $events->count(), 'Expected a CollectionChanged broadcast for serial_sequences.');
        $this->assertTrue(
            $events->contains(fn ($e) => ($e->row['pattern'] ?? null) === 'BP-[date]-[seq]'),
            'Expected the broadcast row to carry the saved pattern.'
        );
    }
}
