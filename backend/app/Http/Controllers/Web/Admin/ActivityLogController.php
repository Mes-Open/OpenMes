<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;

class ActivityLogController extends Controller
{
    /**
     * Per-source safety cap when merging.
     */
    protected const SOURCE_LIMIT = 200;

    /**
     * Per-source safety cap on CSV export.
     */
    protected const EXPORT_LIMIT = 5000;

    /**
     * Display a unified timeline of audit_logs + request_logs.
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $source = $request->input('source');
        $userId = $request->input('user_id');
        $action = $request->input('action');
        $entityType = $request->input('entity_type');

        $audits = collect();
        $requests = collect();

        // Audit logs (Tier A + Tier C — login/logout/login_failed inherit this table)
        if ($source !== 'request') {
            $auditQuery = AuditLog::with('user')
                ->whereBetween('created_at', [$from, $to]);

            if ($userId) {
                $auditQuery->where('user_id', $userId);
            }
            if ($action) {
                $auditQuery->where('action', $action);
            }
            if ($entityType) {
                $auditQuery->where('entity_type', $entityType);
            }

            $audits = $auditQuery->orderByDesc('created_at')
                ->limit(self::SOURCE_LIMIT)
                ->get()
                ->map(function (AuditLog $log) {
                    $log->setAttribute('source', 'audit');
                    return $log;
                });
        }

        // Request logs (Tier B)
        if ($source !== 'audit') {
            // If user filtered to a specific entity_type or audit action, hide requests
            // (those filters do not apply to request_logs).
            $skipRequests = $entityType || $action;

            if (! $skipRequests) {
                $requestQuery = RequestLog::with('user')
                    ->whereBetween('created_at', [$from, $to]);

                if ($userId) {
                    $requestQuery->where('user_id', $userId);
                }

                $requests = $requestQuery->orderByDesc('created_at')
                    ->limit(self::SOURCE_LIMIT)
                    ->get()
                    ->map(function (RequestLog $log) {
                        $log->setAttribute('source', 'request');
                        return $log;
                    });
            }
        }

        // PHP merge — simpler than SQL union and dialect-agnostic.
        $merged = $audits->concat($requests)
            ->sortByDesc(fn ($row) => $row->created_at?->timestamp ?? 0)
            ->values();

        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));
        $total = $merged->count();

        $logs = new LengthAwarePaginator(
            $merged->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Lookups for the filter dropdowns
        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = AuditLog::query()->distinct()->pluck('action')->filter()->sort()->values();
        $entityTypes = AuditLog::query()->distinct()->pluck('entity_type')->filter()->sort()->values();

        return view('admin.logs.activity', compact(
            'logs',
            'users',
            'actions',
            'entityTypes',
            'from',
            'to'
        ));
    }

    /**
     * Stream a CSV export of the same merged timeline.
     */
    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $source = $request->input('source');
        $userId = $request->input('user_id');
        $action = $request->input('action');
        $entityType = $request->input('entity_type');

        $audits = collect();
        $requests = collect();

        if ($source !== 'request') {
            $auditQuery = AuditLog::with('user')
                ->whereBetween('created_at', [$from, $to]);

            if ($userId) {
                $auditQuery->where('user_id', $userId);
            }
            if ($action) {
                $auditQuery->where('action', $action);
            }
            if ($entityType) {
                $auditQuery->where('entity_type', $entityType);
            }

            $audits = $auditQuery->orderByDesc('created_at')
                ->limit(self::EXPORT_LIMIT)
                ->get()
                ->map(function (AuditLog $log) {
                    $log->setAttribute('source', 'audit');
                    return $log;
                });
        }

        if ($source !== 'audit') {
            $skipRequests = $entityType || $action;

            if (! $skipRequests) {
                $requestQuery = RequestLog::with('user')
                    ->whereBetween('created_at', [$from, $to]);

                if ($userId) {
                    $requestQuery->where('user_id', $userId);
                }

                $requests = $requestQuery->orderByDesc('created_at')
                    ->limit(self::EXPORT_LIMIT)
                    ->get()
                    ->map(function (RequestLog $log) {
                        $log->setAttribute('source', 'request');
                        return $log;
                    });
            }
        }

        $merged = $audits->concat($requests)
            ->sortByDesc(fn ($row) => $row->created_at?->timestamp ?? 0)
            ->values();

        $csv = "created_at,user,source,entity,action,path,method,status,duration_ms,ip\n";

        foreach ($merged as $log) {
            if ($log->source === 'audit') {
                $entity = class_basename($log->entity_type ?? '');
                if ($log->entity_id) {
                    $entity .= ' #'.$log->entity_id;
                }
                $row = [
                    $log->created_at?->toIso8601String(),
                    $log->user?->name ?? 'System',
                    'audit',
                    $entity,
                    $log->action,
                    '',
                    '',
                    '',
                    '',
                    $log->ip_address ?? '',
                ];
            } else {
                $row = [
                    $log->created_at?->toIso8601String(),
                    $log->user?->name ?? 'Guest',
                    'request',
                    '',
                    '',
                    $log->path,
                    $log->method,
                    $log->status,
                    $log->duration_ms,
                    $log->ip_address ?? '',
                ];
            }

            $csv .= implode(',', array_map([$this, 'csvEscape'], $row))."\n";
        }

        $filename = 'activity_log_'.date('Y-m-d_H-i-s').'.csv';

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Resolve the from/to range with a sensible 7-day default.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    /**
     * Minimal CSV escaping.
     */
    protected function csvEscape(string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = (string) $value;

        if (preg_match('/[",\r\n]/', $value)) {
            return '"'.str_replace('"', '""', $value).'"';
        }
        return $value;
    }
}
