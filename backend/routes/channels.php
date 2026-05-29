<?php

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Broadcast;

// Public — anyone authenticated can subscribe via Echo.channel('schedule').
Broadcast::channel('schedule', fn () => true);

// Private — only users assigned to the line. Operators subscribe to
// `private-line.{theirLineId}`; Admin/Supervisor can subscribe to any line.
Broadcast::channel('line.{lineId}', function (User $user, int $lineId) {
    if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
        return true;
    }
    return $user->lines()->where('lines.id', $lineId)->exists();
});

// Private — focused channel for a single work order. The run/[batchId]
// screen subscribes so the operator sees status changes the instant they
// land. Admin/Supervisor always allowed; Operators must be assigned to the
// WO's line.
Broadcast::channel('workorder.{workOrderId}', function (User $user, int $workOrderId) {
    if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
        return true;
    }
    $wo = WorkOrder::find($workOrderId);
    if (! $wo || $wo->line_id === null) {
        return false;
    }
    return $user->lines()->where('lines.id', $wo->line_id)->exists();
});
