<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DevicePulseRequest;
use App\Jobs\ProcessMqttMessageJob;
use App\Models\DeviceToken;
use App\Models\MachineConnection;
use App\Models\MachineMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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
        /** @var DeviceToken $token */
        $token = $request->attributes->get('device_token');

        $data = $request->validated();

        // Idempotency: a device may retry a pulse it already delivered (network
        // hiccup). If it sends the same idempotency_key twice within the window,
        // apply it once — the retry is acknowledged but not double-counted.
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey !== null) {
            $cacheKey = "device-pulse:{$token->id}:{$idempotencyKey}";
            if (! Cache::add($cacheKey, true, now()->addHour())) {
                return response()->json(['accepted' => true, 'idempotent_replay' => true], 202);
            }
        }

        $qty = (float) ($data['qty'] ?? 1);

        $payload = json_encode([
            'pulse' => $qty,
            'ts' => now()->timestamp,
            'source' => 'device',
        ]);

        // Run synchronously so we can report the actual processing outcome rather
        // than blindly acknowledging (a device must not stop retrying when its
        // count was never applied).
        ProcessMqttMessageJob::dispatchSync(
            $connection->id,
            DeviceEnrollmentController::PULSE_TOPIC,
            $payload,
            now()->toIso8601String(),
        );

        // Inspect the message the job just logged. A processing error means the
        // count-step action failed — surface it so the sensor keeps retrying.
        $message = MachineMessage::where('machine_connection_id', $connection->id)
            ->latest('id')->first();

        if ($message && $message->processing_status === MachineMessage::STATUS_ERROR) {
            // The pulse was not applied — don't let the key suppress a later retry.
            if ($idempotencyKey !== null) {
                Cache::forget("device-pulse:{$token->id}:{$idempotencyKey}");
            }

            return response()->json([
                'accepted' => false,
                'error' => $message->processing_error,
            ], 422);
        }

        return response()->json(['accepted' => true], 202);
    }
}
