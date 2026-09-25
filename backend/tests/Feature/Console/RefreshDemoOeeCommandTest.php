<?php

namespace Tests\Feature\Console;

use App\Models\Line;
use App\Models\OeeRecord;
use App\Models\User;
use App\Support\SampleDataLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_it_stands_down_while_a_sample_data_load_holds_the_lock(): void
    {
        // This reseeds the same tables the sample-data loader writes, and it
        // fires at 00:30 — a time an administrator loading an example company
        // has no reason to avoid. Two seeders into one set of tables is what
        // deadlocked the demo. The schedule's own withoutOverlapping() does not
        // help here: it guards the command against itself, not against a person.
        config(['openmmes.demo_mode' => true]);
        User::factory()->create();
        Line::factory()->create(['is_active' => true]);

        $lock = Cache::lock(SampleDataLock::KEY, SampleDataLock::TTL);
        $this->assertTrue($lock->get(), 'the test needs to hold the lock itself');

        // Exit 0, not a failure: missing one nightly refresh changes nothing
        // anybody will see, and a non-zero exit would page somebody over two
        // schedules coinciding.
        $this->artisan('demo:refresh-oee')
            ->expectsOutputToContain('skipping this refresh')
            ->assertExitCode(0);

        $lock->release();

        $this->assertSame(0, OeeRecord::count(), 'nothing may be seeded while another load holds the lock');
    }

    public function test_it_leaves_the_lock_free_for_the_next_caller(): void
    {
        // Taking the lock and keeping it would block the loader for fifteen
        // minutes every night, which is the opposite of what this guard is for.
        config(['openmmes.demo_mode' => true]);
        User::factory()->create();
        Line::factory()->create(['is_active' => true]);

        $this->artisan('demo:refresh-oee')
            ->expectsOutputToContain('Demo OEE refreshed')
            ->assertExitCode(0);

        $lock = Cache::lock(SampleDataLock::KEY, SampleDataLock::TTL);
        $this->assertTrue($lock->get(), 'a finished refresh must leave the lock free');
        $lock->release();
    }
}
