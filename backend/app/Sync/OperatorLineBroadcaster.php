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
                });
            }
        }
        Event::listen(TransactionRolledBack::class, fn () => self::$queued = []);
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
