<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Pallet;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use App\Support\UnitSerialisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A non-conforming unit is held with an error code: nothing downstream takes
 * it, a passing retest does not lift it, and only a supervisor releases it.
 */
class UnitHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $supervisor;

    private ScrapReason $reason;

    private SerialUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Supervisor', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->reason = ScrapReason::factory()->create(['code' => 'NC-01', 'name' => 'Cosmetic defect', 'is_active' => true]);
        $this->unit = SerialUnit::create(['serial_no' => 'SN-HOLD-1', 'psn' => 'P-HOLD-1']);
    }

    private function block(?User $user = null, array $body = [])
    {
        return $this->actingAs($user ?? $this->operator)->postJson(route('operator.unit-labels.block', $this->unit), $body + ['scrap_reason_id' => $this->reason->id, 'note' => 'Scratch on the cover']);
    }

    public function test_an_operator_holds_a_unit_with_an_error_code(): void
    {
        $this->postJson(route('operator.unit-labels.block', $this->unit), ['scrap_reason_id' => $this->reason->id])->assertUnauthorized();

        $this->block()->assertOk()->assertJsonPath('unit.status', SerialUnit::STATUS_BLOCKED)
            ->assertJsonPath('unit.hold.source', 'manual')->assertJsonPath('unit.hold.reason_code', 'NC-01');
        $event = $this->unit->history()->reorder('id', 'desc')->first();
        $this->assertSame('blocked', $event->parameters['event']);
        $this->assertSame('NC-01', $event->parameters['reason_code']);
        $this->assertSame('Scratch on the cover', $event->notes);

        // Twice is refused; so is a code that is missing or switched off.
        $this->block()->assertStatus(422);
        $other = SerialUnit::create(['serial_no' => 'SN-HOLD-2']);
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.block', $other), [])->assertStatus(422)->assertJsonValidationErrors('scrap_reason_id');
        $off = ScrapReason::factory()->create(['code' => 'OLD', 'is_active' => false]);
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.block', $other), ['scrap_reason_id' => $off->id])->assertStatus(422);
    }

    public function test_a_passing_test_does_not_lift_a_hand_made_hold_but_lifts_a_test_hold(): void
    {
        $this->block()->assertOk();
        $svc = app(SerialTraceService::class);
        $svc->recordStep($this->unit->fresh(), $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $this->unit->fresh()->status);

        // The tests' own hold: the default policy blocks on the first fail, a pass lifts it.
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode(UnitSerialisation::FAIL_BLOCK)]);
        app(UnitSerialisation::class)->forget();
        $tested = SerialUnit::create(['serial_no' => 'SN-TEST-1']);
        $svc->recordStep($tested, $this->operator, null, ['result' => 'fail', 'parameters' => ['event' => 'test']]);
        $this->assertSame('test', $tested->fresh()->extra_data['hold']['source']);
        $svc->recordStep($tested->fresh(), $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $tested->fresh()->status);

        // Both moments read in the history, each right after the verdict that caused it.
        $events = $tested->fresh()->history->map(fn ($h) => ($h->result ?? '-').':'.$h->parameters['event'])->all();
        $this->assertSame(['fail:test', '-:blocked', 'pass:test', '-:unblocked'], $events);
        $blocked = $tested->history()->where('parameters->event', 'blocked')->first();
        $this->assertSame(['event' => 'blocked', 'hold_source' => 'test', 'failed_attempts' => 1, 'max_attempts' => 1], $blocked->parameters);
        $this->assertSame('test', $tested->history()->where('parameters->event', 'unblocked')->first()->parameters['released_by']);

        // A further fail on a unit already held adds no second "blocked".
        $svc->recordStep($tested->fresh(), $this->operator, null, ['result' => 'fail', 'parameters' => ['event' => 'test']]);
        $svc->recordStep($tested->fresh(), $this->operator, null, ['result' => 'fail', 'parameters' => ['event' => 'test']]);
        $this->assertSame(2, $tested->history()->where('parameters->event', 'blocked')->count());
    }

    public function test_only_a_supervisor_releases_a_unit_and_it_goes_back_where_it_was(): void
    {
        $this->unit->update(['packed_at' => now(), 'status' => SerialUnit::STATUS_COMPLETED]);
        $this->block()->assertOk();

        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.unblock', $this->unit), ['note' => 'Checked'])->assertForbidden();
        $this->actingAs($this->supervisor)->postJson(route('operator.unit-labels.unblock', $this->unit), [])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->actingAs($this->supervisor)->postJson(route('operator.unit-labels.unblock', $this->unit), ['note' => 'Rework done, OK'])
            ->assertOk()->assertJsonPath('unit.status', SerialUnit::STATUS_COMPLETED)->assertJsonPath('unit.hold', null);
        $this->assertSame('unblocked', $this->unit->history()->reorder('id', 'desc')->first()->parameters['event']);
        $this->actingAs($this->supervisor)->postJson(route('operator.unit-labels.unblock', $this->unit), ['note' => 'again'])->assertStatus(422);
    }

    public function test_a_held_unit_is_not_packed_and_fails_its_pallet(): void
    {
        $wo = WorkOrder::factory()->create();
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 1]);
        $this->unit->update(['pallet_id' => $pallet->id, 'work_order_id' => $wo->id]);
        app(SerialTraceService::class)->recordStep($this->unit, $this->operator, null, ['result' => 'pass', 'parameters' => ['event' => 'test']]);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);

        $this->block()->assertOk();
        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);
        $other = SerialUnit::create(['serial_no' => 'SN-HOLD-3', 'psn' => 'P-HOLD-3']);
        app(SerialTraceService::class)->blockUnit($other, $this->operator, $this->reason);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-HOLD-3'])->assertStatus(422);
    }

    public function test_a_held_unit_does_not_move_on_to_its_label_until_it_is_released(): void
    {
        $this->block()->assertOk();
        $before = $this->unit->history()->count();

        // The label station refuses it and writes nothing: no "label applied" on a held unit.
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-HOLD-1', 'psn' => 'P-HOLD-1'])
            ->assertStatus(422)->assertJsonPath('message', 'Unit SN-HOLD-1 is blocked - it cannot move on until it passes a retest or a supervisor releases it.');
        $this->assertSame($before, $this->unit->history()->count());

        // Neither does a unit still waiting for its serial number get one while held.
        $waiting = SerialUnit::create(['psn' => 'P-HOLD-4']);
        app(SerialTraceService::class)->blockUnit($waiting, $this->operator, $this->reason);
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-HOLD-4', 'psn' => 'P-HOLD-4'])->assertStatus(422);
        $this->assertNull($waiting->fresh()->serial_no);

        $this->actingAs($this->supervisor)->postJson(route('operator.unit-labels.unblock', $this->unit), ['note' => 'Rework done, OK'])->assertOk();
        $this->actingAs($this->operator)->postJson(route('operator.unit-labels.apply'), ['serial_no' => 'SN-HOLD-1', 'psn' => 'P-HOLD-1'])->assertOk();
    }

    public function test_the_api_holds_and_releases_too(): void
    {
        $this->actingAs($this->operator)->postJson("/api/v1/serial-units/{$this->unit->id}/block", ['scrap_reason_id' => $this->reason->id])
            ->assertOk()->assertJsonPath('data.status', SerialUnit::STATUS_BLOCKED);
        $this->actingAs($this->operator)->postJson("/api/v1/serial-units/{$this->unit->id}/unblock", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->supervisor)->postJson("/api/v1/serial-units/{$this->unit->id}/unblock", ['note' => 'Released after check'])
            ->assertOk()->assertJsonPath('data.status', SerialUnit::STATUS_IN_PRODUCTION);
    }
}
