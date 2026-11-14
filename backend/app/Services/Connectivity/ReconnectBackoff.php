<?php

namespace App\Services\Connectivity;

/**
 * When to try a failed connection again.
 *
 * The MQTT supervisor serves every broker from one process, and connecting is
 * blocking: a broker that is switched off (a machine powered down for the
 * night) holds the whole loop for its full connect timeout. Retrying it on
 * every reconcile tick would stall the healthy connections every few seconds,
 * long enough for their brokers to drop them and for messages to pile up. Each
 * consecutive failure therefore doubles the wait, up to a cap, and a success
 * resets it.
 */
final class ReconnectBackoff
{
    /** @var array<int|string, array{failures: int, retry_at: float}> */
    private array $state = [];

    public function __construct(
        private readonly float $initialSeconds = 5.0,
        private readonly float $maxSeconds = 60.0,
    ) {}

    /** Whether a connection may be (re)tried at this moment. */
    public function ready(int|string $key, float $now): bool
    {
        return ! isset($this->state[$key]) || $now >= $this->state[$key]['retry_at'];
    }

    /** Record a failed attempt; returns the seconds until the next one. */
    public function failed(int|string $key, float $now): float
    {
        $failures = ($this->state[$key]['failures'] ?? 0) + 1;
        $wait = min($this->maxSeconds, $this->initialSeconds * 2 ** ($failures - 1));
        $this->state[$key] = ['failures' => $failures, 'retry_at' => $now + $wait];

        return $wait;
    }

    public function succeeded(int|string $key): void
    {
        unset($this->state[$key]);
    }
}
