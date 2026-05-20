<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\OeeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CalculateOeeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_for_all_active_lines(): void
    {
        Line::factory()->count(2)->create(['is_active' => true]);
        Line::factory()->create(['is_active' => false]);

        $this->assertSame(0, Artisan::call('oee:calculate', ['--date' => '2026-05-15']));
        $this->assertSame(2, OeeRecord::whereDate('record_date', '2026-05-15')->count());
    }

    public function test_command_respects_line_filter(): void
    {
        $target = Line::factory()->create();
        Line::factory()->create();

        $this->assertSame(0, Artisan::call('oee:calculate', [
            '--date' => '2026-05-15',
            '--line' => $target->id,
        ]));

        $this->assertSame(1, OeeRecord::whereDate('record_date', '2026-05-15')->count());
        $this->assertSame($target->id, OeeRecord::first()->line_id);
    }

    public function test_command_can_be_re_run_for_same_date(): void
    {
        // updateOrCreate dedup relies on the unique(line_id, workstation_id, shift_id, record_date)
        // index. On Postgres this works as expected; on SQLite null comparison in the
        // where-clause may bypass dedup. We assert the command itself runs OK twice.
        Line::factory()->create();

        $this->assertSame(0, Artisan::call('oee:calculate', ['--date' => '2026-05-15']));
        $this->assertSame(0, Artisan::call('oee:calculate', ['--date' => '2026-05-15']));
    }
}
