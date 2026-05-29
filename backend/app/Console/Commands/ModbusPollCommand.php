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
    protected $signature = 'modbus:poll {--connection= : machine_connection id} {--once : single poll cycle then exit (for testing)}';

    protected $description = 'Poll a Modbus TCP device and ingest machine signals';

    public function handle(MachineSignalIngestor $ingestor): int
    {
        $connection = MachineConnection::with(['modbusConnection', 'activeTags.workstation'])
            ->find($this->option('connection'));

        if (! $connection || ! $connection->modbusConnection) {
            $this->error('Modbus connection not found.');

            return self::FAILURE;
        }

        $modbus = $connection->modbusConnection;
        $tags = $connection->activeTags;
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
