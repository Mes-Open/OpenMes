<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMqttMessageJob;
use App\Models\MachineConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

/**
 * Supervisor for MQTT machine connections. A single process services **all**
 * active MQTT connections at once (non-blocking `loopOnce()` per client) and
 * periodically reconciles against the database:
 *
 *   - a newly-created / newly-activated connection is connected + subscribed
 *     automatically (no restart needed — #174),
 *   - a deactivated / deleted connection is disconnected,
 *   - a connection whose broker or topics changed is reconnected with the new
 *     config on the next reconcile tick.
 *
 * Pass --connection=<id> to supervise a single connection (used by the
 * per-connection `mqtt-listener` container); omit it to supervise every active
 * MQTT connection in one process.
 */
class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen
        {--connection= : Supervise only this machine_connection id (omit to supervise all active)}
        {--dry-run     : Connect and log received messages without dispatching jobs}';

    protected $description = 'Start the MQTT listener supervisor — subscribes to all active MQTT connections and picks up new ones live';

    /** How often (seconds) to reconcile managed clients against the database. */
    private const RECONCILE_SECONDS = 5;

    private bool $shouldStop = false;

    /** @var array<int, array{client: MqttClient, sig: string, conn: MachineConnection}> */
    private array $managed = [];

    public function handle(): int
    {
        if (! class_exists(MqttClient::class)) {
            $this->error('Package php-mqtt/client is not installed.');
            $this->line('Run: composer require php-mqtt/client');

            return self::FAILURE;
        }

        $only = $this->option('connection');
        $dryRun = (bool) $this->option('dry-run');

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $this->info($only
            ? "Starting MQTT supervisor for connection [{$only}]"
            : 'Starting MQTT supervisor for all active connections');
        $this->line('Reconciling every '.self::RECONCILE_SECONDS.'s — new connections are picked up automatically.');

        $lastReconcile = 0.0;

        while (! $this->shouldStop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $now = microtime(true);
            if ($now - $lastReconcile >= self::RECONCILE_SECONDS) {
                $this->reconcile($only, $dryRun);
                $lastReconcile = $now;
            }

            if (empty($this->managed)) {
                // Nothing to service yet — wait, then reconcile again.
                usleep(200_000);

                continue;
            }

            foreach ($this->managed as $id => $entry) {
                try {
                    $entry['client']->loopOnce(microtime(true), false);
                } catch (\Throwable $e) {
                    $this->error("[{$id}] loop error: {$e->getMessage()}");
                    Log::warning('MQTT client loop error', ['connection_id' => $id, 'error' => $e->getMessage()]);
                    $entry['conn']->markError($e->getMessage());
                    $this->teardown($id); // dropped → retried on the next reconcile
                }
            }

            usleep(50_000); // 50ms breather so the round-robin doesn't busy-spin
        }

        $this->shutdown();

        return self::SUCCESS;
    }

    /**
     * Bring the set of live clients in line with the active MQTT connections in
     * the database: connect new ones, drop removed/deactivated ones, reconnect
     * ones whose config changed.
     */
    private function reconcile(?string $only, bool $dryRun): void
    {
        $query = MachineConnection::with(['mqttConnection', 'activeTopics'])
            ->where('protocol', 'mqtt')
            ->where('is_active', true);

        if ($only) {
            $query->where('id', $only);
        }

        $active = $query->get()->keyBy('id');

        // Drop clients whose connection is no longer active/present.
        foreach (array_keys($this->managed) as $id) {
            if (! $active->has($id)) {
                $this->line("[{$id}] no longer active — disconnecting.");
                $conn = $this->managed[$id]['conn'];
                $this->teardown($id);
                $conn->markDisconnected('Connection deactivated or removed');
            }
        }

        // Connect new connections and reconnect changed ones.
        foreach ($active as $id => $conn) {
            $cfg = $conn->mqttConnection;
            if (! $cfg) {
                $conn->markError('No MQTT configuration defined');

                continue;
            }

            $sig = $this->signature($conn);

            if (isset($this->managed[$id])) {
                if ($this->managed[$id]['sig'] === $sig) {
                    continue; // unchanged — leave the live client alone
                }
                $this->line("[{$id}] config changed — reconnecting.");
                $this->teardown($id);
            }

            try {
                $conn->update(['status' => 'connecting', 'status_message' => null]);
                $client = $this->connectAndSubscribe($conn, $cfg, $dryRun);
                $this->managed[$id] = ['client' => $client, 'sig' => $sig, 'conn' => $conn];
                $conn->markConnected();
                $this->info("[{$id}] {$conn->name} connected → {$cfg->broker_host}:{$cfg->broker_port}");
            } catch (MqttClientException $e) {
                $this->error("[{$id}] connect failed: {$e->getMessage()}");
                Log::error('MQTT connect failed', ['connection_id' => $id, 'error' => $e->getMessage()]);
                $conn->markError($e->getMessage());
            } catch (\Throwable $e) {
                $this->error("[{$id}] unexpected error: {$e->getMessage()}");
                Log::error('MQTT listener error', ['connection_id' => $id, 'error' => $e->getMessage()]);
                $conn->markError($e->getMessage());
            }
        }
    }

    private function connectAndSubscribe(MachineConnection $conn, mixed $cfg, bool $dryRun): MqttClient
    {
        $client = $this->buildClient($cfg);
        $client->connect($this->buildSettings($cfg), $cfg->clean_session);

        foreach ($conn->activeTopics as $topic) {
            $client->subscribe(
                $topic->topic_pattern,
                function (string $incomingTopic, string $message) use ($conn, $dryRun) {
                    $receivedAt = now()->toIso8601String();
                    $this->line("[{$conn->id}] {$incomingTopic}: ".substr($message, 0, 120));

                    if (! $dryRun) {
                        ProcessMqttMessageJob::dispatch($conn->id, $incomingTopic, $message, $receivedAt);
                    }
                },
                $cfg->qos_default,
            );
            $this->line("[{$conn->id}]   subscribed: {$topic->topic_pattern}");
        }

        return $client;
    }

    /**
     * A fingerprint of everything that requires a reconnect when it changes:
     * broker connection settings + the active topic subscriptions.
     */
    private function signature(MachineConnection $conn): string
    {
        $cfg = $conn->mqttConnection;
        $topics = $conn->activeTopics->pluck('topic_pattern')->sort()->values()->implode('|');

        return md5(implode('~', [
            $cfg->broker_host, $cfg->broker_port, $cfg->username, $cfg->getPassword(),
            $cfg->use_tls ? '1' : '0', $cfg->qos_default, $cfg->keep_alive_seconds,
            $cfg->clean_session ? '1' : '0', $topics,
        ]));
    }

    private function teardown(int $id): void
    {
        $entry = $this->managed[$id] ?? null;
        if ($entry) {
            try {
                $entry['client']->disconnect();
            } catch (\Throwable) {
            }
        }
        unset($this->managed[$id]);
    }

    private function shutdown(): void
    {
        foreach ($this->managed as $id => $entry) {
            $entry['conn']->markDisconnected('Listener stopped');
            $this->teardown($id);
        }
        $this->info('MQTT supervisor stopped.');
    }

    private function buildClient(mixed $cfg): MqttClient
    {
        return new MqttClient(
            $cfg->broker_host,
            $cfg->broker_port,
            $cfg->getEffectiveClientId(),
            MqttClient::MQTT_3_1_1,
        );
    }

    private function buildSettings(mixed $cfg): ConnectionSettings
    {
        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval($cfg->keep_alive_seconds)
            ->setConnectTimeout($cfg->connect_timeout)
            ->setReconnectAutomatically(false);

        if ($cfg->username) {
            $settings->setUsername($cfg->username);
        }

        $password = $cfg->getPassword();
        if ($password) {
            $settings->setPassword($password);
        }

        if ($cfg->use_tls) {
            $settings->setUseTls(true);
            if ($cfg->ca_cert) {
                $caFile = tempnam(sys_get_temp_dir(), 'mqtt_ca_');
                file_put_contents($caFile, $cfg->ca_cert);
                $settings->setTlsCertificateAuthorityFile($caFile);
            }
        }

        return $settings;
    }
}
