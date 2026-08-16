<?php

namespace Tests\Feature\Machine;

use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ModbusConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `modbus:poll --connection=` accepts a name as well as an id.
 *
 * A container declared in docker-compose cannot know the auto-increment id a
 * seeder will hand out, so the simulator services address their connection by
 * the name it was seeded with.
 */
class ModbusPollConnectionResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function connection(string $name): MachineConnection
    {
        $connection = MachineConnection::create([
            'name' => $name,
            'protocol' => 'modbus',
            'is_active' => true,
        ]);

        ModbusConnection::create([
            'machine_connection_id' => $connection->id,
            // Unroutable on purpose: resolution is what's under test, and the
            // poll loop is expected to fail to connect immediately after.
            'host' => '127.0.0.1',
            'port' => 1,
            'poll_interval_ms' => 1000,
            'timeout_seconds' => 1,
        ]);

        return $connection;
    }

    public function test_a_connection_resolves_by_name(): void
    {
        $this->connection('SIM-DTG-1');

        // The "Polling <name>" line is printed only once the connection has
        // been resolved, so it is the signal that resolution worked. What the
        // run then does against an unreachable host is a separate concern.
        $this->artisan('modbus:poll', ['--connection' => 'SIM-DTG-1', '--once' => true])
            ->expectsOutputToContain('Polling SIM-DTG-1');
    }

    public function test_a_connection_still_resolves_by_id(): void
    {
        $connection = $this->connection('SIM-DTG-2');

        $this->artisan('modbus:poll', ['--connection' => (string) $connection->id, '--once' => true])
            ->expectsOutputToContain('Polling SIM-DTG-2');
    }

    public function test_only_active_tags_are_polled(): void
    {
        $connection = $this->connection('SIM-DTG-3');

        foreach ([['Good count', '1', true], ['Temperature', '3', false]] as [$name, $address, $active]) {
            MachineTag::create([
                'machine_connection_id' => $connection->id,
                'name' => $name,
                'address' => $address,
                'signal_type' => $active ? MachineTag::SIGNAL_GOOD_COUNT : MachineTag::SIGNAL_TELEMETRY,
                'data_type' => 'uint16',
                'register_type' => 'holding',
                'is_active' => $active,
            ]);
        }

        // A deactivated tag is one somebody switched off to stop it being read
        // — telemetry at one poll a second is tens of thousands of rows a shift.
        $this->artisan('modbus:poll', ['--connection' => 'SIM-DTG-3', '--once' => true])
            ->expectsOutputToContain('1 tags');
    }

    public function test_an_inactive_connection_is_not_polled(): void
    {
        $connection = $this->connection('SIM-DTG-4');
        $connection->update(['is_active' => false]);

        // Taking a machine down for maintenance means switching its connection
        // off in Admin → Connectivity. A poller that keeps ingesting afterwards
        // draws RUNNING on the shift monitor for a machine that is supposed to
        // be offline, and the maintenance stop never appears as downtime.
        $this->artisan('modbus:poll', ['--connection' => 'SIM-DTG-4', '--once' => true])
            ->expectsOutputToContain('is not active')
            ->assertFailed();
    }

    public function test_an_unknown_handle_is_reported_rather_than_polled(): void
    {
        $this->artisan('modbus:poll', ['--connection' => 'SIM-NOPE', '--once' => true])
            ->expectsOutputToContain('Modbus connection not found.')
            ->assertFailed();
    }
}
