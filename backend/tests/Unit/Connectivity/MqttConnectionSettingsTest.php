<?php

namespace Tests\Unit\Connectivity;

use App\Console\Commands\MqttListenCommand;
use App\Models\MqttConnection;
use PhpMqtt\Client\ConnectionSettings;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The credentials and TLS options saved on a device must reach the broker.
 *
 * php-mqtt's ConnectionSettings is immutable — every setter clones and returns
 * the copy — so calling one without keeping the result is a no-op that nothing
 * reports. A broker with anonymous access disabled then refuses the listener as
 * "not authorised" while the panel shows a username that was never sent.
 *
 * buildSettings() is private, so the test reaches it by reflection rather than
 * widening the command's API for a test's sake.
 */
class MqttConnectionSettingsTest extends TestCase
{
    private function buildSettings(MqttConnection $cfg): ConnectionSettings
    {
        $method = new ReflectionMethod(MqttListenCommand::class, 'buildSettings');
        $method->setAccessible(true);

        return $method->invoke(app(MqttListenCommand::class), $cfg);
    }

    private function connection(array $attributes = []): MqttConnection
    {
        $cfg = new MqttConnection(array_merge([
            'broker_host' => 'broker.local',
            'broker_port' => 1883,
            'keep_alive_seconds' => 60,
            'connect_timeout' => 5,
        ], $attributes));

        $cfg->machine_connection_id = 1;

        return $cfg;
    }

    public function test_a_username_and_password_reach_the_connection(): void
    {
        $cfg = $this->connection(['username' => 'openmes']);
        $cfg->password = 'secret';

        $settings = $this->buildSettings($cfg);

        $this->assertSame('openmes', $settings->getUsername());
        $this->assertSame('secret', $settings->getPassword());
    }

    public function test_an_anonymous_broker_is_left_without_credentials(): void
    {
        $settings = $this->buildSettings($this->connection());

        $this->assertNull($settings->getUsername());
        $this->assertNull($settings->getPassword());
    }

    public function test_tls_and_its_certificate_authority_reach_the_connection(): void
    {
        $cfg = $this->connection([
            'use_tls' => true,
            'ca_cert' => "-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----",
        ]);

        $settings = $this->buildSettings($cfg);

        $this->assertTrue($settings->shouldUseTls());

        $caFile = $settings->getTlsCertificateAuthorityFile();
        $this->assertNotNull($caFile);
        $this->assertStringContainsString('BEGIN CERTIFICATE', file_get_contents($caFile));

        @unlink($caFile);
    }

    public function test_the_timings_saved_on_the_device_are_kept(): void
    {
        $settings = $this->buildSettings($this->connection([
            'keep_alive_seconds' => 15,
            'connect_timeout' => 9,
        ]));

        $this->assertSame(15, $settings->getKeepAliveInterval());
        $this->assertSame(9, $settings->getConnectTimeout());
    }
}
