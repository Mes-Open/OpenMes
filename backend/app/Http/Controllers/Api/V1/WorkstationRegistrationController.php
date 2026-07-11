<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterWorkstationRequest;
use App\Models\WorkstationDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service registration + heartbeat for OpenMES Workstation clients.
 *
 * Unauthenticated on purpose: a freshly installed station only has the MAIN IP.
 * Both routes are rate limited and only exposed on the LAN. The MAIN app shows
 * the resulting roster live (Admin -> Workstation devices) and derives
 * online/offline from last_seen_at.
 */
class WorkstationRegistrationController extends Controller
{
    /**
     * Register (or re-register) a device by its stable client-generated uuid.
     * Upsert keyed on device_uuid so a reinstall/restart updates in place.
     */
    public function register(RegisterWorkstationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $device = WorkstationDevice::updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'name' => $data['name'],
                'hostname' => $data['hostname'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'line_id' => $data['line_id'] ?? null,
                'ip_address' => $data['ip_address'] ?? $request->ip(),
                'last_seen_at' => now(),
                'registered_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'device_id' => $device->id,
            'heartbeat_interval' => 10,
        ]);
    }

    /**
     * Refresh the heartbeat clock for an already-registered device. A device
     * unknown to the server (e.g. forgotten in the panel) is told to re-register.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:64'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
        ]);

        $device = WorkstationDevice::where('device_uuid', $validated['device_uuid'])->first();

        if ($device === null) {
            return response()->json(['ok' => false, 'reregister' => true], 404);
        }

        $device->fill([
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
        ]);
        if (array_key_exists('line_id', $validated)) {
            $device->line_id = $validated['line_id'];
        }
        $device->save();

        return response()->json(['ok' => true]);
    }
}
