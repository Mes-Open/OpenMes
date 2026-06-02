<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\WorkOrder;
use Inertia\Inertia;

class AlertController extends Controller
{
    public function index()
    {
        // The page live-syncs entirely from Electric shapes (issues_all,
        // issue_types, work_orders_all, lines_all, users) and derives the
        // blocking / non-blocking / overdue / blocked lists client-side, so
        // state changes appear without a refresh. No server props needed.
        return Inertia::render('admin/alerts/Index');
    }

    /**
     * JSON endpoint for real-time polling.
     */
    public function check()
    {
        return response()->json([
            'total' => static::totalCount(),
            'latest_issue_at' => Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
                ->max('created_at'),
        ]);
    }

    /**
     * Returns total alert count for navbar badge (called via shared view composer).
     */
    public static function totalCount(): int
    {
        $allOpenIssues = Issue::whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->count();

        $overdue = WorkOrder::whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->count();

        $blocked = WorkOrder::where('status', WorkOrder::STATUS_BLOCKED)->count();

        return $allOpenIssues + $overdue + $blocked;
    }
}
