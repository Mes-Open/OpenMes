<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ScheduleUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $kind = 'change',
        public ?int $lineId = null,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'line_id' => $this->lineId,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('schedule')];

        // When the event has a specific line, also push it on the private
        // line channel so operators assigned to that line get a targeted
        // wake-up (and operators on other lines don't).
        if ($this->lineId !== null) {
            $channels[] = new \Illuminate\Broadcasting\PrivateChannel("line.{$this->lineId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'schedule.updated';
    }

    public function broadcastWhen(): bool
    {
        $mode = DB::table('system_settings')->where('key', 'realtime_mode')->value('value');

        return $mode === 'websocket';
    }
}
