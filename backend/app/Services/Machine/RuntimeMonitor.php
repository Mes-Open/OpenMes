<?php

namespace App\Services\Machine;

use App\Enums\ApiTokenScope;
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
     * `error` is the reason a running gateway reported for not reaching its
     * machine; `token_url` points at where a gateway token is created (only for
     * runtimes that call the API); `endpoint_warning` flags an endpoint that
     * cannot work from inside a container.
     *
     * @return array{required: bool, alive: bool, seconds_ago: int|null, label: string, command: string|null, docker: string|null, error: string|null, token_url: string|null, endpoint_warning: string|null}
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
                // The sidecar authenticates against the API, unlike the artisan
                // daemons above — without a token it only ever gets a 401.
                'docker' => "OPCUA_CONNECTION_ID={$id} OPENMES_API_TOKEN=<token> docker compose --profile connectivity up -d opcua-gateway",
            ],
            default => ['label' => __('Runtime'), 'command' => null, 'docker' => null],
        };

        $alive = $this->isAlive($kind, $id);

        return [
            'required' => $connection->is_active,
            'alive' => $alive,
            'seconds_ago' => $this->secondsSince($kind, $id),
            'label' => $meta['label'],
            'command' => $meta['command'],
            'docker' => $meta['docker'],
            'error' => $alive && $connection->status === MachineConnection::STATUS_ERROR
                ? $connection->status_message
                : null,
            'token_url' => $kind === MachineConnection::PROTOCOL_OPCUA
                ? '/settings/api-tokens?scope='.ApiTokenScope::MachineGateway->value
                : null,
            'endpoint_warning' => $kind === MachineConnection::PROTOCOL_OPCUA
                ? $this->loopbackWarning($connection->opcuaConnection?->endpoint_url)
                : null,
        ];
    }

    /**
     * The gateway runs in its own container, where localhost is the container
     * itself — an OPC UA server on the host is not reachable that way.
     */
    private function loopbackWarning(?string $endpoint): ?string
    {
        $host = $endpoint ? parse_url($endpoint, PHP_URL_HOST) : null;
        $host = is_string($host) ? strtolower(trim($host, '[]')) : null;

        if ($host === null || ! ($host === 'localhost' || $host === '::1' || str_starts_with($host, '127.'))) {
            return null;
        }

        return __('The endpoint points to :host. Inside Docker that is the gateway container itself - use host.docker.internal or the machine\'s IP address instead.', ['host' => $host]);
    }

    private function cacheKey(string $kind, int|string $key): string
    {
        return "runtime_hb:{$kind}:{$key}";
    }
}
