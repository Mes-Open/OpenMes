<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMqttMessageJob;
use App\Models\MachineConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen
        {--connection= : ID of a specific machine_connection to listen on (omit for all active)}
        {--dry-run     : Connect and log received messages without dispatching jobs}';

    protected $description = 'Start MQTT listener daemon — subscribes to all active MQTT connections';

    private bool $shouldStop = false;

    public function handle(): int
    {
        if (!class_exists(MqttClient::class)) {
            $this->error('Package php-mqtt/client is not installed.');
            $this->line('Run: composer require php-mqtt/client');
            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $dryRun       = $this->option('dry-run');

        $query = MachineConnection::with('mqttConnection')
            ->where('protocol', 'mqtt')
            ->where('is_active', true);

        if ($connectionId) {
            $query->where('id', $connectionId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->warn('No active MQTT connections found.');
            return self::SUCCESS;
        }

        if ($connections->count() > 1) {
            $this->error('This command handles one connection at a time.');
            $this->line('Use --connection=<id> or run separate processes per connection.');
            $this->line('Active connections:');
            foreach ($connections as $conn) {
                $this->line("  [{$conn->id}] {$conn->name}");
            }
            return self::FAILURE;
        }

        $machineConn = $connections->first();
        $mqttConfig  = $machineConn->mqttConnection;

        if (!$mqttConfig) {
            $this->error("No MQTT configuration found for connection [{$machineConn->id}] {$machineConn->name}");
            $machineConn->markError('No MQTT configuration defined');
            return self::FAILURE;
        }

        // Register SIGTERM / SIGINT handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        }

        $this->info("Starting MQTT listener for [{$machineConn->id}] {$machineConn->name}");
        $this->line("  Broker: {$mqttConfig->broker_host}:{$mqttConfig->broker_port}");

        while (!$this->shouldStop) {
            $client = null;
            try {
                $machineConn->update(['status' => 'connecting', 'status_message' => null]);

                $client   = $this->buildClient($mqttConfig);
                $settings = $this->buildSettings($mqttConfig);

                $client->connect($settings, $mqttConfig->clean_session);
                $machineConn->markConnected();
                $this->info('[' . now()->toTimeString() . '] Connected.');

                // Subscribe to all active topics for this connection
                $topics = $machineConn->activeTopics()->get();
                foreach ($topics as $topic) {
                    $client->subscribe(
                        $topic->topic_pattern,
                        function (string $incomingTopic, string $message) use ($machineConn, $dryRun) {
                            if (function_exists('pcntl_signal_dispatch')) {
                                pcntl_signal_dispatch();
                            }

                            $receivedAt = now()->toIso8601String();
                            $this->line("[{$receivedAt}] {$incomingTopic}: " . substr($message, 0, 120));

                            if (!$dryRun) {
                                ProcessMqttMessageJob::dispatch(
                                    $machineConn->id,
                                    $incomingTopic,
                                    $message,
                                    $receivedAt,
                                );
                            }
                        },
                        $mqttConfig->qos_default,
                    );
                    $this->line("  Subscribed: {$topic->topic_pattern}");
                }

                // Main loop — exits on exception or stop signal
                $client->loop(true, true);

            } catch (MqttClientException $e) {
                $message = 'MQTT error: ' . $e->getMessage();
                $this->error($message);
                Log::error($message, ['connection_id' => $machineConn->id]);
                $machineConn->markError($e->getMessage());
            } catch (\Throwable $e) {
                $this->error('Unexpected error: ' . $e->getMessage());
                Log::error('MQTT listener crashed', ['error' => $e->getMessage(), 'connection_id' => $machineConn->id]);
                $machineConn->markError($e->getMessage());
            } finally {
                try {
                    $client?->disconnect();
                } catch (\Throwable) {}
            }

            if ($this->shouldStop) {
                break;
            }

            $delay = $mqttConfig->reconnect_delay_seconds;
            $this->line("Reconnecting in {$delay}s...");
            sleep($delay);

            // Reload config in case it was updated
            $machineConn->refresh();
            $mqttConfig = $machineConn->mqttConnection;
        }

        $machineConn->markDisconnected('Listener stopped');
        $this->info('MQTT listener stopped.');
        return self::SUCCESS;
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
        $settings = (new ConnectionSettings())
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
                // Write CA cert to temp file
                $caFile = tempnam(sys_get_temp_dir(), 'mqtt_ca_');
                file_put_contents($caFile, $cfg->ca_cert);
                $settings->setTlsCertificateAuthorityFile($caFile);
            }
        }

        return $settings;
    }
}
