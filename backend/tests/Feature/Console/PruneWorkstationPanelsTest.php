<?php

namespace Tests\Feature\Console;

use App\Models\WorkstationDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * workstation-devices:prune-panels trashes operator-panel rows not seen for
 * the retention period and never touches Workstation client rows.
 */
class PruneWorkstationPanelsTest extends TestCase
{
    use RefreshDatabase;

    private function panel(array $attributes = []): WorkstationDevice
    {
        return WorkstationDevice::factory()->create($attributes + [
            'device_uuid' => WorkstationDevice::PANEL_UUID_PREFIX.fake()->uuid(),
            'name' => WorkstationDevice::DEFAULT_PANEL_NAME,
        ]);
    }

    public function test_stale_panels_are_trashed_and_recent_ones_kept(): void
    {
        config(['openmmes.workstation_panel_prune_days' => 30]);

        $stale = $this->panel(['last_seen_at' => now()->subDays(31)]);
        $recent = $this->panel(['last_seen_at' => now()->subDays(29)]);
        $neverSeen = $this->panel(['last_seen_at' => null, 'created_at' => now()->subDays(40)]);

        $this->artisan('workstation-devices:prune-panels')->assertSuccessful();

        $this->assertSoftDeleted($stale);
        $this->assertSoftDeleted($neverSeen);
        $this->assertNotSoftDeleted($recent);
    }

    public function test_workstation_client_rows_are_never_pruned(): void
    {
        $client = WorkstationDevice::factory()->create(['last_seen_at' => now()->subYear()]);

        $this->artisan('workstation-devices:prune-panels', ['--days' => 1])->assertSuccessful();

        $this->assertNotSoftDeleted($client);
    }

    public function test_days_option_overrides_config(): void
    {
        config(['openmmes.workstation_panel_prune_days' => 30]);
        $panel = $this->panel(['last_seen_at' => now()->subDays(3)]);

        $this->artisan('workstation-devices:prune-panels', ['--days' => 2])->assertSuccessful();

        $this->assertSoftDeleted($panel);
    }

    public function test_zero_days_disables_pruning(): void
    {
        config(['openmmes.workstation_panel_prune_days' => 0]);
        $panel = $this->panel(['last_seen_at' => now()->subYears(2)]);

        $this->artisan('workstation-devices:prune-panels')->assertSuccessful();

        $this->assertNotSoftDeleted($panel);
    }

    public function test_invalid_days_is_rejected(): void
    {
        $this->artisan('workstation-devices:prune-panels', ['--days' => 'abc'])->assertFailed();
        $this->artisan('workstation-devices:prune-panels', ['--days' => '-1'])->assertFailed();
    }

    public function test_malformed_config_fails_instead_of_disabling_pruning(): void
    {
        // WORKSTATION_PANEL_PRUNE_DAYS=abc reaches the command as the raw string.
        config(['openmmes.workstation_panel_prune_days' => 'abc']);
        $panel = $this->panel(['last_seen_at' => now()->subYears(2)]);

        $this->artisan('workstation-devices:prune-panels')->assertFailed();

        $this->assertNotSoftDeleted($panel);
    }

    public function test_numeric_string_config_from_env_works(): void
    {
        config(['openmmes.workstation_panel_prune_days' => '30']);
        $stale = $this->panel(['last_seen_at' => now()->subDays(31)]);

        $this->artisan('workstation-devices:prune-panels')->assertSuccessful();

        $this->assertSoftDeleted($stale);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('workstation-devices:prune-panels')
            ->assertSuccessful();
    }
}
