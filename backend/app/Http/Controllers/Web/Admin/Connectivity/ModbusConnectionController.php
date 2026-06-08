<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Connectivity\Concerns\ManagesMachineTags;
use App\Models\MachineConnection;
use App\Models\ModbusConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModbusConnectionController extends Controller
{
    use ManagesMachineTags;

    public function index()
    {
        $connections = MachineConnection::where('protocol', MachineConnection::PROTOCOL_MODBUS)
            ->with('modbusConnection')
            ->withCount('tags')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/connectivity/modbus/Index', [
            'connections' => $connections->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'description'       => $c->description,
                'is_active'         => $c->is_active,
                'status'            => $c->status,
                'status_color'      => $c->statusColor(),
                'tags_count'        => $c->tags_count,
                'host'              => $c->modbusConnection?->host,
                'port'              => $c->modbusConnection?->port,
                'unit_id'           => $c->modbusConnection?->unit_id,
                'last_connected_at' => $c->last_connected_at?->diffForHumans(),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/connectivity/modbus/Create', [
            'workstations' => $this->workstationOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request) {
            $connection = MachineConnection::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'protocol' => MachineConnection::PROTOCOL_MODBUS,
                'is_active' => $request->boolean('is_active'),
                'status' => MachineConnection::STATUS_DISCONNECTED,
            ]);

            ModbusConnection::create([
                'machine_connection_id' => $connection->id,
                'host' => $data['host'],
                'port' => $data['port'],
                'unit_id' => $data['unit_id'],
                'poll_interval_ms' => $data['poll_interval_ms'],
                'timeout_seconds' => $data['timeout_seconds'],
                'byte_order' => $data['byte_order'],
                'word_order' => $data['word_order'],
            ]);
        });

        return redirect()->route('admin.connectivity.modbus.index')
            ->with('success', __('Modbus connection created.'));
    }

    public function show(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);
        $machineConnection->load(['modbusConnection', 'tags.workstation']);
        $modbus = $machineConnection->modbusConnection;

        return Inertia::render('admin/connectivity/modbus/Show', [
            'connection' => [
                'id'           => $machineConnection->id,
                'name'         => $machineConnection->name,
                'description'  => $machineConnection->description,
                'is_active'    => $machineConnection->is_active,
                'status'         => $machineConnection->status,
                'status_color'   => $machineConnection->statusColor(),
                'status_message' => $machineConnection->status_message,
                'modbus'       => $modbus ? [
                    'host'             => $modbus->host,
                    'port'             => $modbus->port,
                    'unit_id'          => $modbus->unit_id,
                    'poll_interval_ms' => $modbus->poll_interval_ms,
                    'timeout_seconds'  => $modbus->timeout_seconds,
                    'byte_order'       => $modbus->byte_order,
                    'word_order'       => $modbus->word_order,
                ] : null,
                'tags' => $machineConnection->tags->map(fn ($t) => $this->mapTag($t))->values(),
            ],
            'workstations' => $this->workstationOptions(),
            'runtime'      => app(\App\Services\Machine\RuntimeMonitor::class)->connectionRuntime($machineConnection),
        ]);
    }

    public function edit(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);
        $machineConnection->load('modbusConnection');
        $modbus = $machineConnection->modbusConnection;

        return Inertia::render('admin/connectivity/modbus/Edit', [
            'connection' => [
                'id'          => $machineConnection->id,
                'name'        => $machineConnection->name,
                'description' => $machineConnection->description,
                'is_active'   => $machineConnection->is_active,
                'modbus'      => $modbus ? [
                    'host'             => $modbus->host,
                    'port'             => $modbus->port,
                    'unit_id'          => $modbus->unit_id,
                    'poll_interval_ms' => $modbus->poll_interval_ms,
                    'timeout_seconds'  => $modbus->timeout_seconds,
                    'byte_order'       => $modbus->byte_order,
                    'word_order'       => $modbus->word_order,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request, MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);
        $data = $this->validateData($request);

        DB::transaction(function () use ($machineConnection, $data, $request) {
            $machineConnection->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);
            $machineConnection->modbusConnection->update([
                'host' => $data['host'],
                'port' => $data['port'],
                'unit_id' => $data['unit_id'],
                'poll_interval_ms' => $data['poll_interval_ms'],
                'timeout_seconds' => $data['timeout_seconds'],
                'byte_order' => $data['byte_order'],
                'word_order' => $data['word_order'],
            ]);
        });

        return redirect()->route('admin.connectivity.modbus.show', $machineConnection)
            ->with('success', __('Modbus connection updated.'));
    }

    public function destroy(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);
        $machineConnection->delete();

        return redirect()->route('admin.connectivity.modbus.index')
            ->with('success', __('Modbus connection deleted.'));
    }

    /** Add a tag to a connection (register_type is required for Modbus). */
    public function storeTag(Request $request, MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'signal_type' => ['required', 'string', 'max:30'],
            'data_type' => ['required', 'string', 'max:20'],
            'register_type' => ['required', 'string', 'max:20'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'value_map' => ['nullable', 'string'],
            'scale' => ['nullable', 'numeric'],
        ]);

        $this->createTag($machineConnection, $data, $data['register_type']);

        return back()->with('success', __('Tag added.'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'unit_id' => ['required', 'integer', 'min:0', 'max:255'],
            'poll_interval_ms' => ['required', 'integer', 'min:100', 'max:60000'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'byte_order' => ['required', 'in:big,little'],
            'word_order' => ['required', 'in:big,little'],
        ]);
    }
}
