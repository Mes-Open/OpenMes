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
