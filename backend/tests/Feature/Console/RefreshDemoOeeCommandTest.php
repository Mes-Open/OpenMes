<?php

namespace Tests\Feature\Console;

use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshDemoOeeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_when_demo_mode_is_off(): void
    {
        config(['openmmes.demo_mode' => false]);

        $this->artisan('demo:refresh-oee')
            ->expectsOutputToContain('Demo mode is off')
            ->assertExitCode(0);

        $this->assertSame(0, OeeRecord::count(), 'Nothing should be seeded when demo mode is off.');
    }

    public function test_force_runs_even_when_demo_mode_is_off(): void
    {
        config(['openmmes.demo_mode' => false]);
        User::factory()->create();
        Line::factory()->create(['is_active' => true]);

        $this->artisan('demo:refresh-oee', ['--force' => true])->assertExitCode(0);

        $this->assertGreaterThan(0, OeeRecord::count(), 'Forcing should roll OEE data forward even with demo mode off.');
    }

    public function test_it_runs_when_demo_mode_is_on(): void
    {
        config(['openmmes.demo_mode' => true]);
        User::factory()->create();
        Line::factory()->create(['is_active' => true]);

        $this->artisan('demo:refresh-oee')->assertExitCode(0);

        // Today must have OEE rows so the report isn't N/A.
        $this->assertTrue(
            OeeRecord::whereDate('record_date', now()->toDateString())->exists(),
            'Today should have OEE records after a refresh.'
        );
    }
}
