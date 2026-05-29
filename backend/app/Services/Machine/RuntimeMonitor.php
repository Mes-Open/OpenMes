<?php

namespace App\Services\Machine;

use App\Models\MachineConnection;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks whether the background runtimes required by each protocol are actually
 * running. Daemons (Modbus poller, MQTT listener) and external gateways
 * (OPC UA) write a heartbeat every cycle; the UI reads it so a user is told,
 * clearly, when a connection is configured but nothing is polling it — whether
 * they run on Docker or bare metal.
 */
class RuntimeMonitor
{
    /** A runtime is considered alive if seen within this many seconds. */
    public const STALE_AFTER = 20;

    private const TTL = 120;

    public function heartbeat(string $kind, int|string $key): void
    {
        Cache::put($this->cacheKey($kind, $key), time(), self::TTL);
    }

    public function lastSeen(string $kind, int|string $key): ?int
    {
        return Cache::get($this->cacheKey($kind, $key));
    }

    public function isAlive(string $kind, int|string $key): bool
    {
        $ts = $this->lastSeen($kind, $key);

        return $ts !== null && (time() - $ts) <= self::STALE_AFTER;
    }

    public function secondsSince(string $kind, int|string $key): ?int
    {
        $ts = $this->lastSeen($kind, $key);

        return $ts === null ? null : max(0, time() - $ts);
    }

    /**
     * Runtime status for a machine connection, including a copy-paste command
     * and the optional Docker service to start it.
     *
     * @return array{required: bool, alive: bool, seconds_ago: int|null, label: string, command: string|null, docker: string|null}
     */
    public function connectionRuntime(MachineConnection $connection): array
    {
        $kind = $connection->protocol;
        $id = $connection->id;

        $meta = match ($kind) {
            MachineConnection::PROTOCOL_MODBUS => [
                'label' => __('Modbus poller'),
                'command' => "php artisan modbus:poll --connection={$id}",
                'docker' => "MODBUS_CONNECTION_ID={$id} docker compose --profile connectivity up -d modbus-poller",
            ],
            MachineConnection::PROTOCOL_MQTT => [
                'label' => __('MQTT listener'),
                'command' => "php artisan mqtt:listen --connection={$id}",
                'docker' => "MQTT_CONNECTION_ID={$id} docker compose --profile connectivity up -d mqtt-listener",
            ],
            MachineConnection::PROTOCOL_OPCUA => [
                'label' => __('OPC UA gateway'),
                'command' => null, // external sidecar, not an artisan command
                'docker' => "OPCUA_CONNECTION_ID={$id} docker compose --profile connectivity up -d opcua-gateway",
            ],
            default => ['label' => __('Runtime'), 'command' => null, 'docker' => null],
        };

        return [
            'required' => $connection->is_active,
            'alive' => $this->isAlive($kind, $id),
            'seconds_ago' => $this->secondsSince($kind, $id),
            'label' => $meta['label'],
            'command' => $meta['command'],
            'docker' => $meta['docker'],
        ];
    }

    private function cacheKey(string $kind, int|string $key): string
    {
        return "runtime_hb:{$kind}:{$key}";
    }
}
