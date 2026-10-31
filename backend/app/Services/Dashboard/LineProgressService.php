<?php

namespace App\Services\Dashboard;

use App\Models\Batch;
use App\Models\MachineEvent;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The "Lines" cards of the plant overview: per active line, the work order it
 * is working on now, how far along it is and when it will be done.
 *
 * Current order — among the line's IN_PROGRESS orders (or, when there are
 * none, its BLOCKED / PAUSED ones, so a stuck line still shows what it is stuck
 * on) the one started earliest. `work_orders` has no `started_at`, so "started"
 * is the order's earliest `batches.started_at`; orders without a started batch
 * sort after, by `planned_start_at` then id. `more_count` is how many other
 * such orders the line holds.
 *
 * Rate (pieces per hour) — good pieces over the last 60 minutes:
 *  - a line with a machine collector: counter events of kind `good`, summed per
 *    station, then the MAX over the line's stations. On a serial line every
 *    piece passes every station, so that is the line's output without double
 *    counting; on parallel stations it undercounts;
 *  - a line without one: `produced_qty` of the current order's batches that
 *    were completed in the window.
 *
 * ETA — remaining quantity ÷ rate; with no rate, the order's `planned_end_at`.
 * "Late" when the ETA is after `planned_end_at` (with no rate: when
 * `planned_end_at` has already passed). See computeEta().
 *
 * Tenant boundary: Workstation, Batch and MachineEvent carry no tenant scope;
 * they are reached only through the IDs of tenant-scoped lines and orders.
 * Query count is fixed regardless of line count.
 */
class LineProgressService
{
    private const RATE_WINDOW_MINUTES = 60;

    /** Order statuses a line can be "on", best candidates first. */
    private const CURRENT_TIERS = [
        [WorkOrder::STATUS_IN_PROGRESS],
        [WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_PAUSED],
    ];

    /**
     * @param  array<int, array{id: int, has_collector: bool}>  $lines  PlantOverviewService::lineStates() rows
     * @return array<int, array{line_id: int, order: ?array<string, mixed>, more_count: int, rate_per_hour: ?float, eta: ?string, eta_source: ?string, late: bool}>
     */
    public function forLines(array $lines, Carbon $now): array
    {
        if ($lines === []) {
            return [];
        }

        $lineIds = array_column($lines, 'id');
        $windowStart = $now->copy()->subMinutes(self::RATE_WINDOW_MINUTES);

        $ordersByLine = WorkOrder::with('productType:id,code')
            ->whereIn('line_id', $lineIds)
            ->whereIn('status', array_merge(...self::CURRENT_TIERS))
            ->withMin('batches', 'started_at')
            ->get(['id', 'line_id', 'order_no', 'status', 'product_type_id', 'planned_qty', 'produced_qty', 'planned_start_at', 'planned_end_at'])
            ->groupBy('line_id');

        $current = [];
        $more = [];
        foreach ($lineIds as $lineId) {
            [$current[$lineId], $more[$lineId]] = $this->pickCurrent($ordersByLine->get($lineId) ?? collect());
        }

        $collectorLines = collect($lines)->where('has_collector', true)->pluck('id');
        $machineRate = $this->machineRateByLine($collectorLines, $windowStart, $now);

        $manualOrderIds = collect($lines)
            ->where('has_collector', false)
            ->map(fn (array $l) => $current[$l['id']]?->id)
            ->filter();
        $batchRate = $this->batchRateByOrder($manualOrderIds, $windowStart, $now);

        return collect($lines)->map(function (array $line) use ($current, $more, $machineRate, $batchRate, $now) {
            $order = $current[$line['id']];

            if ($order === null) {
                return [
                    'line_id' => $line['id'],
                    'order' => null,
                    'more_count' => 0,
                    'rate_per_hour' => null,
                    'eta' => null,
                    'eta_source' => null,
                    'late' => false,
                ];
            }

            $rate = $line['has_collector']
                ? ($machineRate[$line['id']] ?? null)
                : ($batchRate[$order->id] ?? null);
            $rate = $rate !== null && $rate > 0 ? round($rate, 2) : null;

            $planned = (float) $order->planned_qty;
            $produced = (float) $order->produced_qty;
            $eta = self::computeEta($planned, $produced, $rate, $order->planned_end_at, $now);

            return [
                'line_id' => $line['id'],
                'order' => [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'status' => $order->status,
                    'product_code' => $order->productType?->code,
                    'produced' => $produced,
                    'planned' => $planned,
                    'percent' => self::percent($planned, $produced),
                    'planned_end_at' => $order->planned_end_at?->toIso8601String(),
                ],
                'more_count' => $more[$line['id']],
                'rate_per_hour' => $rate,
                'eta' => $eta['eta']?->toIso8601String(),
                'eta_source' => $eta['source'],
                'late' => $eta['late'],
            ];
        })->values()->all();
    }

