<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineCounter;
use Inertia\Inertia;

class ConnectivityController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::withCount(['topics', 'tags'])
            ->with(['mqttConnection', 'modbusConnection', 'opcuaConnection'])
            ->orderBy('name')
            ->get();

        $counters = MachineCounter::whereIn('machine_connection_id', $connections->pluck('id'))
            ->orderBy('id')->get(['id', 'machine_connection_id'])->groupBy('machine_connection_id');

        return Inertia::render('admin/connectivity/Index', [
            'counts' => MachineConnection::protocolCounts(),
            'connections' => $connections->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'protocol' => $c->protocol,
                'counter_ids' => $counters->get($c->id, collect())->pluck('id')->values(),
                'is_active' => $c->is_active,
                'status' => $c->liveStatus(),
                'status_color' => $c->liveStatusColor(),
                'status_message' => $c->liveStatusMessage(),
                'topics_count' => $c->topics_count,
                'tags_count' => $c->tags_count,
                'messages_received' => $c->messages_received,
                'last_connected_at' => $c->last_connected_at?->diffForHumans(),
                'endpoint' => match ($c->protocol) {
                    MachineConnection::PROTOCOL_MQTT => $c->mqttConnection
                        ? $c->mqttConnection->broker_host.':'.$c->mqttConnection->broker_port
                        : null,
                    MachineConnection::PROTOCOL_MODBUS => $c->modbusConnection
                        ? $c->modbusConnection->host.':'.$c->modbusConnection->port
                        : null,
                    MachineConnection::PROTOCOL_OPCUA => $c->opcuaConnection?->endpoint_url,
                    default => null,
                },
            ]),
        ]);
    }

    /**
     * Switch a connection on or off. Its collector (poller, listener or
     * gateway) picks the change up on its next config refresh.
     */
    public function toggleActive(MachineConnection $machineConnection)
    {
        $machineConnection->update(['is_active' => ! $machineConnection->is_active]);

        return back()->with('success', $machineConnection->is_active ? __('Connection activated.') : __('Connection deactivated.'));
    }
}
