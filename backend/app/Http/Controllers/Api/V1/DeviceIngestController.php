<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DevicePulseRequest;
use App\Jobs\ProcessMqttMessageJob;
use App\Models\MachineConnection;
use Illuminate\Http\JsonResponse;

/**
 * A sensor pulse. The auth.device middleware has already resolved the device's
 * MachineConnection and set the tenant scope. We funnel the pulse through the
 * exact same pipeline as an MQTT message — ProcessMqttMessageJob re-scopes to
 * the connection's tenant, matches the `device/pulse` topic, runs its count_step
 * mapping, and logs a MachineMessage — so there is one counting path, not two.
 */
class DeviceIngestController extends Controller
{
    public function pulse(DevicePulseRequest $request): JsonResponse
    {
        /** @var MachineConnection $connection */
        $connection = $request->attributes->get('device_connection');

        $qty = (float) ($request->validated()['qty'] ?? 1);

        $payload = json_encode([
            'pulse' => $qty,
            'ts' => now()->timestamp,
            'source' => 'device',
        ]);

        // Run synchronously so the device gets an accurate immediate ack and the
        // count is applied without depending on a queue worker being up.
        ProcessMqttMessageJob::dispatchSync(
            $connection->id,
            DeviceEnrollmentController::PULSE_TOPIC,
            $payload,
            now()->toIso8601String(),
        );

        return response()->json(['accepted' => true], 202);
    }
}
