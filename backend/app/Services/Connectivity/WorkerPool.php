<?php

namespace App\Services\Connectivity;

use Symfony\Component\Process\Process;

/**
 * One child process per key, kept in line with a wanted set.
 *
 * Reading a machine is blocking: a PLC that is switched off holds its reader
 * for the whole connect or read timeout. Run in one loop, that pause would
 * stop every other machine from being read. Each connection therefore gets a
 * worker of its own, while the service is still started as one command in
 * one container.
 *
 * A worker that exits while still wanted is started again, after a growing
 * pause when it keeps dying young, so a crash loop does not spin.
 */
final class WorkerPool
{
    /** A worker that ran at least this long is healthy: its next restart is immediate. */
    private const HEALTHY_AFTER_SECONDS = 30.0;

    /** @var array<int|string, array{process: Process, started_at: float}> */
    private array $workers = [];

    /** @var array<int|string, string> output after a worker's last complete line */
    private array $partial = [];

    private ReconnectBackoff $restarts;

    /**
     * @param  \Closure(int|string): list<string>  $command  the worker's command line for a key
     * @param  \Closure(int|string, string): void  $output  receives each line a worker prints
     */
    public function __construct(
        private readonly \Closure $command,
        private readonly \Closure $output,
    ) {
        $this->restarts = new ReconnectBackoff;
    }

    /**
     * Start a worker for each wanted key that has none, stop those no longer
     * wanted, and restart any that exited.
     *
     * @param  list<int|string>  $wanted
     */
    public function sync(array $wanted, float $now): void
    {
        $this->reap($now);

        foreach (array_keys($this->workers) as $key) {
            if (! in_array($key, $wanted, true)) {
                $this->stop($key);
            }
        }

        foreach ($wanted as $key) {
            if (! isset($this->workers[$key]) && $this->restarts->ready($key, $now)) {
                $process = new Process(($this->command)($key));
                $process->setTimeout(null);
                $process->start();
                $this->workers[$key] = ['process' => $process, 'started_at' => $now];
            }
        }
    }

    /** Pass on what the workers printed and note any that exited. */
    public function pump(float $now): void
    {
        foreach ($this->workers as $key => $worker) {
            $this->forward($key, $worker['process']);
        }
        $this->reap($now);
    }

    /** @return list<int|string> keys with a running worker */
    public function keys(): array
    {
        return array_keys($this->workers);
    }

    /**
     * Ask every worker to stop (SIGTERM, so it can mark its connection
     * disconnected), wait for them together, then kill what is left.
     */
    public function stopAll(float $graceSeconds = 10.0): void
    {
        foreach ($this->workers as $worker) {
            if ($worker['process']->isRunning()) {
                $worker['process']->signal(SIGTERM);
            }
        }

        $deadline = microtime(true) + $graceSeconds;
        while (microtime(true) < $deadline && array_filter($this->workers, fn ($w) => $w['process']->isRunning()) !== []) {
            usleep(100_000);
        }

        foreach (array_keys($this->workers) as $key) {
            $this->stop($key);
        }
    }

    private function stop(int|string $key): void
    {
        $process = $this->workers[$key]['process'];
        $process->stop(5);
        $this->forward($key, $process, true);
        unset($this->workers[$key]);
        $this->restarts->succeeded($key);
    }

    private function reap(float $now): void
    {
        foreach ($this->workers as $key => $worker) {
            if ($worker['process']->isRunning()) {
                continue;
            }
            $this->forward($key, $worker['process'], true);
            unset($this->workers[$key]);

            if ($now - $worker['started_at'] >= self::HEALTHY_AFTER_SECONDS) {
                $this->restarts->succeeded($key);
            } else {
                $this->restarts->failed($key, $now);
            }
        }
    }

    /** Hand on complete lines only; at the end ($final) whatever is left too. */
    private function forward(int|string $key, Process $process, bool $final = false): void
    {
        $text = ($this->partial[$key] ?? '').$process->getIncrementalOutput().$process->getIncrementalErrorOutput();
        $lines = preg_split('/\R/', $text);
        if ($final) {
            unset($this->partial[$key]);
        } else {
            $this->partial[$key] = array_pop($lines);
        }

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                ($this->output)($key, $line);
            }
        }
    }
}
