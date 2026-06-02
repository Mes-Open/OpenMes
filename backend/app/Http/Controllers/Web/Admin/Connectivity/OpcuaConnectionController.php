<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Connectivity\Concerns\ManagesMachineTags;
use App\Models\MachineConnection;
use App\Models\OpcuaConnection;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OpcuaConnectionController extends Controller
{
    use ManagesMachineTags;

    public function index()
    {
        $connections = MachineConnection::where('protocol', MachineConnection::PROTOCOL_OPCUA)
            ->with('opcuaConnection')
            ->withCount('tags')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/connectivity/opcua/Index', [
            'connections' => $connections->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'description'       => $c->description,
                'is_active'         => $c->is_active,
                'status'            => $c->status,
                'status_color'      => $c->statusColor(),
                'tags_count'        => $c->tags_count,
                'endpoint_url'      => $c->opcuaConnection?->endpoint_url,
                'security_policy'   => $c->opcuaConnection?->security_policy,
                'last_connected_at' => $c->last_connected_at?->diffForHumans(),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/connectivity/opcua/Create', [
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
        $opcua = $machineConnection->opcuaConnection;

        return Inertia::render('admin/connectivity/opcua/Show', [
            'connection' => [
                'id'           => $machineConnection->id,
                'name'         => $machineConnection->name,
                'description'  => $machineConnection->description,
                'is_active'    => $machineConnection->is_active,
                'status'         => $machineConnection->status,
                'status_color'   => $machineConnection->statusColor(),
                'status_message' => $machineConnection->status_message,
                'opcua'        => $opcua ? [
                    'endpoint_url'           => $opcua->endpoint_url,
                    'security_policy'        => $opcua->security_policy,
                    'security_mode'          => $opcua->security_mode,
                    'auth_mode'              => $opcua->auth_mode,
                    'username'               => $opcua->username,
                    'publishing_interval_ms' => $opcua->publishing_interval_ms,
                ] : null,
                'tags' => $machineConnection->tags->map(fn ($t) => $this->mapTag($t))->values(),
            ],
            'workstations' => $this->workstationOptions(),
            'runtime'      => app(RuntimeMonitor::class)->connectionRuntime($machineConnection),
        ]);
    }

    public function edit(MachineConnection $machineConnection)
    {
        abort_unless($machineConnection->protocol === MachineConnection::PROTOCOL_OPCUA, 404);
        $machineConnection->load('opcuaConnection');
        $opcua = $machineConnection->opcuaConnection;

        return Inertia::render('admin/connectivity/opcua/Edit', [
            'connection' => [
                'id'          => $machineConnection->id,
                'name'        => $machineConnection->name,
                'description' => $machineConnection->description,
                'is_active'   => $machineConnection->is_active,
                'opcua'       => $opcua ? [
                    'endpoint_url'           => $opcua->endpoint_url,
                    'security_policy'        => $opcua->security_policy,
                    'security_mode'          => $opcua->security_mode,
                    'auth_mode'              => $opcua->auth_mode,
                    'username'               => $opcua->username,
                    'publishing_interval_ms' => $opcua->publishing_interval_ms,
                    'has_password'           => ! empty($opcua->password_encrypted),
                ] : null,
            ],
        ]);
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

        // OPC UA addresses by node id, so there is no Modbus register_type.
        $this->createTag($machineConnection, $data, null);

        return back()->with('success', __('Tag added.'));
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
