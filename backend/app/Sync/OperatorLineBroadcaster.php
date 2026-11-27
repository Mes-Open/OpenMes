<?php

namespace App\Sync;

use App\Events\OperatorLineChanged;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\QualityControlTask;
use App\Models\ScrapEntry;
use App\Models\WorkOrder;
use App\Models\WorkOrderStop;
use App\Models\Workstation;
use App\Support\ProductionRules;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** Keep relational operator pages current, including changes with no order counter update. */
class OperatorLineBroadcaster
{
    private static array $queued = [];

    public static function boot(): void
    {
        foreach ([WorkOrder::class, Batch::class, BatchStep::class, Issue::class, ScrapEntry::class, QualityControlTask::class, WorkOrderStop::class] as $model) {
            foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
                if ($event === 'restored' && ! method_exists($model, 'restored')) {
                    continue;
                }
                $model::{$event}(function ($row) {
                    $order = match (true) {
                        $row instanceof WorkOrder => $row,
                        $row instanceof BatchStep => $row->batch?->workOrder,
                        $row instanceof QualityControlTask => $row->workOrder ?? $row->batch?->workOrder,
                        default => $row->workOrder,
                    };
                    self::nudge($order?->line_id);
                    if ($row instanceof WorkOrder && $row->wasChanged('line_id')) {
                        self::nudge($row->getOriginal('line_id'));
                    }
                    foreach (self::routedLines($row, $order) as $lineId) {
                        self::nudge($lineId);
                    }
                });
            }
        }
        Event::listen(TransactionRolledBack::class, fn () => self::$queued = []);
    }

    /**
     * With workstation routing on, an order is also worked from workstations of
     * other lines: their queues and pages listen on their own line, so it is
     * nudged too. A step nudges its workstation's line; the order and its
     * batches nudge every line its routing passes through.
     *
     * @return list<int>
     */
    private static function routedLines(object $row, ?WorkOrder $order): array
    {
        if (! $order) {
            return [];
        }

        $stationIds = $row instanceof BatchStep
            ? [$row->workstation_id]
            : ($row instanceof WorkOrder || $row instanceof Batch
                ? array_column($order->process_snapshot['steps'] ?? [], 'workstation_id')
                : []);
        $stationIds = array_values(array_unique(array_filter(array_map('intval', $stationIds))));
        // Read the setting only when a workstation is involved, and every time:
        // a memo would outlive a settings change in a long-running worker.
        if ($stationIds === [] || ! ProductionRules::workstationRoutingEnabled()) {
            return [];
        }

        return Workstation::whereIn('id', $stationIds)
            ->where('line_id', '!=', $order->line_id)
            ->distinct()
            ->pluck('line_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function nudge(?int $lineId): void
    {
        if (! $lineId || isset(self::$queued[$lineId])) {
            return;
        }
        self::$queued[$lineId] = true;
        DB::afterCommit(function () use ($lineId) {
            unset(self::$queued[$lineId]);
            try {
                event(new OperatorLineChanged($lineId));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
