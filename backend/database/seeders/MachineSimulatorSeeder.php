<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ModbusConnection;
use App\Models\ProductionDowntime;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Database\Seeder;

/**
 * Wires the Modbus simulators into the real ingest path.
 *
 * `modbus:simulate` serves a machine; `modbus:poll` reads it and feeds
 * `MachineSignalIngestor`, which drives the state timeline and counters the
 * shift monitor is built on. What was missing between them was configuration:
 * a connection per station and the tags that say which register means what.
 *
 * Run once, then bring the containers up:
 *
 *   php artisan db:seed --class=MachineSimulatorSeeder
 *   docker compose --profile simulator up -d
 *
 * Safe to re-run — every row is keyed and updated in place.
 */
class MachineSimulatorSeeder extends Seeder
{
    /**
     * station code => [simulator host, port, nameplate parts/hour].
     *
     * The host is the compose service name; the rate is written onto the
     * workstation too, so the monitor's "target" and "speed loss" are measured
     * against what the simulator is actually configured to produce.
     *
     * @var array<string, array{host: string, port: int, rate: int}>
     */
    private const STATIONS = [
        'DTG-1' => ['host' => 'machine-sim-1', 'port' => 5020, 'rate' => 1200],
        'DTG-2' => ['host' => 'machine-sim-2', 'port' => 5020, 'rate' => 1200],
        'SITO-1' => ['host' => 'machine-sim-3', 'port' => 5020, 'rate' => 900],
    ];

    /**
     * Register map — must match ModbusSimulateCommand's backing store.
     *
     * Temperature is seeded inactive. `MachineSignalIngestor::handleTelemetry()`
     * writes a machine_event *and* updates the open state slice on every read,
     * so at one poll a second it is ~28k rows per station per shift — for a
     * signal nothing on the shift monitor consumes. Activate it in Admin →
     * Connectivity if you want to exercise the telemetry path.
     */
    private const TAGS = [
        ['name' => 'State', 'address' => '0', 'signal_type' => MachineTag::SIGNAL_STATE, 'active' => true],
        ['name' => 'Good count', 'address' => '1', 'signal_type' => MachineTag::SIGNAL_GOOD_COUNT, 'active' => true],
        ['name' => 'Reject count', 'address' => '2', 'signal_type' => MachineTag::SIGNAL_REJECT_COUNT, 'active' => true],
        ['name' => 'Temperature', 'address' => '3', 'signal_type' => MachineTag::SIGNAL_TELEMETRY, 'active' => false],
    ];

    public function run(): void
    {
        foreach (self::STATIONS as $code => $cfg) {
            $workstation = Workstation::where('code', $code)->first();

            if (! $workstation) {
                $this->command?->warn("Workstation {$code} not found — skipped.");

                continue;
            }

            // The monitor measures against this, so it has to agree with what
            // the simulator is told to produce or every hour reads as a miss.
            $workstation->update(['ideal_rate_per_hour' => $cfg['rate']]);

            $connection = $this->connectionFor($code, $cfg);
            $this->tagsFor($connection, $workstation);
            $this->clearStaleOpenState($workstation);
            $this->clearStaleOpenBatches($workstation);

            $this->command?->info(sprintf(
                '%s ← %s (%s:%d) @ %d/h · connection "%s"',
                $code, 'modbus', $cfg['host'], $cfg['port'], $cfg['rate'], $connection->name,
            ));
        }

        $this->command?->info('Now run: docker compose --profile simulator up -d');
    }

    /**
     * @param  array{host: string, port: int, rate: int}  $cfg
     */
    private function connectionFor(string $code, array $cfg): MachineConnection
    {
        // Named, not numbered: docker-compose addresses the poller by this name
        // because it cannot know the id a seeder will hand out.
        $connection = MachineConnection::updateOrCreate(
            ['name' => "SIM-{$code}"],
            [
                'description' => "Simulated Modbus machine for {$code} (modbus:simulate)",
                'protocol' => 'modbus',
                'is_active' => true,
            ],
        );

        ModbusConnection::updateOrCreate(
            ['machine_connection_id' => $connection->id],
            [
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'unit_id' => 1,
                // Once a second: fast enough that a stop appears on the timeline
                // promptly, slow enough not to hammer the ingest path.
                'poll_interval_ms' => 1000,
                'timeout_seconds' => 3,
                'byte_order' => 'big',
            ],
        );

        return $connection;
    }

    private function tagsFor(MachineConnection $connection, Workstation $workstation): void
    {
        foreach (self::TAGS as $tag) {
            MachineTag::updateOrCreate(
                ['machine_connection_id' => $connection->id, 'address' => $tag['address']],
                [
                    'workstation_id' => $workstation->id,
                    'name' => $tag['name'],
                    'signal_type' => $tag['signal_type'],
                    'data_type' => 'uint16',
                    'register_type' => 'holding',
                    // The state register carries a number; this is what turns it
                    // into the state names WorkstationStateMachine understands.
                    'transform' => $tag['signal_type'] === MachineTag::SIGNAL_STATE
                        ? ['value_map' => [
                            '1' => WorkstationState::RUNNING,
                            '2' => WorkstationState::IDLE,
                            '3' => WorkstationState::FAULT,
                        ]]
                        : ($tag['signal_type'] === MachineTag::SIGNAL_TELEMETRY
                            ? ['scale' => 0.1]   // °C ×10 in the register
                            : null),
                    'unit' => $tag['signal_type'] === MachineTag::SIGNAL_TELEMETRY ? '°C' : null,
                    'is_active' => $tag['active'],
                ],
            );
        }
    }

    /**
     * Close any slice left open by an earlier run.
     *
     * A workstation is in one state at a time. An open slice from a previous
     * session would otherwise overlap everything the simulator is about to
     * report, and the monitor would count those minutes twice. The downtime the
     * state machine opened alongside it has to be closed in the same breath —
     * see below.
     */
    private function clearStaleOpenState(Workstation $workstation): void
    {
        WorkstationState::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'duration_seconds' => 0]);

        // The state machine opens a downtime alongside a down-state slice and
        // closes the two together, and it refuses to open a second while one is
        // still open. Closing only the slice breaks that pairing: the next
        // transition finds no current slice, so it never closes the downtime,
        // and the row stays open for good. A stop with no end overlaps every
        // later shift, which is enough for the monitor to attach it to stops it
        // has nothing to do with.
        ProductionDowntime::where('workstation_id', $workstation->id)
            ->whereNull('ended_at')
            ->get()
            ->each(function (ProductionDowntime $downtime) {
                $downtime->update([
                    'ended_at' => now(),
                    'duration_minutes' => max(0, (int) ceil($downtime->started_at->diffInSeconds(now()) / 60)),
                ]);
            });
    }

    /**
     * Hand the station over: close whatever batch was left running on it.
     *
     * `demo:simulate-workflow` refuses to touch a batch it did not open, so a
     * leftover from a demo seeder would block the station indefinitely. Closing
     * it belongs here — this seeder is the deliberate "point a simulator at
     * this station" step, run by hand, and it says what it closed — rather than
     * in the daemon, where it would be a silent write to somebody's record.
     */
    private function clearStaleOpenBatches(Workstation $workstation): void
    {
        Batch::where('workstation_id', $workstation->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->get()
            ->each(function (Batch $batch) {
                $batch->update(['status' => Batch::STATUS_DONE, 'completed_at' => now()]);

                $this->command?->warn(sprintf(
                    '  closed batch %s — the station is now driven by the simulator',
                    $batch->lot_number ?? '#'.$batch->batch_number,
                ));
            });
    }
}
