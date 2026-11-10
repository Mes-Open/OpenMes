<?php

namespace App\Console\Commands;

use App\Models\MachineConnection;
use App\Models\ModbusConnection;
use App\Services\Connectivity\ReconnectBackoff;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\Modbus\ModbusReader;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use ModbusTcpClient\Network\IOException;

/**
 * Long-running Modbus TCP poller. One process serves every active Modbus
 * machine_connection: each is connected and, every poll_interval_ms, all of
 * its active tags are read and fed into the MachineSignalIngestor.
 *
 *   php artisan modbus:poll                  every active Modbus connection
 *   php artisan modbus:poll --connection=3   just this one (id or name)
 *
 * The set of connections, their tags and their address settings are re-read
 * every REFRESH_SECONDS, so a machine added, switched off, moved to a new IP or
 * given a new tag in Admin → Connectivity needs no restart. A machine that
 * cannot be reached is retried with a growing pause (ReconnectBackoff), so a
 * PLC powered down for the night costs the others one connect timeout now and
 * then rather than on every cycle.
 */
class ModbusPollCommand extends Command
{
    protected $signature = 'modbus:poll {--connection= : only this machine_connection (id or name); omit to poll every active Modbus connection} {--once : single poll cycle then exit (for testing)}';

    protected $description = 'Poll Modbus TCP devices and ingest machine signals';

    /** How often the running poller re-reads connections, tags and settings. */
    private const REFRESH_SECONDS = 60;

    /** Well inside RuntimeMonitor::STALE_AFTER. */
    private const HEARTBEAT_SECONDS = 5;

    /**
     * @var array<int, array{connection: MachineConnection, modbus: ModbusConnection, tags: Collection, reader: ?ModbusReader, next_poll_at: float}>
     */
    private array $managed = [];

    private bool $shouldStop = false;

    private ReconnectBackoff $backoff;

