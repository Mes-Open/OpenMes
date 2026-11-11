<?php

namespace Tests\Feature\Machine;

use App\Console\Commands\ModbusPollCommand;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ModbusConnection;
use App\Services\Connectivity\ReconnectBackoff;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\Modbus\ModbusReader;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ModbusTcpClient\Exception\ModbusException;
use ModbusTcpClient\Network\IOException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * A Modbus poller that loses its machine (PLC restarted, cable pulled) must
 * say so and reconnect, not keep reporting "connected" while every read fails.
 * A single misconfigured tag, on the other hand, must not stop the rest.
 */
class ModbusPollLinkLossTest extends TestCase
{
    use RefreshDatabase;

    private MachineConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = MachineConnection::create(['name' => 'PLC-1', 'protocol' => 'modbus', 'is_active' => true]);
        ModbusConnection::create([
            'machine_connection_id' => $this->connection->id,
            'host' => '127.0.0.1', 'port' => 502, 'poll_interval_ms' => 1000, 'timeout_seconds' => 1,
        ]);
        foreach (['Bad tag' => '1', 'Temperature' => '2'] as $name => $address) {
            MachineTag::create([
                'machine_connection_id' => $this->connection->id,
                'name' => $name, 'address' => $address,
                'signal_type' => MachineTag::SIGNAL_TELEMETRY,
                'data_type' => 'uint16', 'register_type' => 'holding', 'is_active' => true,
            ]);
        }
    }

    /** @param  array<string, \Throwable|int>  $answers  tag name → value or failure */
    private function fakeReader(array $answers): void
    {
        $this->app->bind(ModbusReader::class, fn ($app, array $args) => new class($args['modbus'], $answers) extends ModbusReader
        {
            public function __construct(ModbusConnection $modbus, private readonly array $answers)
            {
                parent::__construct($modbus);
            }

            public function connect(): void {}

            public function close(): void {}

            public function readTag(MachineTag $tag): mixed
            {
                $answer = $this->answers[$tag->name];
                if ($answer instanceof \Throwable) {
                    throw $answer;
                }

                return $answer;
            }
        });
    }

    public function test_a_lost_link_is_reported_instead_of_polling_on_as_connected(): void
    {
        $this->fakeReader(['Bad tag' => new IOException('Read total timeout expired'), 'Temperature' => 21]);

        $this->artisan('modbus:poll', ['--connection' => 'PLC-1', '--once' => true])
            ->expectsOutputToContain('connection error: Read total timeout expired')
            ->assertFailed();

        $this->connection->refresh();
        $this->assertSame(MachineConnection::STATUS_ERROR, $this->connection->status);
        $this->assertSame(0, (int) $this->connection->messages_received);
    }

    public function test_one_bad_tag_does_not_stop_the_others(): void
    {
        $this->fakeReader(['Bad tag' => new ModbusException('Illegal data address'), 'Temperature' => 21]);

        $this->artisan('modbus:poll', ['--connection' => 'PLC-1', '--once' => true])
            ->expectsOutputToContain('tag Bad tag: Illegal data address')
            ->assertSuccessful();

        $this->connection->refresh();
        $this->assertSame(MachineConnection::STATUS_CONNECTED, $this->connection->status);
        $this->assertSame(1, (int) $this->connection->messages_received);
    }

    public function test_one_process_polls_every_machine_and_a_dead_one_does_not_stop_the_rest(): void
    {
        $other = MachineConnection::create(['name' => 'PLC-2', 'protocol' => 'modbus', 'is_active' => true]);
        ModbusConnection::create([
            'machine_connection_id' => $other->id,
            'host' => '10.0.0.99', 'port' => 502, 'poll_interval_ms' => 1000, 'timeout_seconds' => 1,
        ]);
        MachineTag::create([
            'machine_connection_id' => $other->id, 'name' => 'Pressure', 'address' => '1',
            'signal_type' => MachineTag::SIGNAL_TELEMETRY, 'data_type' => 'uint16', 'register_type' => 'holding', 'is_active' => true,
        ]);
        // Switched off: must not be polled at all.
        MachineConnection::create(['name' => 'PLC-off', 'protocol' => 'modbus', 'is_active' => false]);

        // PLC-1 (127.0.0.1) is powered down; PLC-2 answers.
        $this->app->bind(ModbusReader::class, fn ($app, array $args) => new class($args['modbus']) extends ModbusReader
        {
            public function __construct(private readonly ModbusConnection $settings)
            {
                parent::__construct($settings);
            }

            public function connect(): void
            {
                if ($this->settings->host === '127.0.0.1') {
                    throw new IOException('Connection refused');
                }
            }

            public function close(): void {}

            public function readTag(MachineTag $tag): mixed
            {
                return 7;
            }
        });

        $this->artisan('modbus:poll', ['--once' => true])
            ->expectsOutputToContain('Polling PLC-1')
            ->expectsOutputToContain('Polling PLC-2')
            ->expectsOutputToContain('PLC-1: connection error: Connection refused')
            ->doesntExpectOutputToContain('PLC-off')
            ->assertFailed();

        $this->assertSame(MachineConnection::STATUS_ERROR, $this->connection->fresh()->status);
        $this->assertSame(MachineConnection::STATUS_CONNECTED, $other->fresh()->status);
        $this->assertSame(1, (int) $other->fresh()->messages_received);
    }

    public function test_a_plc_that_accepts_the_connection_but_never_answers_keeps_backing_off(): void
    {
        // A TCP bridge in front of a dead PLC: connect() succeeds, every read times out.
        $this->fakeReader(['Bad tag' => new IOException('Read total timeout expired'), 'Temperature' => 21]);

        $command = $this->app->make(ModbusPollCommand::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $backoff = new ReconnectBackoff(0.000001, 60.0);
        $connection = $this->connection->load(['modbusConnection', 'activeTags']);
        $state = fn (string $name) => (new \ReflectionProperty(ModbusPollCommand::class, $name));
        $state('backoff')->setValue($command, $backoff);
        $state('managed')->setValue($command, [$connection->id => [
            'connection' => $connection, 'modbus' => $connection->modbusConnection,
            'tags' => $connection->activeTags, 'reader' => null, 'next_poll_at' => 0.0,
        ]]);
        $poll = new \ReflectionMethod(ModbusPollCommand::class, 'poll');
        $ingestor = $this->app->make(MachineSignalIngestor::class);

        $this->assertFalse($poll->invoke($command, $connection->id, $ingestor));
        usleep(10);
        $this->assertFalse($poll->invoke($command, $connection->id, $ingestor));

        // Two failures in a row, not two "first" failures: the connect that
        // succeeded in between did not reset the count.
        $failures = (new \ReflectionProperty(ReconnectBackoff::class, 'state'))->getValue($backoff)[$connection->id]['failures'];
        $this->assertSame(2, $failures);
    }
}
