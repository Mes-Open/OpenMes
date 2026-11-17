<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IngestMachineSignalsRequest;
use App\Http\Requests\Api\V1\MachineGatewayHeartbeatRequest;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\RuntimeMonitor;
use App\Services\Machine\WorkstationStateMachine;
use App\Support\MachineTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Bridge endpoint for external protocol gateways (OPC UA sidecar, custom REST
 * pushers). The gateway fetches its config (which nodes/tags to read), then
 * posts normalized readings back here; each reading flows through the same
 * MachineSignalIngestor as Modbus/MQTT. Posting also refreshes the runtime
 * heartbeat so the UI knows the gateway is alive.
 */
class MachineGatewayController extends Controller
{
    public function __construct(
        private readonly MachineSignalIngestor $ingestor,
        private readonly RuntimeMonitor $runtime,
    ) {}

    /**
     * Config the gateway needs to connect and subscribe.
     */
    public function config(Request $request, MachineConnection $machineConnection): JsonResponse
    {
        $machineConnection->load(['opcuaConnection', 'activeTags']);
        $opc = $machineConnection->opcuaConnection;

        return response()->json([
            'connection' => [
                'id' => $machineConnection->id,
                'name' => $machineConnection->name,
                'protocol' => $machineConnection->protocol,
                'is_active' => $machineConnection->is_active,
            ],
            'opcua' => $opc ? [
                'endpoint_url' => $opc->endpoint_url,
                'security_policy' => $opc->security_policy,
                'security_mode' => $opc->security_mode,
                'auth_mode' => $opc->auth_mode,
                'username' => $opc->username,
                // The password saved in Admin → Connectivity, so it does not
                // also have to be copied into the gateway's environment.
                'password' => $opc->auth_mode === 'username' && $opc->password_encrypted && $this->mayReadSecrets($request)
                    ? Crypt::decryptString($opc->password_encrypted)
                    : null,
                'publishing_interval_ms' => $opc->publishing_interval_ms,
            ] : null,
            'tags' => $machineConnection->activeTags->map(fn (MachineTag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'node_id' => $t->address,
                'signal_type' => $t->signal_type,
                'data_type' => $t->data_type,
            ])->values(),
        ]);
    }

    /**
     * Sessions and full-access tokens of any user also pass this route group;
     * the machine's password goes only to a token issued for a gateway, or to
     * an admin, who could set it anyway.
     */
    private function mayReadSecrets(Request $request): bool
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $abilities = $token instanceof \Laravel\Sanctum\PersonalAccessToken ? (array) $token->abilities : [];

        return in_array(ApiTokenScope::GATEWAY_ABILITY, $abilities, true)
            || ($user !== null && $user->hasRole('Admin'));
    }

    /**
     * Receive normalized readings from the gateway and ingest them.
     *
     * Body: { readings: [ { tag_id?, node_id?, value, ts?, event_id? }, ... ] }
     *
     * The gateway resends a whole batch when it gets no answer, including one
     * this method already committed. A reading whose event_id was ingested
     * before is therefore skipped: for counters MachineCounterService would
     * catch it, but a replayed state change or alarm would be recorded again.
     *
     * A reading the data itself rules out (a state value no workstation state
     * matches) is reported back in `rejected` and the rest of the batch goes
     * through; otherwise one misconfigured tag would hold every other reading
     * of the connection in the gateway's retry queue. Any other failure still
     * fails the request, so the gateway retries the batch.
     */
    public function ingest(IngestMachineSignalsRequest $request, MachineConnection $machineConnection): JsonResponse
    {
        $data = $request->validated();

        // Heartbeat: a posting gateway is, by definition, alive.
        $this->runtime->heartbeat($machineConnection->protocol, $machineConnection->id);
        $machineConnection->markConnected();

        $tags = $machineConnection->activeTags()->get()->keyBy('id');
        $byAddress = $tags->keyBy('address');

        $accepted = 0;
        $duplicates = 0;
        $rejected = [];
        foreach ($data['readings'] as $index => $r) {
            $tag = isset($r['tag_id']) ? $tags->get($r['tag_id']) : null;
            $tag ??= isset($r['node_id']) ? $byAddress->get($r['node_id']) : null;
            if (! $tag) {
                continue;
            }

            $eventId = isset($r['event_id']) && $r['event_id'] !== '' ? (string) $r['event_id'] : null;
            $receipt = $eventId !== null ? $this->receiptKey($machineConnection, $eventId) : null;
            if ($receipt !== null && Cache::has($receipt)) {
                $duplicates++;

                continue;
            }

            try {
                $this->ingestor->ingest($tag, $r['value'], MachineTime::parse($r['ts'] ?? null), $eventId);
            } catch (\InvalidArgumentException $e) {
                $rejected[] = ['index' => $index, 'tag_id' => $tag->id, 'event_id' => $eventId, 'error' => $e->getMessage()];
                Log::warning('Machine reading rejected', ['machine_connection_id' => $machineConnection->id, 'tag_id' => $tag->id, 'error' => $e->getMessage()]);

                continue;
            }

            if ($receipt !== null) {
                Cache::put($receipt, true, now()->addDay());
            }
            $machineConnection->increment('messages_received');
            $accepted++;
        }

        return response()->json(['accepted' => $accepted, 'duplicates' => $duplicates, 'rejected' => $rejected]);
    }

    /** Remembers an ingested event_id long enough to outlast any gateway retry. */
    private function receiptKey(MachineConnection $connection, string $eventId): string
    {
        return 'machine-reading:'.$connection->id.':'.hash('sha256', $eventId);
    }

    /**
     * Standalone heartbeat (gateway connected but no readings to push yet).
     *
     * The gateway posts readings only when a value changes, so this is also
     * what keeps an unchanging machine — one sitting in FAULT, say — from
     * reading as "No data" on the plant overview: its stations are marked as
     * heard from.
     *
     * A gateway that is running but cannot reach its machine sends
     * `status: error` with the reason: it still counts as alive (the container
     * is up), the connection is put into error with that message, and the
     * stations are NOT marked as heard from — nothing was read from them.
     */
    public function heartbeat(
        MachineGatewayHeartbeatRequest $request,
        MachineConnection $machineConnection,
        WorkstationStateMachine $states,
    ): JsonResponse {
        $this->runtime->heartbeat($machineConnection->protocol, $machineConnection->id);

        if ($request->isError()) {
            $machineConnection->markError((string) $request->validated('message'));

            return response()->json(['ok' => true]);
        }

        if ($machineConnection->status !== MachineConnection::STATUS_CONNECTED) {
            $machineConnection->markConnected();
        }
        $states->markConnectionSignal($machineConnection);

        return response()->json(['ok' => true]);
    }
}
