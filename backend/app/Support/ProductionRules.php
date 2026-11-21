<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Plant-wide production rules an admin switches in Settings → System → Production.
 *
 * The stored setting is the source of truth. The `openmmes.*` config value
 * (env) is only the fallback for a database without the row — the switches on
 * the settings page used to be saved but never read, because these rules were
 * taken from env alone.
 */
final class ProductionRules
{
    public const ALLOW_OVERPRODUCTION = 'allow_overproduction';

    public const FORCE_SEQUENTIAL_STEPS = 'force_sequential_steps';

    /** May the batches of a work order add up to more than its planned quantity? */
    public static function allowOverproduction(): bool
    {
        return self::flag(self::ALLOW_OVERPRODUCTION, (bool) config('openmmes.allow_overproduction', false));
    }

    /** Must a step wait for the previous one before it can start? */
    public static function forceSequentialSteps(): bool
    {
        return self::flag(self::FORCE_SEQUENTIAL_STEPS, (bool) config('openmmes.force_sequential_steps', true));
    }

    /**
     * Would a work order total of `$total` break the overproduction rule?
     * Always false while overproduction is allowed.
     */
    public static function exceedsPlan(WorkOrder $workOrder, float $total): bool
    {
        return ! self::allowOverproduction() && $total - (float) $workOrder->planned_qty > 0.001;
    }

    /**
     * How much of the work order its batches already take up: what a finished
     * batch produced, the target of a batch still open. A cancelled batch takes
     * nothing, and a batch that finished short leaves room for a top-up batch.
     */
    public static function batchedQty(WorkOrder $workOrder, ?Batch $except = null): float
    {
        return (float) $workOrder->batches()
            ->where('status', '!=', Batch::STATUS_CANCELLED)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->get(['status', 'target_qty', 'produced_qty'])
            ->sum(fn (Batch $b) => $b->status === Batch::STATUS_DONE ? (float) $b->produced_qty : (float) $b->target_qty);
    }

    /** What new batches may still add up to, or null when overproduction is allowed. */
    public static function batchableQty(WorkOrder $workOrder): ?float
    {
        if (self::allowOverproduction()) {
            return null;
        }

        return max(0.0, round((float) $workOrder->planned_qty - self::batchedQty($workOrder), 2));
    }

    /** The message shown when an entry would go over the planned quantity. */
    public static function exceedsPlanMessage(float $remaining): string
    {
        return __('Overproduction is turned off: only :qty more can be recorded for this work order.', [
            'qty' => rtrim(rtrim(number_format(max(0.0, $remaining), 2, '.', ''), '0'), '.'),
        ]);
    }

    private static function flag(string $key, bool $default): bool
    {
        try {
            $raw = DB::table('system_settings')->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $default; // no database yet (install)
        }

        if ($raw === null) {
            return $default;
        }

        return (bool) (json_decode($raw, true) ?? $default);
    }
}
