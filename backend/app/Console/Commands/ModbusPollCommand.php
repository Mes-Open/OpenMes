<?php

namespace App\Console\Commands;

use App\Models\MachineConnection;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\Modbus\ModbusReader;
use Illuminate\Console\Command;

/**
 * Long-running Modbus TCP poller. One process per machine_connection: connects,
 * then every poll_interval_ms reads all active tags and feeds each into the
 * MachineSignalIngestor. Reconnects with backoff on transport errors.
 *
 *   php artisan modbus:poll --connection=3
 */
class ModbusPollCommand extends Command
{
    protected $signature = 'modbus:poll {--connection= : machine_connection id or name} {--once : single poll cycle then exit (for testing)}';

    protected $description = 'Poll a Modbus TCP device and ingest machine signals';

    /** How often the running poller re-reads its connection and tag list. */
    private const REFRESH_SECONDS = 60;

    /**
     * By id, or by name when the argument isn't numeric.
     *
     * A container can't know the auto-increment id a seeder will hand out, so
     * anything declared in docker-compose addresses its connection by the name
     * it was seeded with.
     */
    private function resolveConnection(string $handle): ?MachineConnection
    {
        $query = MachineConnection::with(['modbusConnection', 'activeTags.workstation']);

        return ctype_digit($handle)
            ? $query->find((int) $handle)
            : $query->where('name', $handle)->first();
    }

    public function handle(MachineSignalIngestor $ingestor, \App\Services\Machine\RuntimeMonitor $runtime): int
    {
        $connection = $this->resolveConnection((string) $this->option('connection'));

        if (! $connection || ! $connection->modbusConnection) {
            $this->error('Modbus connection not found.');

            return self::FAILURE;
        }

        // Refusing at startup as well as mid-run: a container restarted while
        // its machine is switched off would otherwise come straight back up and
        // resume ingesting, which is the same surprise the refresh below exists
        // to prevent.
        if (! $connection->is_active) {
            $this->error("{$connection->name} is not active — nothing to poll.");

            return self::FAILURE;
        }

        $modbus = $connection->modbusConnection;
        $tags = $connection->activeTags;
        $tagsLoadedAt = microtime(true);
        $intervalUs = max(100, $modbus->poll_interval_ms) * 1000;
        $once = (bool) $this->option('once');

        $this->info("Polling {$connection->name} ({$modbus->host}:{$modbus->port}), {$tags->count()} tags");

        do {
            $reader = new ModbusReader($modbus);
            try {
                $reader->connect();
                $connection->markConnected();

                do {
                    $cycleStart = microtime(true);

                    // Which tags are active, and whether the connection is
                    // enabled at all, are configuration — and this process
                    // outlives any change to them. Without a re-read, switching
                    // either off in Admin → Connectivity does nothing until
                    // somebody restarts the container, and the operator has no
                    // way to know that: the UI reports the machine as disabled
                    // while the poller keeps ingesting states and counters, so
                    // a machine taken down for maintenance goes on drawing
                    // RUNNING on the shift monitor. Cheap at this cadence — two
                    // indexed queries a minute against a loop running every
                    // second.
                    if ($cycleStart - $tagsLoadedAt >= self::REFRESH_SECONDS) {
                        $tagsLoadedAt = $cycleStart;
                        $connection->refresh();

                        if (! $connection->is_active) {
                            $this->info("{$connection->name} was deactivated — stopping.");

                            return self::SUCCESS;
                        }

                        $fresh = $connection->activeTags()->with('workstation')->get();

                        if ($fresh->count() !== $tags->count()) {
                            $this->info("tags changed: {$tags->count()} → {$fresh->count()}");
                        }

                        $tags = $fresh;
                    }

                    $runtime->heartbeat($connection->protocol, $connection->id);
                    foreach ($tags as $tag) {
                        try {
                            $value = $reader->readTag($tag);
                            $ingestor->ingest($tag, $value);
                            $connection->increment('messages_received');
                        } catch (\Throwable $e) {
                            $this->warn("tag {$tag->name}: {$e->getMessage()}");
                        }
                    }

                    if ($once) {
                        break 2;
                    }

                    $elapsed = (int) ((microtime(true) - $cycleStart) * 1_000_000);
                    usleep(max(0, $intervalUs - $elapsed));
                } while (true);
            } catch (\Throwable $e) {
                $connection->markError($e->getMessage());
                $this->error("connection error: {$e->getMessage()}");
                if ($once) {
                    return self::FAILURE;
                }
                sleep(5); // backoff before reconnect
            } finally {
                $reader->close();
            }
        } while (! $once);

        return self::SUCCESS;
    }
}
