<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use Inertia\Inertia;

class ConnectivityController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::withCount(['topics', 'tags'])
            ->with(['mqttConnection', 'modbusConnection', 'opcuaConnection'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/connectivity/Index', [
            'connections' => $connections->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'description'       => $c->description,
                'protocol'          => $c->protocol,
                'is_active'         => $c->is_active,
                'status'            => $c->status,
                'status_color'      => $c->statusColor(),
                'topics_count'      => $c->topics_count,
                'tags_count'        => $c->tags_count,
                'messages_received' => $c->messages_received,
                'last_connected_at' => $c->last_connected_at?->diffForHumans(),
                'endpoint'          => match ($c->protocol) {
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
}
