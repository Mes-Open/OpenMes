<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ModbusConnection;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModbusConnectionController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::where('protocol', MachineConnection::PROTOCOL_MODBUS)
            ->with(['modbusConnection', 'tags'])
            ->orderBy('name')
            ->get();

        return view('admin.connectivity.modbus.index', compact('connections'));
    }

    public function create()
    {
        return view('admin.connectivity.modbus.create', [
            'workstations' => Workstation::with('line:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
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

        return view('admin.connectivity.modbus.show', [
            'connection' => $machineConnection,
            'workstations' => Workstation::with('line:id,name')->orderBy('name')->get(),
        ]);
    }

    public function edit(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_MODBUS, 404);
        $machineConnection->load('modbusConnection');

        return view('admin.connectivity.modbus.edit', ['connection' => $machineConnection]);
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

    /** Add a tag to a connection. */
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

        $transform = [];
        if (! empty($data['value_map'])) {
            // "1=RUNNING,2=IDLE,3=FAULT" → map
            $map = [];
            foreach (explode(',', $data['value_map']) as $pair) {
                [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, null);
                if ($k !== null && $v !== null) {
                    $map[trim($k)] = trim($v);
                }
            }
            if ($map) {
                $transform['value_map'] = $map;
            }
        }
        if (isset($data['scale'])) {
            $transform['scale'] = (float) $data['scale'];
        }

        MachineTag::create([
            'machine_connection_id' => $machineConnection->id,
            'workstation_id' => $data['workstation_id'] ?? null,
            'name' => $data['name'],
            'address' => $data['address'],
            'signal_type' => $data['signal_type'],
            'data_type' => $data['data_type'],
            'register_type' => $data['register_type'],
            'transform' => $transform ?: null,
        ]);

        return back()->with('success', __('Tag added.'));
    }

    public function destroyTag(MachineConnection $machineConnection, MachineTag $tag)
    {
        abort_unless($tag->machine_connection_id === $machineConnection->id, 404);
        $tag->delete();

        return back()->with('success', __('Tag removed.'));
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
