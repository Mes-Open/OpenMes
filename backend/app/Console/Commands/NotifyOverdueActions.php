<?php

namespace App\Console\Commands;

use App\Models\IssueAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flags overdue corrective/preventive/containment actions (#11) — outstanding
 * (open/in_progress) actions whose due date has passed. Logs a warning per
 * overdue action (picked up by the security/ops log) and reports to the
 * console. Naturally idempotent: each run reports the current overdue set.
 *
 * Registered in the scheduler (routes/console.php) to run daily.
 */
class NotifyOverdueActions extends Command
{
    protected $signature = 'quality:notify-overdue-actions';

    protected $description = 'Flag overdue corrective/preventive actions on issues';

    public function handle(): int
    {
        $actions = IssueAction::overdue()
            ->with(['issue:id,title', 'assignedTo:id,name'])
            ->orderBy('due_date')
            ->get();

        if ($actions->isEmpty()) {
            $this->info('No overdue actions.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($actions as $action) {
            Log::warning('Issue action overdue', [
                'issue_action_id' => $action->id,
                'issue_id' => $action->issue_id,
                'due_date' => $action->due_date?->toDateString(),
                'assigned_to_id' => $action->assigned_to_id,
            ]);

            $rows[] = [
                $action->id,
                $action->issue?->title ?? "#{$action->issue_id}",
                $action->title,
                $action->due_date?->toDateString(),
                $action->assignedTo?->name ?? '—',
            ];
        }

        $this->table(['Action', 'Issue', 'Title', 'Due', 'Assignee'], $rows);
        $this->warn($actions->count().' overdue action(s).');

        return self::SUCCESS;
    }
}