    /**
     * Whole percent done, floored so an order never reads 100 % before it is;
     * null when nothing is planned (no fake 0 % or NaN).
     */
    public static function percent(float $planned, float $produced): ?int
    {
        if ($planned <= 0) {
            return null;
        }

        return (int) min(100, max(0, floor($produced / $planned * 100)));
    }

    /**
     * When the order will be done and whether that is late.
     *
     *  - with a rate:    eta = now + remaining ÷ rate; late when eta > planned end;
     *  - without a rate: eta = planned end; late when the planned end has passed;
     *  - neither:        no eta, never late.
     *
     * @return array{eta: ?Carbon, source: ?string, late: bool}
     */
    public static function computeEta(float $planned, float $produced, ?float $ratePerHour, ?Carbon $plannedEnd, Carbon $now): array
    {
        if ($ratePerHour !== null && $ratePerHour > 0) {
            $remaining = max(0.0, $planned - $produced);
            $eta = $now->copy()->addSeconds((int) ceil($remaining / $ratePerHour * 3600));

            return ['eta' => $eta, 'source' => 'rate', 'late' => $plannedEnd !== null && $eta->gt($plannedEnd)];
        }

        if ($plannedEnd !== null) {
            return ['eta' => $plannedEnd->copy(), 'source' => 'plan', 'late' => $plannedEnd->lt($now)];
        }

        return ['eta' => null, 'source' => null, 'late' => false];
    }

    /**
     * The order the line is on now and how many other candidates it has.
     *
     * @param  Collection<int, WorkOrder>  $orders
     * @return array{0: ?WorkOrder, 1: int}
     */
    private function pickCurrent(Collection $orders): array
    {
        foreach (self::CURRENT_TIERS as $statuses) {
            $tier = $orders->whereIn('status', $statuses);
            if ($tier->isEmpty()) {
                continue;
            }

            $sorted = $tier->sort(function (WorkOrder $a, WorkOrder $b) {
                return [self::ts($a->batches_min_started_at), self::ts($a->planned_start_at), $a->id]
                    <=> [self::ts($b->batches_min_started_at), self::ts($b->planned_start_at), $b->id];
            });

            return [$sorted->first(), $orders->count() - 1];
        }

        return [null, 0];
    }

    /** Sort key for an optional timestamp — missing ones go last. */
    private static function ts(mixed $value): int
    {
        return $value === null ? PHP_INT_MAX : Carbon::parse($value)->getTimestamp();
    }

    /**
     * Good pieces per hour per collector line: counter deltas summed per
     * station over the window, max over the line's stations.
     *
     * @param  Collection<int, int>  $lineIds
     * @return array<int, float> line id → pieces per hour
     */
    private function machineRateByLine(Collection $lineIds, Carbon $from, Carbon $to): array
    {
        if ($lineIds->isEmpty()) {
            return [];
        }

        $stationLine = Workstation::whereIn('line_id', $lineIds)
            ->where('is_active', true)
            ->pluck('line_id', 'id');

        if ($stationLine->isEmpty()) {
            return [];
        }

        // Same payload decoding as ShiftMonitorService::counterMinutesByStation;
        // streamed so an hour of a chatty counter never sits in memory at once.
        $rows = MachineEvent::whereIn('workstation_id', $stationLine->keys())
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $from)
            ->where('event_timestamp', '<', $to)
            ->select(['id', 'workstation_id', 'payload'])
            ->toBase()
            ->lazyById(1000);

        $byStation = [];
        foreach ($rows as $event) {
            $payload = json_decode((string) $event->payload, true) ?: [];
            if (($payload['kind'] ?? 'good') === 'reject') {
                continue;
            }
            $byStation[$event->workstation_id] = ($byStation[$event->workstation_id] ?? 0) + (float) ($payload['delta'] ?? 0);
        }

        $perHour = 60 / self::RATE_WINDOW_MINUTES;
        $byLine = [];
        foreach ($byStation as $stationId => $good) {
            $lineId = $stationLine[$stationId];
            $byLine[$lineId] = max($byLine[$lineId] ?? 0, $good * $perHour);
        }

        return $byLine;
    }

    /**
     * Pieces per hour per order, from its batches completed in the window.
     *
     * @param  Collection<int, int>  $orderIds
     * @return array<int, float> order id → pieces per hour
     */
    private function batchRateByOrder(Collection $orderIds, Carbon $from, Carbon $to): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        $perHour = 60 / self::RATE_WINDOW_MINUTES;

        return Batch::whereIn('work_order_id', $orderIds->values())
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $to)
            ->groupBy('work_order_id')
            ->selectRaw('work_order_id, SUM(produced_qty) as qty')
            ->pluck('qty', 'work_order_id')
            ->map(fn ($qty) => (float) $qty * $perHour)
            ->all();
    }
}
