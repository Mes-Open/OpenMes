<?php

namespace Tests\Feature\Console;

use App\Models\SerialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Historical tester logs loaded once at go-live keep their verdicts, but with
 * --record-only a failed run does not block or scrap a unit that may have left
 * the plant long ago.
 */
class ImportTestStationJsonlRecordOnlyTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create();
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_fail_policy'], ['value' => json_encode('block')]);
        DB::table('system_settings')->updateOrInsert(['key' => 'unit_test_max_attempts'], ['value' => '1']);
        $this->dir = sys_get_temp_dir().'/om-jsonl-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/fail.jsonl',
            '{"t":"cycle","sn":"SN-FAIL-1","id":"R-1","start":"2025-03-10T08:15:00.000","station":"S2-01"}'."\n"
            .'{"t":"v","ts":"08:16:30.000","r":"F","fail":[3]}'."\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/fail.jsonl');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_record_only_keeps_a_failed_verdict_without_blocking_the_unit(): void
    {
        $this->artisan('traceability:import-jsonl', ['paths' => [$this->dir], '--record-only' => true])->assertSuccessful();

        $unit = SerialUnit::where('serial_no', 'SN-FAIL-1')->firstOrFail();
        $this->assertSame(SerialUnit::STATUS_IN_PRODUCTION, $unit->status);
        $this->assertSame('fail', $unit->history()->first()->result);
    }

    public function test_without_the_option_the_fail_policy_applies(): void
    {
        $this->artisan('traceability:import-jsonl', ['paths' => [$this->dir]])->assertSuccessful();

        $this->assertSame(SerialUnit::STATUS_BLOCKED, SerialUnit::where('serial_no', 'SN-FAIL-1')->value('status'));
    }
}
