<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineMessage;
use App\Models\MqttConnection;
use Illuminate\Http\Request;

class MqttConnectionController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::where('protocol', 'mqtt')
            ->with(['mqttConnection', 'topics'])
            ->withCount('topics')
            ->orderBy('name')
            ->get();

        return view('admin.connectivity.mqtt.index', compact('connections'));
    }

    public function create()
    {
        return view('admin.connectivity.mqtt.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            'description'             => ['nullable', 'string', 'max:500'],
            'is_active'               => ['boolean'],
            'broker_host'             => ['required', 'string', 'max:255'],
            'broker_port'             => ['required', 'integer', 'min:1', 'max:65535'],
            'client_id'               => ['nullable', 'string', 'max:100'],
            'username'                => ['nullable', 'string', 'max:100'],
            'password'                => ['nullable', 'string', 'max:255'],
            'use_tls'                 => ['boolean'],
            'ca_cert'                 => ['nullable', 'string'],
            'keep_alive_seconds'      => ['required', 'integer', 'min:5', 'max:3600'],
            'qos_default'             => ['required', 'integer', 'in:0,1,2'],
            'clean_session'           => ['boolean'],
            'connect_timeout'         => ['required', 'integer', 'min:1', 'max:120'],
            'reconnect_delay_seconds' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $connection = MachineConnection::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'protocol'    => 'mqtt',
            'is_active'   => $request->boolean('is_active'),
        ]);

        $mqtt = new MqttConnection([
            'machine_connection_id'   => $connection->id,
            'broker_host'             => $validated['broker_host'],
            'broker_port'             => $validated['broker_port'],
            'client_id'               => $validated['client_id'] ?? null,
            'username'                => $validated['username'] ?? null,
            'use_tls'                 => $request->boolean('use_tls'),
            'ca_cert'                 => $validated['ca_cert'] ?? null,
            'keep_alive_seconds'      => $validated['keep_alive_seconds'],
            'qos_default'             => $validated['qos_default'],
            'clean_session'           => $request->boolean('clean_session', true),
            'connect_timeout'         => $validated['connect_timeout'],
            'reconnect_delay_seconds' => $validated['reconnect_delay_seconds'],
        ]);

        if (!empty($validated['password'])) {
            $mqtt->setPasswordAttribute($validated['password']);
        }

        $mqtt->save();

        return redirect()
            ->route('admin.connectivity.mqtt.show', $connection)
            ->with('success', 'MQTT connection created.');
    }

    public function show(MachineConnection $mqttConnection)
    {
        $mqttConnection->load([
            'mqttConnection',
            'topics.activeMappings',
        ]);

        $recentMessages = MachineMessage::where('machine_connection_id', $mqttConnection->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.connectivity.mqtt.show', [
            'connection'     => $mqttConnection,
            'recentMessages' => $recentMessages,
        ]);
    }

    public function edit(MachineConnection $mqttConnection)
    {
        $mqttConnection->load(['mqttConnection', 'topics.mappings']);
        return view('admin.connectivity.mqtt.edit', ['connection' => $mqttConnection]);
    }

    public function update(Request $request, MachineConnection $mqttConnection)
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            'description'             => ['nullable', 'string', 'max:500'],
            'is_active'               => ['boolean'],
            'broker_host'             => ['required', 'string', 'max:255'],
            'broker_port'             => ['required', 'integer', 'min:1', 'max:65535'],
            'client_id'               => ['nullable', 'string', 'max:100'],
            'username'                => ['nullable', 'string', 'max:100'],
            'password'                => ['nullable', 'string', 'max:255'],
            'use_tls'                 => ['boolean'],
            'ca_cert'                 => ['nullable', 'string'],
            'keep_alive_seconds'      => ['required', 'integer', 'min:5', 'max:3600'],
            'qos_default'             => ['required', 'integer', 'in:0,1,2'],
            'clean_session'           => ['boolean'],
            'connect_timeout'         => ['required', 'integer', 'min:1', 'max:120'],
            'reconnect_delay_seconds' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $mqttConnection->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $request->boolean('is_active'),
        ]);

        $mqtt = $mqttConnection->mqttConnection ?? new MqttConnection(['machine_connection_id' => $mqttConnection->id]);
        $mqtt->fill([
            'broker_host'             => $validated['broker_host'],
            'broker_port'             => $validated['broker_port'],
            'client_id'               => $validated['client_id'] ?? null,
            'username'                => $validated['username'] ?? null,
            'use_tls'                 => $request->boolean('use_tls'),
            'ca_cert'                 => $validated['ca_cert'] ?? null,
            'keep_alive_seconds'      => $validated['keep_alive_seconds'],
            'qos_default'             => $validated['qos_default'],
            'clean_session'           => $request->boolean('clean_session', true),
            'connect_timeout'         => $validated['connect_timeout'],
            'reconnect_delay_seconds' => $validated['reconnect_delay_seconds'],
        ]);

        if ($request->filled('password')) {
            $mqtt->setPasswordAttribute($validated['password']);
        }

        $mqtt->save();

        return redirect()
            ->route('admin.connectivity.mqtt.show', $mqttConnection)
            ->with('success', 'MQTT connection updated.');
    }

    public function destroy(MachineConnection $mqttConnection)
    {
        $mqttConnection->delete();
        return redirect()
            ->route('admin.connectivity.mqtt.index')
            ->with('success', 'Connection deleted.');
    }

    public function toggleActive(MachineConnection $mqttConnection)
    {
        $mqttConnection->update(['is_active' => !$mqttConnection->is_active]);
        return back()->with('success', 'Connection ' . ($mqttConnection->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * API endpoint: return last N messages as JSON (used by live log polling fallback).
     */
    public function messages(MachineConnection $mqttConnection, Request $request)
    {
        $afterId  = $request->integer('after_id', 0);
        $messages = MachineMessage::where('machine_connection_id', $mqttConnection->id)
            ->when($afterId, fn($q) => $q->where('id', '>', $afterId))
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn($m) => [
                'id'                => $m->id,
                'topic'             => $m->topic,
                'raw_payload'       => $m->raw_payload,
                'parsed_data'       => $m->parsed_data,
                'actions_triggered' => $m->actions_triggered,
                'processing_status' => $m->processing_status,
                'processing_error'  => $m->processing_error,
                'received_at'       => $m->received_at?->toIso8601String(),
            ]);

        return response()->json($messages);
    }
}
