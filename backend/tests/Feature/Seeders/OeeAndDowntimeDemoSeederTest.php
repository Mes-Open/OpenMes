<?php

namespace Tests\Feature\Seeders;

use App\Enums\DowntimeKind;
use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\User;
use Database\Seeders\OeeAndDowntimeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for issue #73: the demo seeder inserted downtime reasons
 * with the removed `is_planned` column (replaced by `kind`), so it crashed with
 * an "undefined column" error. It must now seed using the current schema.
 */
class OeeAndDowntimeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_and_uses_kind_column(): void
    {
        Line::factory()->count(2)->create();
        User::factory()->create();

        // Would throw SQLSTATE[42703] undefined column "is_planned" before the fix.
        $this->seed(OeeAndDowntimeDemoSeeder::class);

        $this->assertDatabaseHas('downtime_reasons', ['code' => 'MAINT_PLANNED', 'kind' => DowntimeKind::Planned->value]);
        $this->assertDatabaseHas('downtime_reasons', ['code' => 'TOOL_CHANGE', 'kind' => DowntimeKind::Changeover->value]);
        $this->assertDatabaseHas('downtime_reasons', ['code' => 'MACH_BREAK', 'kind' => DowntimeKind::Unplanned->value]);

        // The downstream OEE/downtime data also seeds (proves the run completed).
        $this->assertGreaterThan(0, OeeRecord::count());
    }
}
