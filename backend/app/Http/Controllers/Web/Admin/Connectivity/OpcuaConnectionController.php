<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\OpcuaConnection;
use App\Models\Workstation;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class OpcuaConnectionController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::where('protocol', MachineConnection::PROTOCOL_OPCUA)
            ->with(['opcuaConnection', 'tags'])
            ->orderBy('name')
            ->get();

        return view('admin.connectivity.opcua.index', compact('connections'));
    }

    public function create()
    {
        return view('admin.connectivity.opcua.create', [
            'workstations' => Workstation::with('line:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request) {
            $connection = MachineConnection::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'protocol' => MachineConnection::PROTOCOL_OPCUA,
                'is_active' => $request->boolean('is_active'),
                'status' => MachineConnection::STATUS_DISCONNECTED,
            ]);

            OpcuaConnection::create([
                'machine_connection_id' => $connection->id,
                'endpoint_url' => $data['endpoint_url'],
                'security_policy' => $data['security_policy'],
                'security_mode' => $data['security_mode'],
                'auth_mode' => $data['auth_mode'],
                'username' => $data['username'] ?? null,
                'password_encrypted' => ! empty($data['password']) ? Crypt::encryptString($data['password']) : null,
                'publishing_interval_ms' => $data['publishing_interval_ms'],
            ]);
        });

        return redirect()->route('admin.connectivity.opcua.index')
            ->with('success', __('OPC UA connection created.'));
    }

    public function show(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);
        $machineConnection->load(['opcuaConnection', 'tags.workstation']);

        return view('admin.connectivity.opcua.show', [
            'connection' => $machineConnection,
            'workstations' => Workstation::with('line:id,name')->orderBy('name')->get(),
            'runtime' => app(RuntimeMonitor::class)->connectionRuntime($machineConnection),
        ]);
    }

    public function edit(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);
        $machineConnection->load('opcuaConnection');

        return view('admin.connectivity.opcua.edit', ['connection' => $machineConnection]);
    }

    public function update(Request $request, MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);
        $data = $this->validateData($request);

        DB::transaction(function () use ($machineConnection, $data, $request) {
            $machineConnection->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);
            $update = [
                'endpoint_url' => $data['endpoint_url'],
                'security_policy' => $data['security_policy'],
                'security_mode' => $data['security_mode'],
                'auth_mode' => $data['auth_mode'],
                'username' => $data['username'] ?? null,
                'publishing_interval_ms' => $data['publishing_interval_ms'],
            ];
            if (! empty($data['password'])) {
                $update['password_encrypted'] = Crypt::encryptString($data['password']);
            }
            $machineConnection->opcuaConnection->update($update);
        });

        return redirect()->route('admin.connectivity.opcua.show', $machineConnection)
            ->with('success', __('OPC UA connection updated.'));
    }

    public function destroy(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);
        $machineConnection->delete();

        return redirect()->route('admin.connectivity.opcua.index')
            ->with('success', __('OPC UA connection deleted.'));
    }

    public function storeTag(Request $request, MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'], // node id, e.g. ns=2;s=State
            'signal_type' => ['required', 'string', 'max:30'],
            'data_type' => ['required', 'string', 'max:20'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'value_map' => ['nullable', 'string'],
            'scale' => ['nullable', 'numeric'],
        ]);

        $transform = [];
        if (! empty($data['value_map'])) {
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
            'register_type' => null,
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
            'endpoint_url' => ['required', 'string', 'max:500'],
            'security_policy' => ['required', 'in:None,Basic256Sha256'],
            'security_mode' => ['required', 'in:None,Sign,SignAndEncrypt'],
            'auth_mode' => ['required', 'in:anonymous,username,certificate'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'publishing_interval_ms' => ['required', 'integer', 'min:100', 'max:60000'],
        ]);
    }
}
