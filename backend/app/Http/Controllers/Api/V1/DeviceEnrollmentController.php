<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PairingCodeUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EnrollDeviceRequest;
use App\Models\DevicePairingCode;
use App\Models\DeviceToken;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * A sensor's first contact. It presents a one-time pairing code (an admin
 * generated in the panel) plus its own name and MAC; if the code is redeemable
 * we create its MachineConnection — a REST device wired with a count_step
 * mapping so each later pulse increments the running order's step — and issue a
 * long-lived device token, returned once. Public (no session); the pairing code
 * is the authorization and it carries the tenant.
 */
class DeviceEnrollmentController extends Controller
{
    /** The fixed topic every enrolled sensor pulses on. */
    public const PULSE_TOPIC = 'device/pulse';

    public function __construct(private readonly TenantContext $tenant) {}

    public function enroll(EnrollDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolve the code with the tenant scope bypassed (no session yet).
        $code = DevicePairingCode::findByPlaintext($data['pairing_code']);

        if ($code === null || ! $code->isRedeemable()) {
            return response()->json(['message' => __('Invalid or expired pairing code.')], 422);
        }

        // Establish the code's tenant before any write so HasTenant stamps every
        // new row (connection, topic, mapping, token) with the right tenant.
        $previousTenant = $this->tenant->id();
        $this->tenant->set($code->tenant_id);

        try {
            $result = DB::transaction(function () use ($code, $data) {
                // Atomically claim the code before provisioning anything. A single
                // conditional UPDATE means only one of two concurrent requests can
                // flip used_at — the loser gets 0 rows and is rejected, so one code
                // never provisions two devices/tokens.
                $claimed = DevicePairingCode::withoutGlobalScope(TenantScope::class)
                    ->whereKey($code->id)
                    ->whereNull('used_at')
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->update(['used_at' => now(), 'is_active' => false]);

                if ($claimed === 0) {
                    // Lost the race, or the code was spent/expired between checks.
                    throw new PairingCodeUnavailableException;
                }

                $connection = MachineConnection::create([
                    'name' => $data['name'],
                    'protocol' => MachineConnection::PROTOCOL_REST,
                    'line_id' => $code->line_id,
                    'mac_address' => strtoupper($data['mac_address']),
                    'is_active' => true,
                    'status' => MachineConnection::STATUS_CONNECTED,
                    'last_connected_at' => now(),
                ]);

                $topic = MachineTopic::create([
                    'machine_connection_id' => $connection->id,
                    'topic_pattern' => self::PULSE_TOPIC,
                    'payload_format' => MachineTopic::FORMAT_JSON,
                    'description' => 'Sensor pulse ingest',
                    'is_active' => true,
                ]);

                // Each pulse is one unit leaving the station. The device drives the
                // count via the payload's `pulse` field. If the admin bound a
                // station at pairing time, count that step; otherwise treat this
                // sensor as the line's finished-goods point (feed produced_qty), so
                // an unbound device still counts something meaningful.
                $params = ['increment_path' => '$.pulse'];
                if ($code->workstation_id !== null) {
                    $params['workstation_id'] = $code->workstation_id;
                } else {
                    $params['also_count_work_order'] = true;
                }

                TopicMapping::create([
                    'machine_topic_id' => $topic->id,
                    // JSONPath into the pulse payload — '$.' prefix marks a data
                    // lookup; a bare word would be read as a literal.
                    'field_path' => '$.pulse',
                    'action_type' => TopicMapping::ACTION_COUNT_STEP,
                    'action_params' => $params,
                    'priority' => 100,
                    'is_active' => true,
                ]);

                [$token, $plaintext] = DeviceToken::issue([
                    'machine_connection_id' => $connection->id,
                    'is_active' => true,
                ]);

                // Record which connection the (already-claimed) code created.
                DevicePairingCode::withoutGlobalScope(TenantScope::class)
                    ->whereKey($code->id)
                    ->update(['used_by_connection_id' => $connection->id]);

                return [$connection, $token, $plaintext];
            });
        } catch (PairingCodeUnavailableException) {
            return response()->json(['message' => __('Invalid or expired pairing code.')], 422);
        } finally {
            $this->tenant->set($previousTenant);
        }

        [$connection, , $plaintext] = $result;

        return response()->json([
            'device' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'line_id' => $connection->line_id,
                'mac_address' => $connection->mac_address,
            ],
            // Returned exactly once — the device must persist it now.
            'token' => $plaintext,
            'pulse_url' => url('/api/v1/devices/pulse'),
        ], 201);
    }
}
