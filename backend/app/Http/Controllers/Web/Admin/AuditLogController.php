<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', "%{$request->entity_type}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $auditLogs = $query->paginate(20);

        // Get distinct entity types for filter
        $entityTypes = AuditLog::selectRaw('DISTINCT entity_type')
            ->pluck('entity_type')
            ->map(function ($type) {
                return class_basename($type);
            })
            ->unique()
            ->sort()
            ->values();

        // Get all users for filter
        $users = User::select('id', 'name', 'username')->orderBy('name')->get();

        return view('admin.audit-logs', compact('auditLogs', 'entityTypes', 'users'));
    }

    public function export(Request $request)
    {
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', "%{$request->entity_type}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $auditLogs = $query->limit(10000)->get();

        // Generate CSV
        $csv = "Timestamp,User,Entity,Action,IP Address,Changes\n";

        foreach ($auditLogs as $log) {
            $changes = $this->formatChanges($log);

            $csv .= implode(',', [
                $log->created_at->toIso8601String(),
                $log->user ? '"' . $log->user->username . '"' : 'System',
                '"' . class_basename($log->entity_type) . ' #' . $log->entity_id . '"',
                $log->action,
                $log->ip_address ?? 'N/A',
                '"' . str_replace('"', '""', $changes) . '"',
            ]) . "\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"',
        ]);
    }

    protected function formatChanges(AuditLog $log): string
    {
        if ($log->action === 'created') {
            return 'Created with ' . count($log->after_state ?? []) . ' fields';
        }

        if ($log->action === 'deleted') {
            return 'Deleted';
        }

        if ($log->action === 'updated' && $log->after_state) {
            $changes = [];
            foreach ($log->after_state as $field => $newValue) {
                $oldValue = $log->before_state[$field] ?? 'null';
                $changes[] = "{$field}: {$oldValue} -> {$newValue}";
            }
            return implode('; ', $changes);
        }

        return '';
    }
}
