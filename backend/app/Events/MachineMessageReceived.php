<?php

namespace App\Events;

use App\Models\MachineMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after each MQTT message is processed.
 * Used internally for module hooks (e.g. ModuleManager event listeners).
 * Live log uses HTTP polling — no WebSocket required.
 */
class MachineMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MachineMessage $message
    ) {}
}
