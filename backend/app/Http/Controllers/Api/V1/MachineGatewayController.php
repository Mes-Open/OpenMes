<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function config(MachineConnection $machineConnection): JsonResponse
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
     * Receive normalized readings from the gateway and ingest them.
     *
     * Body: { readings: [ { tag_id?, node_id?, value, ts? }, ... ] }
     */
    public function ingest(Request $request, MachineConnection $machineConnection): JsonResponse
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.tag_id' => ['nullable', 'integer'],
            'readings.*.node_id' => ['nullable', 'string'],
            'readings.*.value' => ['present'],
            'readings.*.ts' => ['nullable', 'date'],
        ]);

        // Heartbeat: a posting gateway is, by definition, alive.
        $this->runtime->heartbeat($machineConnection->protocol, $machineConnection->id);
        $machineConnection->markConnected();

        $tags = $machineConnection->activeTags()->get()->keyBy('id');
        $byAddress = $machineConnection->activeTags()->get()->keyBy('address');

        $accepted = 0;
        foreach ($data['readings'] as $r) {
            $tag = isset($r['tag_id']) ? $tags->get($r['tag_id']) : null;
            $tag ??= isset($r['node_id']) ? $byAddress->get($r['node_id']) : null;
            if (! $tag) {
                continue;
            }
            $at = isset($r['ts']) ? \Illuminate\Support\Carbon::parse($r['ts']) : null;
            $this->ingestor->ingest($tag, $r['value'], $at);
            $machineConnection->increment('messages_received');
            $accepted++;
        }

        return response()->json(['accepted' => $accepted]);
    }

    /**
     * Standalone heartbeat (gateway connected but no readings to push yet).
     */
    public function heartbeat(MachineConnection $machineConnection): JsonResponse
    {
        $this->runtime->heartbeat($machineConnection->protocol, $machineConnection->id);

        return response()->json(['ok' => true]);
    }
}
