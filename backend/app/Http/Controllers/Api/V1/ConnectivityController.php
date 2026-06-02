<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineMessage;
use App\Models\MachineTopic;
use App\Models\MqttConnection;
use App\Models\TopicMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConnectivityController extends Controller
{
    // ── Machine Connections ────────────────────────────────────────────────

    public function listConnections(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $query = MachineConnection::query()->with('mqttConnection')->withCount(['topics', 'messages']);
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function showConnection(MachineConnection $machineConnection): JsonResponse
    {
        $this->authorize('view', $machineConnection);
        $machineConnection->load(['mqttConnection', 'topics']);
        $machineConnection->loadCount(['topics', 'messages']);
        return response()->json(['data' => $machineConnection]);
    }

    public function storeConnection(Request $request): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $validated = $this->validateConnectionPayload($request);

        $connection = MachineConnection::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'protocol'    => 'mqtt',
            'is_active'   => $request->boolean('is_active'),
        ]);

        $mqtt = new MqttConnection($this->mqttFieldsFromValidated($validated, $request) + [
            'machine_connection_id' => $connection->id,
        ]);
        if (!empty($validated['password'])) {
            $mqtt->setPasswordAttribute($validated['password']);
        }
        $mqtt->save();

        $connection->load('mqttConnection');
        return response()->json(['data' => $connection], 201);
    }

    public function updateConnection(Request $request, MachineConnection $machineConnection): JsonResponse
    {
        $this->authorize('update', $machineConnection);
        $validated = $this->validateConnectionPayload($request);

        $machineConnection->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $request->boolean('is_active'),
        ]);

        $mqtt = $machineConnection->mqttConnection
            ?? new MqttConnection(['machine_connection_id' => $machineConnection->id]);
        $mqtt->fill($this->mqttFieldsFromValidated($validated, $request));
        // Password is optional on update — only rotate it when the client
        // actually sent a non-empty value (so editing other fields doesn't
        // clobber a previously-set credential).
        if ($request->filled('password')) {
            $mqtt->setPasswordAttribute($validated['password']);
        }
        $mqtt->save();

        $machineConnection->load('mqttConnection');
        return response()->json(['data' => $machineConnection]);
    }

    /** Shared validation rules for store + update of MQTT connections. */
    private function validateConnectionPayload(Request $request): array
    {
        return $request->validate([
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
    }

    /** Maps validated payload to MqttConnection attributes (excluding password). */
    private function mqttFieldsFromValidated(array $validated, Request $request): array
    {
        return [
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
        ];
    }

    public function deleteConnection(MachineConnection $machineConnection): JsonResponse
    {
        $this->authorize('delete', $machineConnection);
        $machineConnection->delete();
        return response()->json(['message' => 'Connection deleted']);
    }

    public function toggleConnectionActive(MachineConnection $machineConnection): JsonResponse
    {
        $this->authorize('update', $machineConnection);
        $machineConnection->update(['is_active' => !$machineConnection->is_active]);
        return response()->json(['data' => $machineConnection]);
    }

    // Used by web admin too — kept simple here. Mobile shows status read-only.
    public function showMqttSettings(MachineConnection $machineConnection): JsonResponse
    {
        $this->authorize('view', $machineConnection);
        $mqtt = $machineConnection->mqttConnection;
        if (!$mqtt) {
            return response()->json(['data' => null]);
        }
        // Redact password
        $payload = $mqtt->toArray();
        unset($payload['password_encrypted']);
        return response()->json(['data' => $payload]);
    }

    // ── Machine Topics (read + delete on mobile) ────────────────────────────

    public function listTopics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $query = MachineTopic::query()->with('machineConnection')->withCount('mappings');
        if ($connId = $request->query('machine_connection_id')) {
            $query->where('machine_connection_id', $connId);
        }
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('topic_pattern')->get()]);
    }

    public function showTopic(MachineTopic $machineTopic): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $machineTopic->load(['machineConnection', 'mappings']);
        return response()->json(['data' => $machineTopic]);
    }

    public function storeTopic(Request $request): JsonResponse
    {
        $this->authorize('create', MachineConnection::class); // admin only
        $validated = $request->validate([
            'machine_connection_id' => ['required', 'integer', 'exists:machine_connections,id'],
            'topic_pattern'         => ['required', 'string', 'max:500'],
            'payload_format'        => ['required', 'in:json,plain,csv,hex'],
            'description'           => ['nullable', 'string', 'max:500'],
            'is_active'             => ['boolean'],
        ]);

        $topic = MachineTopic::create($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);
        $topic->load('machineConnection');
        return response()->json(['data' => $topic], 201);
    }

    public function updateTopic(Request $request, MachineTopic $machineTopic): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $validated = $request->validate([
            'topic_pattern'  => ['required', 'string', 'max:500'],
            'payload_format' => ['required', 'in:json,plain,csv,hex'],
            'description'    => ['nullable', 'string', 'max:500'],
            'is_active'      => ['boolean'],
        ]);

        $machineTopic->update($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);
        $machineTopic->load('machineConnection');
        return response()->json(['data' => $machineTopic]);
    }

    public function deleteTopic(MachineTopic $machineTopic): JsonResponse
    {
        $this->authorize('create', MachineConnection::class); // admin only
        $machineTopic->delete();
        return response()->json(['message' => 'Topic deleted']);
    }

    public function toggleTopicActive(MachineTopic $machineTopic): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $machineTopic->update(['is_active' => !$machineTopic->is_active]);
        return response()->json(['data' => $machineTopic]);
    }

    // ── Topic Mappings (read + delete on mobile) ────────────────────────────

    public function listMappings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $query = TopicMapping::query()->with('topic');
        if ($topicId = $request->query('machine_topic_id')) {
            $query->where('machine_topic_id', $topicId);
        }
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('priority')->get()]);
    }

    public function showMapping(TopicMapping $topicMapping): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $topicMapping->load('topic');
        return response()->json(['data' => $topicMapping]);
    }

    public function storeMapping(Request $request): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $validated = $this->validateMappingPayload($request, true);

        $mapping = TopicMapping::create($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);
        $mapping->load('topic');
        return response()->json(['data' => $mapping], 201);
    }

    public function updateMapping(Request $request, TopicMapping $topicMapping): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $validated = $this->validateMappingPayload($request, false);

        $topicMapping->update($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);
        $topicMapping->load('topic');
        return response()->json(['data' => $topicMapping]);
    }

    /**
     * Shared TopicMapping validation. `includeTopicId` toggles requiring
     * machine_topic_id (always set on store, never moved on update).
     */
    private function validateMappingPayload(Request $request, bool $includeTopicId): array
    {
        $rules = [
            'description'    => ['nullable', 'string', 'max:255'],
            'field_path'     => ['nullable', 'string', 'max:255'],
            'action_type'    => ['required', 'in:update_batch_step,update_work_order_qty,create_issue,update_line_status,set_work_order_status,log_event,webhook_forward'],
            'action_params'  => ['nullable'], // object | JSON string
            'condition_expr' => ['nullable', 'string', 'max:255'],
            'priority'       => ['required', 'integer', 'min:1', 'max:9999'],
        ];
        if ($includeTopicId) {
            $rules['machine_topic_id'] = ['required', 'integer', 'exists:machine_topics,id'];
        }
        $validated = $request->validate($rules);

        // action_params accepts either a JSON object (mobile sends real JSON)
        // or a JSON-as-string (legacy web form). Normalize both to a PHP array
        // on the model so the consumer doesn't care.
        $raw = $validated['action_params'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                abort(422, 'Invalid JSON in action_params.');
            }
            $validated['action_params'] = $decoded;
        } elseif ($raw === '' || $raw === null) {
            $validated['action_params'] = null;
        }

        return $validated;
    }

    public function deleteMapping(TopicMapping $topicMapping): JsonResponse
    {
        $this->authorize('create', MachineConnection::class);
        $topicMapping->delete();
        return response()->json(['message' => 'Mapping deleted']);
    }

    // ── Machine Messages (read-only log) ────────────────────────────────────

    public function listMessages(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);

        $query = MachineMessage::query()->with('connection');
        if ($connId = $request->query('machine_connection_id')) {
            $query->where('machine_connection_id', $connId);
        }
        if ($status = $request->query('processing_status')) {
            $query->where('processing_status', $status);
        }
        if ($from = $request->query('from')) $query->where('received_at', '>=', $from);
        if ($to = $request->query('to')) $query->where('received_at', '<=', $to);

        $perPage = max(1, min((int) $request->query('per_page', 30), 100));
        $page = $query->orderByDesc('received_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function showMessage(MachineMessage $machineMessage): JsonResponse
    {
        $this->authorize('viewAny', MachineConnection::class);
        $machineMessage->load('connection');
        return response()->json(['data' => $machineMessage]);
    }
}
