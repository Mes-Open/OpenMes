<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\MachineConnection;
use App\Models\MaintenanceEvent;
use App\Models\WorkOrder;
use App\Services\ModuleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    // ── Settings (key/value) ────────────────────────────────────────────────

    public function listSettings(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $rows = DB::table('system_settings')->orderBy('key')->get()
            ->map(fn($r) => [
                'key' => $r->key,
                'value' => json_decode($r->value, true),
                'description' => $r->description,
                'updated_at' => $r->updated_at,
            ])->values();
        return response()->json(['data' => $rows]);
    }

    public function showSetting(Request $request, string $key): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $row = DB::table('system_settings')->where('key', $key)->first();
        if (!$row) return response()->json(['message' => 'Setting not found'], 404);
        return response()->json(['data' => [
            'key' => $row->key,
            'value' => json_decode($row->value, true),
            'description' => $row->description,
            'updated_at' => $row->updated_at,
        ]]);
    }

    public function updateSetting(Request $request, string $key): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $request->validate(['value' => ['present']]);
        $row = DB::table('system_settings')->where('key', $key)->first();
        if (!$row) return response()->json(['message' => 'Setting not found'], 404);
        DB::table('system_settings')->where('key', $key)->update([
            'value' => json_encode($request->input('value')),
            'updated_at' => now(),
        ]);
        return response()->json([
            'message' => 'Setting updated',
            'data' => [
                'key' => $key,
                'value' => $request->input('value'),
            ],
        ]);
    }

    // ── Modules registration ────────────────────────────────────────────────

    public function listModules(Request $request, ModuleManager $manager): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return response()->json(['data' => $manager->discover()]);
    }

    public function enableModule(Request $request, ModuleManager $manager, string $name): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $manager->enable($name);
        return response()->json(['message' => "Module {$name} enabled"]);
    }

    public function disableModule(Request $request, ModuleManager $manager, string $name): JsonResponse
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $manager->disable($name);
        return response()->json(['message' => "Module {$name} disabled"]);
    }

    // ── Schedule (calendar aggregate) ───────────────────────────────────────

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'type' => ['nullable', 'in:maintenance,work_order,all'],
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $lineId = $request->input('line_id');
        $type = $request->input('type', 'all');

        $events = collect();

        if ($type === 'all' || $type === 'maintenance') {
            $events = $events->concat(
                MaintenanceEvent::query()
                    ->whereBetween('scheduled_at', [$from, $to])
                    ->when($lineId, fn($q) => $q->where('line_id', $lineId))
                    ->get()
                    ->map(fn($e) => [
                        'type' => 'maintenance',
                        'id' => $e->id,
                        'title' => $e->title,
                        'starts_at' => $e->scheduled_at?->toIso8601String(),
                        'ends_at' => $e->completed_at?->toIso8601String(),
                        'status' => $e->status,
                        'color' => $e->status === 'completed' ? '#16a34a' : ($e->status === 'cancelled' ? '#94a3b8' : '#d97706'),
                    ])
            );
        }

        if ($type === 'all' || $type === 'work_order') {
            $events = $events->concat(
                WorkOrder::query()
                    ->whereBetween('due_date', [$from, $to])
                    ->when($lineId, fn($q) => $q->where('line_id', $lineId))
                    ->get()
                    ->map(fn($wo) => [
                        'type' => 'work_order',
                        'id' => $wo->id,
                        'title' => "{$wo->order_no}",
                        'starts_at' => $wo->due_date?->toIso8601String(),
                        'ends_at' => null,
                        'status' => $wo->status,
                        'color' => match ($wo->status) {
                            WorkOrder::STATUS_DONE => '#16a34a',
                            WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_REJECTED, WorkOrder::STATUS_CANCELLED => '#dc2626',
                            WorkOrder::STATUS_IN_PROGRESS => '#2563eb',
                            default => '#94a3b8',
                        },
                    ])
            );
        }

        return response()->json(['data' => $events->sortBy('starts_at')->values()]);
    }

    // ── Alerts dashboard ────────────────────────────────────────────────────

    public function alertsCounts(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $issues = Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])->count();
        $maintenance = MaintenanceEvent::whereIn('status', ['pending', 'in_progress'])->count();
        $machinesOffline = MachineConnection::where('is_active', true)
            ->whereIn('status', ['error', 'disconnected'])->count();

        return response()->json([
            'data' => [
                'issues' => $issues,
                'maintenance' => $maintenance,
                'machines' => $machinesOffline,
                'total' => $issues + $maintenance + $machinesOffline,
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['Admin', 'Supervisor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $type = $request->query('type', 'all');
        $alerts = collect();

        if ($type === 'all' || $type === 'issue') {
            $alerts = $alerts->concat(
                Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                    ->with(['issueType', 'workOrder'])
                    ->orderByDesc('reported_at')
                    ->limit(50)
                    ->get()
                    ->map(fn($i) => [
                        'type' => 'issue',
                        'id' => $i->id,
                        'title' => $i->title ?? $i->issueType?->name ?? 'Issue',
                        'severity' => $i->issueType?->severity ?? 'MEDIUM',
                        'status' => $i->status,
                        'created_at' => $i->reported_at?->toIso8601String(),
                        'link' => "/issues/{$i->id}",
                    ])
            );
        }

        if ($type === 'all' || $type === 'maintenance') {
            $alerts = $alerts->concat(
                MaintenanceEvent::whereIn('status', ['pending', 'in_progress'])
                    ->orderByDesc('scheduled_at')
                    ->limit(50)
                    ->get()
                    ->map(fn($e) => [
                        'type' => 'maintenance',
                        'id' => $e->id,
                        'title' => $e->title,
                        'severity' => $e->event_type === 'corrective' ? 'HIGH' : 'MEDIUM',
                        'status' => $e->status,
                        'created_at' => $e->scheduled_at?->toIso8601String(),
                        'link' => "/maintenance-events/{$e->id}",
                    ])
            );
        }

        if ($type === 'all' || $type === 'machine_offline') {
            $alerts = $alerts->concat(
                MachineConnection::where('is_active', true)
                    ->whereIn('status', ['error', 'disconnected'])
                    ->get()
                    ->map(fn($c) => [
                        'type' => 'machine_offline',
                        'id' => $c->id,
                        'title' => "{$c->name} ({$c->protocol})",
                        'severity' => 'HIGH',
                        'status' => $c->status,
                        'created_at' => $c->last_connected_at?->toIso8601String(),
                        'link' => "/connectivity/connections/{$c->id}",
                    ])
            );
        }

        return response()->json(['data' => $alerts->sortByDesc('created_at')->values()]);
    }

    // ── Update Check ────────────────────────────────────────────────────────

    public function updateCheck(): JsonResponse
    {
        $current = config('app.version', '0.5.0');
        return response()->json([
            'data' => [
                'current_version' => $current,
                'latest_version' => $current,
                'update_available' => false,
                'release_notes_url' => null,
            ],
        ]);
    }
}
