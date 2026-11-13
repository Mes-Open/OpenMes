<?php

namespace Tests\Feature\Machine;

use App\Models\MachineConnection;
use App\Models\MachineCounter;
use App\Models\MachineCounterReading;
use App\Models\MachineTag;
use App\Services\Machine\MachineCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneMachineCounterReadingsTest extends TestCase
{
    use RefreshDatabase;

    private MachineCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $conn = MachineConnection::create(['name' => 'PLC', 'protocol' => 'modbus', 'is_active' => true]);
        $tag = MachineTag::create(['machine_connection_id' => $conn->id, 'name' => 'Good', 'address' => '1', 'signal_type' => 'good_count', 'is_active' => true]);
        $this->counter = app(MachineCounterService::class)->forSource($tag);
    }

    private function reading(string $status, int $daysAgo): MachineCounterReading
    {
        $reading = $this->counter->readings()->create(['status' => $status, 'observed_at' => now()->subDays($daysAgo), 'delta' => 0]);
        $reading->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $reading;
    }

    public function test_only_old_unchanged_readings_are_deleted(): void
    {
        $oldUnchanged = $this->reading('unchanged', 8);
        $recentUnchanged = $this->reading('unchanged', 2);
        $oldApplied = $this->reading('applied', 30);
        $oldReset = $this->reading('reset_required', 30);

        $this->artisan('machine-counters:prune-readings')
            ->expectsOutputToContain('Pruned 1 unchanged')
            ->assertSuccessful();

        $this->assertModelMissing($oldUnchanged);
        $this->assertModelExists($recentUnchanged);
        $this->assertModelExists($oldApplied);
        $this->assertModelExists($oldReset);
    }

    public function test_the_retention_is_configurable_and_validated(): void
    {
        $this->reading('unchanged', 2);

        $this->artisan('machine-counters:prune-readings', ['--days' => 'x'])->assertFailed();
        $this->artisan('machine-counters:prune-readings', ['--days' => 0])->assertFailed();
        $this->artisan('machine-counters:prune-readings', ['--days' => 1])->assertSuccessful();

        $this->assertSame(0, MachineCounterReading::where('status', 'unchanged')->count());
    }
}
