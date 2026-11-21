<?php

namespace App\Services\Schedule;

use App\Models\Issue;
use App\Models\QualityControlTask;

/**
 * What quality holds a work order back: an open blocking quality control
 * (its next step cannot start until the control is performed — see
 * {@see QualityControlTask::hasOpenBlockingForBatch()}) or an open blocking
 * issue raised against it.
 *
 * Read by the finite scheduler, which does not place an order that is held,
 * and by the planner's insights, which warn about held orders already on the
 * plan. Both read the same facts from here, so they never disagree.
 */
final class QualityHolds
{
    public const KIND_CONTROL = 'quality_control';

    public const KIND_ISSUE = 'blocking_issue';

    /**
     * @param  list<int>  $orderIds
     * @return array<int, list<array{kind: string, id: int, name: string, reason: ?string, since: ?string, href: string}>> order id => its holds; orders without one are absent
     */
    public function forOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $holds = [];

        QualityControlTask::query()
            ->open()
            ->whereIn('work_order_id', $orderIds)
            ->whereHas('trigger', fn ($q) => $q->where('is_blocking', true))
            ->with('trigger:id,name')
            ->orderBy('fired_at')
            ->orderBy('id')
            ->get()
            ->each(function (QualityControlTask $task) use (&$holds) {
                $holds[(int) $task->work_order_id][] = [
                    'kind' => self::KIND_CONTROL,
                    'id' => (int) $task->id,
                    'name' => (string) ($task->trigger?->name ?? __('Quality control')),
                    'reason' => $task->due_reason,
                    'since' => $task->fired_at?->toIso8601String(),
                    'href' => '/admin/quality-tasks',
                ];
            });

        Issue::query()
            ->whereIn('work_order_id', $orderIds)
            ->where('blocks_work_order', true)
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'work_order_id', 'title', 'created_at'])
            ->each(function (Issue $issue) use (&$holds) {
                $holds[(int) $issue->work_order_id][] = [
                    'kind' => self::KIND_ISSUE,
                    'id' => (int) $issue->id,
                    'name' => (string) $issue->title,
                    'reason' => null,
                    'since' => $issue->created_at?->toIso8601String(),
                    'href' => '/admin/issues',
                ];
            });

        return $holds;
    }

    /** One line for a planner: what holds the order, by name. */
    public static function describe(array $holds): string
    {
        return implode(' · ', array_map(fn (array $h) => $h['kind'] === self::KIND_CONTROL
            ? __('Quality control due: :name', ['name' => $h['name']])
            : __('Blocking issue: :name', ['name' => $h['name']]), $holds));
    }
}
