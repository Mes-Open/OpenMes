<?php

namespace App\Http\Controllers\Web\Operator;

use App\Enums\WorkstationDeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\OperatorPanelHeartbeatRequest;
use App\Models\WorkstationDevice;
use Illuminate\Http\JsonResponse;

/**
 * Presence of the browser-based operator panel on the Workstation devices
 * roster (Admin -> Workstation devices).
 *
 * While an operator is logged in, the panel posts a heartbeat every few
 * seconds with its status (active while the page is visible, inactive when it
 * is hidden or closed). The server records who is logged in and the request
 * IP. A panel that sends nothing for longer than the online window is shown
 * as inactive (WorkstationDevice::ONLINE_WINDOW_SECONDS).
 */
class OperatorPanelHeartbeatController extends Controller
{
    /** Session key holding the device row this browser heartbeats, read on logout. */
    public const SESSION_KEY = 'operator_panel_device_id';

    public function __invoke(OperatorPanelHeartbeatRequest $request): JsonResponse
    {
        $data = $request->validated();
        $status = WorkstationDeviceStatus::from($data['status']);

        // firstOrCreate falls back to createOrFirst: when two first heartbeats
        // from one browser race, the loser's insert hits the unique index and
        // it picks up the winner's row instead of failing. The name and
        // registration time are set only on creation, so an admin's rename
        // survives every heartbeat.
        $device = WorkstationDevice::firstOrCreate(
            ['device_uuid' => WorkstationDevice::PANEL_UUID_PREFIX.$data['device_uuid']],
            ['name' => WorkstationDevice::DEFAULT_PANEL_NAME, 'registered_at' => now()],
        );

        $device->fill([
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'status' => $status,
            'last_seen_at' => now(),
        ]);
        if (($data['line_id'] ?? null) !== null) {
            $device->line_id = $data['line_id'];
        }
        // The bench is part of the panel's current context: "all workstations"
        // clears it, unlike the line, which the panel always has once chosen.
        if (array_key_exists('workstation_id', $data)) {
            $device->workstation_id = $data['workstation_id'];
        }
        $device->save();

        $request->session()->put(self::SESSION_KEY, $device->id);

        return response()->json([
            'ok' => true,
            'status' => $device->status->value,
        ]);
    }
}
