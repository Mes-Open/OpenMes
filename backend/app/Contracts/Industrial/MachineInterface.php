<?php

namespace App\Contracts\Industrial;

use App\Models\Workstation;

interface MachineInterface
{
    /**
     * Connect to the machine/PLC.
     */
    public function connect(): bool;

    /**
     * Disconnect from the machine.
     */
    public function disconnect(): void;

    /**
     * Read a specific tag/register from the machine.
     */
    public function readTag(string $tag): mixed;

    /**
     * Write a value to a specific tag/register.
     */
    public function writeTag(string $tag, mixed $value): bool;

    /**
     * Get the current state of the machine.
     */
    public function getCurrentState(): string;

    /**
     * Get real-time telemetry (all relevant tags).
     */
    public function getTelemetry(): array;
}
