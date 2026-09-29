<?php

namespace Tests\Feature\Api;

use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A tester posting its runs to POST /api/v1/test-runs. */
class TestRunApiTest extends TestCase
{
    use RefreshDatabase;

    private User $tester;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->tester = User::factory()->create(['name' => 'EOL tester']);
        $this->tester->assignRole('Operator');
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode('block')]);
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_max_attempts'], ['value' => '1']);
    }

    private function asTester()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tester->createToken('tester')->plainTextToken);
    }

    /** A raw NDJSON body, the way a tester streams its log (call() takes no default headers, so the token rides in $server). */
    private function postNdjson(string $url, string $body)
    {
        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/x-ndjson',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tester->createToken('tester')->plainTextToken,
        ], $body);
    }

    public function test_the_native_json_shape_runs_the_fail_policy_and_updates_the_pallet(): void
    {
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $pallet = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 1]);
        $unit = SerialUnit::create(['serial_no' => 'SN-9', 'psn' => 'P-9', 'work_order_id' => $wo->id, 'pallet_id' => $pallet->id]);

        $this->asTester()->postJson('/api/v1/test-runs', [
            'serial_no' => 'sn-9', 'run_id' => 'R-1', 'verdict' => 'fail', 'station' => 'EOL-2',
            'ended_at' => '2026-09-25T10:00:00Z',
            'steps' => [['id' => 'L10', 'name' => 'Leak', 'verdict' => 'fail', 'measurements' => [['name' => 'p', 'value' => 0.4, 'unit' => 'bar', 'low' => 0.5]]]],
        ])->assertCreated()->assertJsonPath('data.unit.status', 'blocked');

        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);
        $this->assertSame(Pallet::QUALITY_FAIL, $pallet->fresh()->quality_status);
        $this->assertSame(['L10'], $unit->history()->first()->parameters['failed_steps']);

        // The retest passes: the block lifts and the pallet may ship again.
        $this->asTester()->postJson('/api/v1/test-runs', ['records' => [['serial_no' => 'SN-9', 'run_id' => 'R-2', 'verdict' => 'pass', 'ended_at' => '2026-09-25T10:30:00Z']]])
            ->assertCreated()->assertJsonPath('data.unit.status', 'in_production');
        $this->assertSame(Pallet::QUALITY_PASS, $pallet->fresh()->quality_status);
    }

    public function test_the_run_is_booked_on_the_token_and_an_unknown_station_stays_unknown(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['username' => 'boss']);
        $admin->assignRole('Admin');
        $bench = \App\Models\Workstation::factory()->create(['line_id' => \App\Models\Line::factory()->create()->id]);
        $this->tester->update(['workstation_id' => $bench->id]);

        // The log names the admin and a station nobody registered.
        $this->asTester()->postJson('/api/v1/test-runs', ['serial_no' => 'SN-20', 'run_id' => 'R-20', 'verdict' => 'pass', 'operator' => 'boss', 'station' => 'NOT-REGISTERED'])->assertCreated();
        $entry = SerialUnit::where('serial_no', 'SN-20')->firstOrFail()->history()->firstOrFail();
        $this->assertSame($this->tester->id, $entry->operator_id, 'a name in the log does not pick the account');
        $this->assertSame('boss', $entry->parameters['operator']);
        $this->assertNull($entry->workstation_id, "not the token user's own bench");
    }

    public function test_a_blank_serial_is_refused_rather_than_matched_to_a_waiting_unit(): void
    {
        $waiting = SerialUnit::create(['psn' => 'P-WAIT']);
        $body = '{"t":"cycle","sn":" ","id":"R-30","start":"2026-03-10T08:15:00.000","station":"TS-01"}'."\n"
            .'{"t":"v","ts":"06:14:30.000","r":"P","fail":[]}'."\n";

        $this->postNdjson('/api/v1/test-runs', $body)->assertStatus(422);
        $this->assertSame(0, $waiting->history()->count());
    }

    public function test_only_a_test_lifts_a_test_hold(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-40']);
        $this->asTester()->postJson('/api/v1/test-runs', ['serial_no' => 'SN-40', 'run_id' => 'R-40', 'verdict' => 'fail', 'ended_at' => '2026-09-25T10:00:00Z'])
            ->assertJsonPath('data.unit.status', 'blocked');

        // A step recorded as "pass" elsewhere (an inspection) is not a retest.
        app(\App\Services\Traceability\SerialTraceService::class)->recordStep($unit->fresh(), $this->tester, null, ['result' => 'pass', 'parameters' => ['event' => 'inspection']]);
        $this->assertSame(SerialUnit::STATUS_BLOCKED, $unit->fresh()->status);

        // An older pass arriving late does not undo the newer hold either.
        $this->asTester()->postJson('/api/v1/test-runs', ['serial_no' => 'SN-40', 'run_id' => 'R-39', 'verdict' => 'pass', 'ended_at' => '2026-09-25T09:00:00Z'])
            ->assertJsonPath('data.unit.status', 'blocked');
    }

    public function test_bad_input_is_refused_and_a_token_is_required(): void
    {
        $this->postJson('/api/v1/test-runs', ['serial_no' => 'SN-1', 'verdict' => 'pass'])->assertUnauthorized();

        $this->asTester()->postJson('/api/v1/test-runs', ['hello' => 'world'])->assertStatus(422);
        $this->asTester()->postJson('/api/v1/test-runs', [])->assertStatus(422);
        $this->asTester()->postJson('/api/v1/test-runs', ['serial_no' => 'SN-1', 'verdict' => 'pass', 'work_order' => 'NO-SUCH-ORDER'])->assertStatus(422)->assertJsonValidationErrors('work_order');
        $this->assertDatabaseMissing('serial_units', ['serial_no' => 'SN-1']);
    }

    public function test_units_are_looked_up_by_exact_serial_or_psn(): void
    {
        SerialUnit::create(['serial_no' => 'SN-1001-0001', 'psn' => 'P-0001']);

        $this->asTester()->getJson('/api/v1/serial-units?serial_no=sn-1001 -0001')->assertOk()->assertJsonCount(1, 'data');
        $this->asTester()->getJson('/api/v1/serial-units?psn=P-0001')->assertOk()->assertJsonPath('data.0.serial_no', 'SN-1001-0001');
        $this->asTester()->getJson('/api/v1/serial-units?psn=P-0002')->assertOk()->assertJsonCount(0, 'data');
    }
}
