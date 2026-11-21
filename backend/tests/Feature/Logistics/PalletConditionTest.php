<?php

namespace Tests\Feature\Logistics;

use App\Enums\PalletCondition;
use App\Models\Pallet;
use App\Models\PalletConditionEvent;
use App\Models\PalletMovement;
use App\Models\User;
use App\Models\Worker;
use App\Services\Logistics\PalletConditionService;
use App\Services\Traceability\TraceabilityService;
use App\Sync\ShapeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Physical condition of a pallet (damaged wrap, crushed cartons), recorded by
 * the warehouse on receipt and on moves, and restored on the pallet screen.
 * Separate from the quality status QC derives from its checks.
 */
class PalletConditionTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function move(Pallet $pallet, array $extra = [], ?User $as = null)
    {
        $worker = Worker::factory()->logistics()->create();

        return $this->actingAs($as ?? $this->operator)->post(route('logistics.movements.store'), $extra + [
            'pallet_id' => $pallet->id,
            'worker_id' => $worker->id,
            'to_location' => 'WH-01',
        ]);
    }

    // ── Defaults ───────────────────────────────────────────────────────────

    public function test_a_new_pallet_is_in_good_condition(): void
    {
        $pallet = Pallet::factory()->create()->fresh();

        $this->assertSame(PalletCondition::Good, $pallet->condition);
        $this->assertNull($pallet->condition_note);
        $this->assertFalse($pallet->isDamaged());
    }

    // ── Move terminal ──────────────────────────────────────────────────────

    public function test_the_first_move_records_damage_as_the_warehouse_receipt(): void
    {
        Carbon::setTestNow('2026-11-21 08:15:00');
        $pallet = Pallet::factory()->closed()->create(['location' => 'LINE-1', 'quality_status' => Pallet::QUALITY_PASS]);

        $this->move($pallet, ['damaged' => true, 'condition_note' => 'Torn stretch wrap'])
            ->assertRedirect(route('logistics.move-pallet'))
            ->assertSessionHasNoErrors();

        $pallet->refresh();
        $this->assertSame(PalletCondition::Damaged, $pallet->condition);
        $this->assertSame('Torn stretch wrap', $pallet->condition_note);
        $this->assertSame($this->operator->id, $pallet->condition_recorded_by_id);
        $this->assertSame('2026-11-21 08:15:00', $pallet->condition_recorded_at->format('Y-m-d H:i:s'));
        // Quality is QC's verdict and is not touched by the physical state.
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->quality_status);

        $movement = PalletMovement::sole();
        $event = PalletConditionEvent::sole();
        $this->assertSame('damaged', $event->condition);
        $this->assertSame('Torn stretch wrap', $event->note);
        $this->assertSame(PalletConditionEvent::SOURCE_RECEIPT, $event->source);
        $this->assertSame($movement->id, $event->pallet_movement_id);
        $this->assertSame($this->operator->id, $event->user_id);
    }

    public function test_damage_found_on_a_later_move_is_recorded_as_a_move(): void
    {
        $pallet = Pallet::factory()->closed()->create(['location' => 'LINE-1']);
        $this->move($pallet);

        $this->move($pallet, ['to_location' => 'DOCK-2', 'damaged' => '1', 'condition_note' => 'Crushed corner cartons'])
            ->assertSessionHasNoErrors();

        $event = PalletConditionEvent::sole();
        $this->assertSame(PalletConditionEvent::SOURCE_MOVE, $event->source);
        $this->assertSame(PalletMovement::latest('id')->first()->id, $event->pallet_movement_id);
        $this->assertTrue($pallet->fresh()->isDamaged());
    }

    public function test_marking_damaged_on_the_terminal_needs_a_note_and_books_nothing_without_one(): void
    {
        $pallet = Pallet::factory()->closed()->create(['location' => 'LINE-1']);

        $this->move($pallet, ['damaged' => true, 'condition_note' => '  '])
            ->assertSessionHasErrors('condition_note');

        $this->assertDatabaseCount('pallet_movements', 0);
        $this->assertDatabaseCount('pallet_condition_events', 0);
        $this->assertSame('LINE-1', $pallet->fresh()->location);
    }

    public function test_a_plain_move_neither_records_nor_clears_the_condition(): void
    {
        $pallet = Pallet::factory()->closed()->create(['location' => 'LINE-1']);
        app(PalletConditionService::class)->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->admin, PalletConditionEvent::SOURCE_MANUAL);

        $this->move($pallet, ['damaged' => false])->assertSessionHasNoErrors();

        $this->assertTrue($pallet->fresh()->isDamaged());
        $this->assertSame('Torn wrap', $pallet->fresh()->condition_note);
        $this->assertSame(1, PalletConditionEvent::count());
    }

    public function test_the_terminal_lists_each_pallet_with_its_condition(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        app(PalletConditionService::class)->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->admin, PalletConditionEvent::SOURCE_MANUAL);

        $this->actingAs($this->operator)->get(route('logistics.move-pallet'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('logistics/MovePallet')
                ->where('pallets.0.id', $pallet->id)
                ->where('pallets.0.condition', 'damaged')
                ->where('pallets.0.condition_note', 'Torn wrap'));
    }

    // ── Record condition on the pallet screen ──────────────────────────────

    public function test_admin_marks_a_pallet_damaged_and_restores_it(): void
    {
        $pallet = Pallet::factory()->closed()->create(['quality_status' => Pallet::QUALITY_PASS]);

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => 'Forklift hit the side'])
            ->assertRedirect(route('admin.pallets.index'))
            ->assertSessionHas('success');
        $this->assertTrue($pallet->fresh()->isDamaged());

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'good', 'note' => 'Rewrapped, cartons checked'])
            ->assertSessionHasNoErrors();

        $pallet->refresh();
        $this->assertSame(PalletCondition::Good, $pallet->condition);
        $this->assertSame('Rewrapped, cartons checked', $pallet->condition_note);
        $this->assertSame($this->admin->id, $pallet->condition_recorded_by_id);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->quality_status);

        $events = PalletConditionEvent::orderBy('id')->get();
        $this->assertSame(['damaged', 'good'], $events->pluck('condition')->all());
        $this->assertSame([PalletConditionEvent::SOURCE_MANUAL, PalletConditionEvent::SOURCE_MANUAL], $events->pluck('source')->all());
        $this->assertNull($events->last()->pallet_movement_id);
    }

    public function test_a_note_is_required_both_to_mark_damaged_and_to_restore(): void
    {
        $pallet = Pallet::factory()->closed()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertFalse($pallet->fresh()->isDamaged());

        app(PalletConditionService::class)->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->admin, PalletConditionEvent::SOURCE_MANUAL);
        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'good'])
            ->assertSessionHasErrors('note');
        $this->assertTrue($pallet->fresh()->isDamaged());
    }

    public function test_an_unknown_condition_is_rejected(): void
    {
        $pallet = Pallet::factory()->closed()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'wet', 'note' => 'x'])
            ->assertSessionHasErrors('condition');
        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_restoring_a_pallet_that_is_already_good_is_rejected(): void
    {
        $pallet = Pallet::factory()->closed()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'good', 'note' => 'Looks fine'])
            ->assertSessionHasErrors('condition');
        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_a_shipped_pallet_condition_cannot_be_recorded(): void
    {
        $pallet = Pallet::factory()->shipped()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => 'Too late'])
            ->assertSessionHasErrors('condition');
        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_guest_is_redirected_and_operator_is_forbidden(): void
    {
        $pallet = Pallet::factory()->closed()->create();

        $this->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => 'x'])
            ->assertRedirect();
        $this->actingAs($this->operator)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => 'x'])
            ->assertForbidden();

        $this->assertFalse($pallet->fresh()->isDamaged());
        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_condition_events_cannot_be_edited_or_deleted(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        $event = app(PalletConditionService::class)->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->operator, PalletConditionEvent::SOURCE_RECEIPT);

        foreach ([fn () => $event->update(['note' => 'Changed']), fn () => $event->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('A condition event must not change.');
            } catch (\LogicException) {
            }
        }
        $this->assertSame('Torn wrap', $event->fresh()->note);
    }

    public function test_a_supervisor_without_the_packaging_tab_is_forbidden(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('admin.pallets.condition', $pallet), ['condition' => 'damaged', 'note' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_the_service_rechecks_a_shipped_pallet_under_the_lock(): void
    {
        // The request's checks run before the lock; a shipment confirmed in
        // between must still keep the record off the shipped pallet.
        $pallet = Pallet::factory()->closed()->create();
        $stale = Pallet::find($pallet->id);
        $pallet->forceFill(['status' => \App\Enums\PalletStatus::Shipped])->saveQuietly();

        try {
            app(PalletConditionService::class)->record($stale, PalletCondition::Damaged, 'Too late', $this->admin, PalletConditionEvent::SOURCE_MANUAL);
            $this->fail('A shipped pallet must not get a condition record.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('condition', $e->errors());
        }
        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    public function test_a_second_restore_adds_no_duplicate_good_event(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        $service = app(PalletConditionService::class);
        $service->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->operator, PalletConditionEvent::SOURCE_RECEIPT);
        $stale = Pallet::find($pallet->id);
        $service->record($pallet, PalletCondition::Good, 'Rewrapped', $this->admin, PalletConditionEvent::SOURCE_MANUAL);

        try {
            $service->record($stale, PalletCondition::Good, 'Rewrapped again', $this->admin, PalletConditionEvent::SOURCE_MANUAL);
            $this->fail('A pallet already in good condition must not be restored again.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('condition', $e->errors());
        }
        $this->assertSame(1, PalletConditionEvent::where('pallet_id', $pallet->id)->where('condition', 'good')->count());
    }

    public function test_damage_on_the_terminal_for_a_shipped_pallet_records_nothing(): void
    {
        $pallet = Pallet::factory()->shipped()->create();

        $this->move($pallet, ['damaged' => true, 'condition_note' => 'Crushed'])->assertSessionHasErrors();

        $this->assertDatabaseCount('pallet_condition_events', 0);
        $this->assertFalse($pallet->fresh()->isDamaged());
    }

    public function test_guest_cannot_record_damage_on_the_terminal(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        $worker = Worker::factory()->logistics()->create();

        $this->post(route('logistics.movements.store'), [
            'pallet_id' => $pallet->id, 'worker_id' => $worker->id, 'to_location' => 'X',
            'damaged' => true, 'condition_note' => 'x',
        ])->assertRedirect();

        $this->assertDatabaseCount('pallet_condition_events', 0);
    }

    // ── Visibility ─────────────────────────────────────────────────────────

    public function test_the_trace_shows_the_condition_and_its_history(): void
    {
        $pallet = Pallet::factory()->closed()->create();
        $service = app(PalletConditionService::class);
        $service->record($pallet, PalletCondition::Damaged, 'Torn wrap', $this->operator, PalletConditionEvent::SOURCE_RECEIPT);
        $service->record($pallet->fresh(), PalletCondition::Good, 'Rewrapped', $this->admin, PalletConditionEvent::SOURCE_MANUAL);

        $data = app(TraceabilityService::class)->palletTrace($pallet->fresh());

        $this->assertSame('good', $data['pallet']['condition']);
        $this->assertSame('Rewrapped', $data['pallet']['condition_note']);
        $history = collect($data['condition_history']);
        $this->assertSame(['damaged', 'good'], $history->pluck('condition')->all());
        $this->assertSame(['receipt', 'manual'], $history->pluck('source')->all());
        $this->assertSame([$this->operator->name, $this->admin->name], $history->pluck('recorded_by')->all());
        $this->assertSame('Torn wrap', $history->first()['note']);
    }

    public function test_the_synced_pallet_collection_carries_the_condition(): void
    {
        $columns = app(ShapeRegistry::class)->find('pallets')->columns();

        foreach (['condition', 'condition_note', 'condition_recorded_by_id', 'condition_recorded_at'] as $column) {
            $this->assertContains($column, $columns);
        }
    }
}
