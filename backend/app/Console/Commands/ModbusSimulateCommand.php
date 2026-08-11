<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Minimal Modbus TCP *server* that simulates a machine, so the real
 * modbus:poll daemon has something to read end-to-end without hardware.
 *
 * Register map (holding & input registers share the same backing store):
 *   0 → state        (1=RUNNING, 2=IDLE, 3=FAULT)
 *   1 → good counter (cumulative, wraps at 16 bits like a real PLC)
 *   2 → reject counter
 *   3 → temperature  (telemetry, °C ×10)
 *
 * The machine walks a scripted timeline: running at rate, running *below* rate,
 * idle, and faulted. The reduced-rate phase matters — the shift monitor derives
 * "speed loss" by comparing the counter feed against the station's nameplate
 * rate, so a simulator that only ever runs flat-out or stops can never exercise
 * that half of the screen.
 *
 *   php artisan modbus:simulate --port=5020 --rate=1200 --seed=2
 */
class ModbusSimulateCommand extends Command
{
    protected $signature = 'modbus:simulate
                            {--port=5020}
                            {--host=0.0.0.0}
                            {--rate=1200 : good parts per hour while running at nameplate}
                            {--seed= : decorrelates replicas so stations do not move in lockstep}';

    protected $description = 'Run a simulated Modbus TCP machine (for testing the poller)';

    /** @var array<int,int> holding/input registers */
    private array $reg = [0 => 1, 1 => 0, 2 => 0, 3 => 220];

    /**
     * Parts produced so far, kept as a float because a realistic rate is a
     * fraction of a part per tick — 1200/hour is one part every three seconds,
     * and truncating each tick would silently produce nothing at all.
     */
    private float $produced = 0.0;

    private float $rejected = 0.0;

    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (! $server) {
            $this->error("Cannot bind {$host}:{$port}: {$errstr}");

            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        $this->info("Modbus simulator listening on {$host}:{$port} (Ctrl+C to stop)");

        $ratePerHour = max(1, (int) $this->option('rate'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }

        $clients = [];
        $lastTick = microtime(true);
        // Scripted timeline: [state, seconds, share of nameplate rate].
        // State 1 twice over at different shares is deliberate — a machine that
        // is on but underperforming is the case the monitor calls speed loss.
        $script = [
            [1, 240, 1.0],   // running at rate
            [1, 90, 0.6],    // running slow
            [2, 60, 0.0],    // idle
            [1, 300, 1.0],   // running at rate
            [3, 120, 0.0],   // fault
            [1, 180, 0.95],  // running, near rate
        ];
        $phase = (int) ($this->option('seed') ?? 0) % count($script);
        $phaseElapsed = 0.0;

        while (true) {
            $read = array_merge([$server], $clients);
            $write = $except = null;
            if (@stream_select($read, $write, $except, 1) === false) {
                break;
            }

            foreach ($read as $sock) {
                if ($sock === $server) {
                    $client = @stream_socket_accept($server, 0);
                    if ($client) {
                        stream_set_blocking($client, false);
                        $clients[(int) $client] = $client;
                    }

                    continue;
                }

                $data = @fread($sock, 1024);
                if ($data === '' || $data === false) {
                    fclose($sock);
                    unset($clients[(int) $sock]);

                    continue;
                }
                $response = $this->handleRequest($data);
                if ($response !== null) {
                    @fwrite($sock, $response);
                }
            }

            // Advance simulation on ~1s ticks.
            $now = microtime(true);
            $dt = $now - $lastTick;
            if ($dt >= 1.0) {
                $lastTick = $now;
                $phaseElapsed += $dt;
                [$state, $duration, $share] = $script[$phase];
                $this->reg[0] = $state;

                if ($state === 1) {
                    // Jitter so consecutive minutes are not identical; the
                    // monitor buckets per minute and a flat feed looks synthetic.
                    $effective = $share * (mt_rand(92, 108) / 100);
                    $this->produced += $ratePerHour / 3600 * $dt * $effective;
                    $this->rejected += $ratePerHour / 3600 * $dt * $effective * 0.02;

                    $this->reg[1] = ((int) $this->produced) & 0xFFFF;
                    $this->reg[2] = ((int) $this->rejected) & 0xFFFF;
                    $this->reg[3] = 220 + mt_rand(-5, 15);
                }

                if ($phaseElapsed >= $duration) {
                    $phaseElapsed = 0;
                    $phase = ($phase + 1) % count($script);
                }
                $label = [1 => 'RUNNING', 2 => 'IDLE', 3 => 'FAULT'][$this->reg[0]];
                $this->line(sprintf(
                    '[sim] state=%-7s share=%3d%% good=%d reject=%d temp=%.1f',
                    $label, (int) ($share * 100), $this->reg[1], $this->reg[2], $this->reg[3] / 10,
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Parse a Modbus TCP frame and build a response. Supports FC 0x03/0x04
     * (read holding/input registers) and 0x01/0x02 (read coils/discretes).
     */
    private function handleRequest(string $data): ?string
    {
        if (strlen($data) < 12) {
            return null;
        }
        $mbap = unpack('ntid/nprot/nlen/Cunit', substr($data, 0, 7));
        $fc = ord($data[7]);
        $body = substr($data, 8);
        $req = unpack('naddr/nqty', $body);
        $addr = $req['addr'];
        $qty = max(1, $req['qty']);

        if (in_array($fc, [0x03, 0x04], true)) {
            $payload = '';
            for ($i = 0; $i < $qty; $i++) {
                $val = $this->reg[$addr + $i] ?? 0;
                $payload .= pack('n', $val & 0xFFFF);
            }
            $pdu = chr($fc).chr(strlen($payload)).$payload;
        } elseif (in_array($fc, [0x01, 0x02], true)) {
            $byteCount = intdiv($qty + 7, 8);
            $bits = 0;
            for ($i = 0; $i < $qty; $i++) {
                if (($this->reg[$addr + $i] ?? 0) > 0) {
                    $bits |= (1 << $i);
                }
            }
            $pdu = chr($fc).chr($byteCount).pack('C', $bits & 0xFF);
        } else {
            // Illegal function exception
            $pdu = chr($fc | 0x80).chr(0x01);
        }

        $len = strlen($pdu) + 1; // + unit id

        return pack('nnn', $mbap['tid'], 0, $len).chr($mbap['unit']).$pdu;
    }
}
