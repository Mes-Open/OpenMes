<?php

namespace App\Listeners;

use App\Enums\WorkstationDeviceStatus;
use App\Http\Controllers\Web\Operator\OperatorPanelHeartbeatController;
use App\Models\WorkstationDevice;
use Illuminate\Auth\Events\Logout;

/**
 * Logging out of the operator panel marks that browser's row on the
 * Workstation devices roster inactive straight away, rather than leaving it
 * active until the online window runs out.
 *
 * Logout fires before the controller invalidates the session, so the device
 * id the heartbeat stored there is still readable.
 */
class MarkOperatorPanelInactive
{
    public function handle(Logout $event): void
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return;
        }

        $deviceId = request()->session()->get(OperatorPanelHeartbeatController::SESSION_KEY);
        if ($deviceId === null) {
            return;
        }

        // Saved through the model (not a bulk update) so the change is
        // broadcast to the live roster.
        $device = WorkstationDevice::find($deviceId);
        $device?->update(['status' => WorkstationDeviceStatus::Inactive]);
    }
}
