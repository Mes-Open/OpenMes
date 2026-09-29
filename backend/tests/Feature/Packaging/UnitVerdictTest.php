<?php

namespace Tests\Feature\Packaging;

use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** What a test verdict does to a unit and to the pallet it sits on. */
class UnitVerdictTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode('block')]);
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_max_attempts'], ['value' => '1']);
    }

    private function verdict(SerialUnit $unit, string $result, int $minutesAgo): void
    {
        app(SerialTraceService::class)->recordStep($unit->fresh(), $this->operator, null, [
            'result' => $result,
            'parameters' => ['event' => 'test', 'station' => 'EOL'],
            'processed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    /** A verdict from a given test station, at a given time. */
    private function verdictAt(SerialUnit $unit, string $result, \App\Models\Workstation $station, int $minutesAgo): void
    {
        app(SerialTraceService::class)->recordStep($unit->fresh(), $this->operator, null, [
            'result' => $result,
            'workstation_id' => $station->id,
            'parameters' => ['event' => 'test', 'station' => $station->code],
            'processed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_attempts_per_test_count_one_station_since_its_last_pass(): void
    {
        foreach (['unit_test_max_attempts' => 3, 'unit_test_attempts_scope' => 'test'] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
        $first = \App\Models\Workstation::factory()->create(['code' => 'TS-1', 'line_id' => $this->wo->line_id ?? \App\Models\Line::factory()->create()->id]);
        $second = \App\Models\Workstation::factory()->create(['code' => 'TS-2', 'line_id' => $first->line_id]);
        $unit = SerialUnit::create(['serial_no' => 'SN-T', 'psn' => 'P-T', 'work_order_id' => $this->wo->id]);

        // Three failures, but spread over two tests: no test reached its limit.
        $this->verdictAt($unit, 'fail', $first, 50);
        $this->verdictAt($unit, 'fail', $second, 45);
        $this->verdictAt($unit, 'fail', $second, 40);
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);

        // A pass on the second test starts its count over.
        $this->verdictAt($unit, 'pass', $second, 35);
        $this->verdictAt($unit, 'fail', $second, 30);
        $this->verdictAt($unit, 'fail', $second, 25);
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);

        // The third failure of the same test since its pass holds the unit.
        $this->verdictAt($unit, 'fail', $second, 20);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);
    }

    /** A verdict from a tester the plant has no workstation for: known only by its station code. */
    private function verdictFrom(SerialUnit $unit, string $result, string $station, int $minutesAgo): void
    {
        app(SerialTraceService::class)->recordStep($unit->fresh(), $this->operator, null, [
            'result' => $result,
            'workstation_id' => null,
            'parameters' => ['event' => 'test', 'station' => $station],
            'processed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_per_test_counting_tells_testers_apart_by_station_code_and_only_a_newer_pass_of_that_test_lifts_the_hold(): void
    {
        foreach (['unit_test_max_attempts' => 3, 'unit_test_attempts_scope' => 'test'] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
        $unit = SerialUnit::create(['serial_no' => 'SN-S', 'psn' => 'P-S', 'work_order_id' => $this->wo->id]);

        // Another tester's pass does not reset this one's count.
        $this->verdictFrom($unit, 'fail', 'ICT-01', 60);
        $this->verdictFrom($unit, 'fail', 'ICT-01', 55);
        $this->verdictFrom($unit, 'pass', 'FCT-02', 50);
        $this->verdictFrom($unit, 'fail', 'ICT-01', 45);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);

        // Neither another test's pass nor an old log of this one lifts the hold ...
        $this->verdictFrom($unit, 'pass', 'FCT-02', 40);
        $this->verdictFrom($unit, 'pass', 'ICT-01', 70);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);

        // ... a newer pass of the same test does.
        $this->verdictFrom($unit, 'pass', 'ICT-01', 30);
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);
    }

    public function test_the_scrap_policy_leaves_a_unit_that_already_left_alone(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode('scrap')]);
        $unit = SerialUnit::create(['serial_no' => 'SN-G', 'psn' => 'P-G', 'work_order_id' => $this->wo->id, 'status' => SerialUnit::STATUS_SHIPPED]);

        $this->verdict($unit, 'fail', 1);

        $this->assertSame(SerialUnit::STATUS_SHIPPED, $unit->fresh()->status);
    }

    public function test_attempts_per_unit_count_every_failure_by_default(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_max_attempts'], ['value' => '3']);
        $first = \App\Models\Workstation::factory()->create(['code' => 'TS-1', 'line_id' => $this->wo->line_id ?? \App\Models\Line::factory()->create()->id]);
        $second = \App\Models\Workstation::factory()->create(['code' => 'TS-2', 'line_id' => $first->line_id]);
        $unit = SerialUnit::create(['serial_no' => 'SN-U', 'psn' => 'P-U', 'work_order_id' => $this->wo->id]);

        $this->verdictAt($unit, 'fail', $first, 50);
        $this->verdictAt($unit, 'pass', $first, 45);
        $this->verdictAt($unit, 'fail', $second, 40);
        $this->verdictAt($unit, 'fail', $second, 35);

        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);
    }

    public function test_the_settings_form_saves_the_attempt_scope(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $form = ['production_period' => 'none', 'workflow_mode' => 'status', 'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1, 'schedule_horizon_weeks' => 6, 'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation', 'production_qty_edit_policy' => 'none', 'scanner_mode' => 'hid'];

        $this->actingAs($admin)->post('/settings/system', $form + ['unit_test_attempts_scope' => 'sometimes'])->assertSessionHasErrors('unit_test_attempts_scope');
        $this->actingAs($admin)->post('/settings/system', $form + ['unit_test_attempts_scope' => 'test'])->assertSessionHasNoErrors();
        $this->assertSame('"test"', DB::table('system_settings')->where('key', 'unit_test_attempts_scope')->value('value'));
    }

    public function test_a_pass_after_a_block_lifts_the_hold(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-1', 'psn' => 'P-1', 'work_order_id' => $this->wo->id]);

        $this->verdict($unit, 'fail', 10);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);

        $this->verdict($unit, 'pass', 5);
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->fresh()->status);

        // The hold is a retest gate, not a permanent mark: it can be packed now.
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-1'])->assertOk();
    }

    public function test_a_packed_unit_that_is_unblocked_stays_packed(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-3', 'psn' => 'P-3', 'work_order_id' => $this->wo->id]);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-3'])->assertOk();
        $this->assertSame(SerialUnit::STATUS_COMPLETED, $unit->fresh()->status);

        $this->verdict($unit, 'fail', 5);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);
        $this->verdict($unit, 'pass', 1);
        $this->assertSame(SerialUnit::STATUS_COMPLETED, $unit->fresh()->status, 'the block lifts back to the packed state, not to production');
    }

    public function test_a_testers_utc_stamp_is_stored_in_the_plants_timezone(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);
        $unit = SerialUnit::create(['serial_no' => 'SN-4', 'psn' => 'P-4', 'work_order_id' => $this->wo->id]);
        app(SerialTraceService::class)->recordStep($unit, $this->operator, null, [
            'result' => 'pass', 'parameters' => ['event' => 'test'],
            'processed_at' => \Carbon\CarbonImmutable::parse('2026-02-20T09:30:04Z'),
        ]);

        $stored = DB::table('unit_step_history')->where('serial_unit_id', $unit->id)->value('processed_at');
        $this->assertStringStartsWith('2026-02-20 10:30:04', (string) $stored);
    }

    public function test_the_pallet_quality_follows_each_units_latest_verdict(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode('record')]);
        $pallet = Pallet::create(['work_order_id' => $this->wo->id, 'status' => 'open', 'qty' => 0]);
        $unit = SerialUnit::create(['serial_no' => 'SN-2', 'psn' => 'P-2', 'work_order_id' => $this->wo->id]);

        // Passed at first, failed later: the pallet must not ship it.
        $this->verdict($unit, 'pass', 20);
        $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => 'P-2', 'pallet_id' => $pallet->id])->assertOk();
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);

        $this->verdict($unit, 'fail', 1);
        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);

        // Failed at first, passed on the retest: the pallet may ship.
        $this->verdict($unit, 'pass', 0);
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
    }
}
