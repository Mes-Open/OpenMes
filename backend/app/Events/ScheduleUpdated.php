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

    public function broadcastOn(): array
    {
        return [new Channel('schedule')];
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
