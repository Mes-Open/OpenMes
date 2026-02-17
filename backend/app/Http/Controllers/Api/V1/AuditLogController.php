<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class AuditLogController extends Controller
{
    /**
     * Get audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|in:created,updated,deleted',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('entity_type')) {
            $query->entity($request->entity_type);
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $auditLogs = $query->paginate($perPage);

        return response()->json([
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'last_page' => $auditLogs->lastPage(),
            ],
        ]);
    }

    /**
     * Get audit logs for a specific entity.
     */
    public function entity(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
        ]);

        $auditLogs = AuditLog::where('entity_type', 'like', "%{$request->entity_type}%")
            ->where('entity_id', $request->entity_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $auditLogs,
        ]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        $request->validate([
            'entity_type' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('entity_type')) {
            $query->entity($request->entity_type);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $auditLogs = $query->limit(10000)->get(); // Limit to prevent memory issues

        // Generate CSV
        $csv = "Timestamp,User,Entity,Action,IP Address,Changes\n";

        foreach ($auditLogs as $log) {
            $changes = $this->formatChanges($log);

            $csv .= implode(',', [
                $log->created_at->toIso8601String(),
                $log->user ? '"' . $log->user->username . '"' : 'System',
                '"' . $log->entity_name . ' #' . $log->entity_id . '"',
                $log->action,
                $log->ip_address ?? 'N/A',
                '"' . $changes . '"',
            ]) . "\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"',
        ]);
    }

    /**
     * Format changes for display.
     */
    protected function formatChanges(AuditLog $log): string
    {
        if ($log->action === 'created') {
            return 'Created with ' . count($log->after_state ?? []) . ' fields';
        }

        if ($log->action === 'deleted') {
            return 'Deleted';
        }

        if ($log->action === 'updated') {
            $changes = [];
            foreach ($log->after_state ?? [] as $field => $newValue) {
                $oldValue = $log->before_state[$field] ?? 'null';
                $changes[] = "{$field}: {$oldValue} -> {$newValue}";
            }
            return implode('; ', $changes);
        }

        return '';
    }
}