    public function handle(MachineSignalIngestor $ingestor, RuntimeMonitor $runtime): int
    {
        $this->backoff = new ReconnectBackoff;
        $handle = $this->option('connection');
        $once = (bool) $this->option('once');

        if ($handle !== null && $handle !== '') {
            $connection = $this->resolveConnection((string) $handle);

            if (! $connection || ! $connection->modbusConnection) {
                $this->error('Modbus connection not found.');

                return self::FAILURE;
            }

            // Refusing at startup as well as mid-run: a container restarted
            // while its machine is switched off would otherwise come straight
            // back up and resume ingesting.
            if (! $connection->is_active) {
                $this->error("{$connection->name} is not active — nothing to poll.");

                return self::FAILURE;
            }
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $this->reconcile($handle);
        if ($this->managed === [] && ! $once) {
            $this->line('No active Modbus connections yet — waiting for one to be added.');
        }

        $lastRefresh = microtime(true);
        $lastHeartbeat = 0.0;
        $failed = false;

        while (! $this->shouldStop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $now = microtime(true);

            // On its own clock, not once per poll: poll_interval_ms can be longer
            // than RuntimeMonitor::STALE_AFTER, and a slow-polled machine would
            // then read as "not running" for part of every cycle. Sent for every
            // connection, reachable or not, so an unreachable machine shows its
            // error rather than a dead poller.
            if ($now - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                $lastHeartbeat = $now;
                foreach ($this->managed as $entry) {
                    $runtime->heartbeat($entry['connection']->protocol, $entry['connection']->id);
                }
            }

            if ($now - $lastRefresh >= self::REFRESH_SECONDS) {
                $lastRefresh = $now;
                $this->reconcile($handle);

                // A single pinned connection that was switched off ends the run.
                if ($handle && $this->managed === []) {
                    return self::SUCCESS;
                }
            }

            foreach (array_keys($this->managed) as $id) {
                if ($once || microtime(true) >= $this->managed[$id]['next_poll_at']) {
                    $failed = ! $this->poll($id, $ingestor) || $failed;
                }
            }

            if ($once) {
                break;
            }

            usleep($this->sleepMicroseconds());
        }

        $this->shutdown();

        return $once && $failed ? self::FAILURE : self::SUCCESS;
    }

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

    /**
     * Bring the polled set in line with the database: start new connections,
     * drop switched-off or deleted ones, pick up tag and address changes.
     */
    private function reconcile(?string $handle): void
    {
        $query = MachineConnection::with(['modbusConnection', 'activeTags.workstation'])
            ->where('protocol', MachineConnection::PROTOCOL_MODBUS)
            ->where('is_active', true);

        if ($handle) {
            $query->where(ctype_digit($handle) ? 'id' : 'name', ctype_digit($handle) ? (int) $handle : $handle);
        }

        $active = $query->get()->filter(fn (MachineConnection $c) => $c->modbusConnection !== null)->keyBy('id');

        foreach (array_keys($this->managed) as $id) {
            if (! $active->has($id)) {
                $connection = $this->managed[$id]['connection'];
                $this->info("{$connection->name} was deactivated — stopping.");
                $this->drop($id);
                $connection->markDisconnected('Connection deactivated or removed');
            }
        }

        foreach ($active as $id => $connection) {
            $modbus = $connection->modbusConnection;
            $tags = $connection->activeTags;

            if (! isset($this->managed[$id])) {
                $this->info("Polling {$connection->name} ({$modbus->host}:{$modbus->port}), {$tags->count()} tags");
                $this->managed[$id] = ['connection' => $connection, 'modbus' => $modbus, 'tags' => $tags, 'reader' => null, 'next_poll_at' => 0.0];

                continue;
            }

            $entry = &$this->managed[$id];
            if ($tags->count() !== $entry['tags']->count()) {
                $this->info("{$connection->name}: tags changed: {$entry['tags']->count()} → {$tags->count()}");
            }
            $entry['connection'] = $connection;
            $entry['tags'] = $tags;

            // A PLC moved to a new IP, a corrected unit id or byte order: drop
            // the socket so the next poll connects with the new settings.
            if ($this->transport($modbus) !== $this->transport($entry['modbus'])) {
                $this->info("{$connection->name}: connection settings changed — reconnecting.");
                $entry['reader']?->close();
                $entry['reader'] = null;
                $this->backoff->succeeded($id);
            }
            $entry['modbus'] = $modbus;
            unset($entry);
        }
    }

    /** One poll of one connection. False when the machine could not be read. */
    private function poll(int $id, MachineSignalIngestor $ingestor): bool
    {
        $entry = &$this->managed[$id];
        $connection = $entry['connection'];
        $modbus = $entry['modbus'];
        $cycleStart = microtime(true);
        $entry['next_poll_at'] = $cycleStart + max(100, $modbus->poll_interval_ms) / 1000;

        try {
            if ($entry['reader'] === null) {
                if (! $this->backoff->ready($id, $cycleStart)) {
                    return false;
                }
                $reader = app(ModbusReader::class, ['modbus' => $modbus]);
                $reader->connect();
                $entry['reader'] = $reader;
                $this->backoff->succeeded($id);
                $connection->markConnected();
            }

            foreach ($entry['tags'] as $tag) {
                try {
                    $acquiredAt = now();
                    $value = $entry['reader']->readTag($tag);
                    $ingestor->ingest($tag, $value, $acquiredAt, null, $modbus->only(['host', 'port', 'unit_id', 'byte_order', 'word_order']));
                    $connection->increment('messages_received');
                } catch (IOException $e) {
                    // The link to the machine is gone (PLC restarted, cable
                    // pulled, read timed out). Reading on would fail every tag
                    // while the page still says "connected", and after a
                    // timeout a late reply could be taken as the answer for the
                    // next tag. Drop the socket and reconnect.
                    throw $e;
                } catch (\Throwable $e) {
                    // One bad tag (wrong address, illegal register) must not
                    // stop the others from being read.
                    $this->warn("{$connection->name}: tag {$tag->name}: {$e->getMessage()}");
                }
            }

            return true;
        } catch (\Throwable $e) {
            $entry['reader']?->close();
            $entry['reader'] = null;
            $wait = $this->backoff->failed($id, microtime(true));
            $connection->markError($e->getMessage());
            $this->error("{$connection->name}: connection error: {$e->getMessage()} — next try in {$wait}s");

            return false;
        } finally {
            unset($entry);
        }
    }

    /** Until the next connection is due, kept short so signals stay responsive. */
    private function sleepMicroseconds(): int
    {
        if ($this->managed === []) {
            return 1_000_000;
        }

        $next = min(array_column($this->managed, 'next_poll_at'));

        return (int) min(200_000, max(10_000, ($next - microtime(true)) * 1_000_000));
    }

    private function drop(int $id): void
    {
        $this->managed[$id]['reader']?->close();
        unset($this->managed[$id]);
        $this->backoff->succeeded($id);
    }

    private function shutdown(): void
    {
        foreach (array_keys($this->managed) as $id) {
            $connection = $this->managed[$id]['connection'];
            $this->drop($id);
            if (! $this->option('once')) {
                $connection->markDisconnected('Poller stopped');
            }
        }
    }

    /** Settings that need a new socket when they change. */
    private function transport(ModbusConnection $modbus): array
    {
        return $modbus->only(['host', 'port', 'unit_id', 'byte_order', 'word_order', 'timeout_seconds', 'poll_interval_ms']);
    }
}
