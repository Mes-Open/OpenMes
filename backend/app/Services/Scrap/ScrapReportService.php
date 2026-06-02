<?php

namespace App\Services\Scrap;

use App\Models\Batch;
use App\Models\Line;
use App\Models\ScrapEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates scrap data for the admin reporting views and the public report
 * API so both surfaces share identical Pareto / scrap-rate maths.
 *
 * Grouping is done in PHP (over a date-bounded set of entries) so the same
 * code runs on PostgreSQL in production and SQLite in the test suite.
 */
class ScrapReportService
{
    /**
     * Scrap entries in the period (optionally constrained to one line), with
     * their reason eager-loaded.
     */
    private function entries(Carbon $from, Carbon $to, ?int $lineId): Collection
    {
        return ScrapEntry::query()
            ->with(['scrapReason', 'workOrder:id,line_id'])
            ->whereBetween('reported_at', [$from, $to])
            ->when($lineId, fn ($q) => $q->whereHas('workOrder', fn ($w) => $w->where('line_id', $lineId)))
            ->get();
    }

    /**
     * Pareto of scrap quantity by reason, sorted descending, with each reason's
     * share and the running cumulative share.
     */
    public function pareto(Carbon $from, Carbon $to, ?int $lineId = null): array
    {
        $entries = $this->entries($from, $to, $lineId);

        $reasons = $entries
            ->groupBy('scrap_reason_id')
            ->map(function (Collection $rows) {
                $reason = $rows->first()->scrapReason;

                return [
                    'scrap_reason_id' => $reason?->id,
                    'code' => $reason?->code,
                    'name' => $reason?->name ?? __('Unknown'),
                    'category' => $reason?->category,
                    'qty' => round((float) $rows->sum('quantity'), 2),
                    'entries' => $rows->count(),
                ];
            })
            ->sortByDesc('qty')
            ->values();

        $total = round((float) $reasons->sum('qty'), 2);
        $running = 0.0;

        $reasons = $reasons->map(function (array $row) use ($total, &$running) {
            $running += $row['qty'];
            $row['pct'] = $total > 0 ? round(($row['qty'] / $total) * 100, 2) : 0.0;
            $row['cumulative_pct'] = $total > 0 ? round(($running / $total) * 100, 2) : 0.0;

            return $row;
        })->values();

        return [
            'total_qty' => $total,
            'total_entries' => $entries->count(),
            'reasons' => $reasons->all(),
        ];
    }

    /**
     * Scrap quantity grouped by 5M category (material/machine/method/man/environment).
     */
    public function byCategory(Carbon $from, Carbon $to, ?int $lineId = null): array
    {
        return $this->entries($from, $to, $lineId)
            ->groupBy(fn (ScrapEntry $e) => $e->scrapReason?->category ?? 'unknown')
            ->map(fn (Collection $rows, $category) => [
                'category' => $category,
                'qty' => round((float) $rows->sum('quantity'), 2),
                'entries' => $rows->count(),
            ])
            ->sortByDesc('qty')
            ->values()
            ->all();
    }

    /**
     * Scrap rate per line over the period: total scrap quantity divided by the
     * quantity produced (completed batches) on that line.
     */
    public function ratePerLine(Carbon $from, Carbon $to): array
    {
        $scrapByLine = ScrapEntry::query()
            ->join('work_orders', 'scrap_entries.work_order_id', '=', 'work_orders.id')
            ->whereBetween('scrap_entries.reported_at', [$from, $to])
            ->whereNotNull('work_orders.line_id')
            ->groupBy('work_orders.line_id')
            ->selectRaw('work_orders.line_id as line_id, COALESCE(SUM(scrap_entries.quantity), 0) as scrap_qty')
            ->pluck('scrap_qty', 'line_id');

        $producedByLine = Batch::query()
            ->join('work_orders', 'batches.work_order_id', '=', 'work_orders.id')
            ->where('batches.status', Batch::STATUS_DONE)
            ->whereBetween('batches.completed_at', [$from, $to])
            ->whereNotNull('work_orders.line_id')
            ->groupBy('work_orders.line_id')
            ->selectRaw('work_orders.line_id as line_id, COALESCE(SUM(batches.produced_qty), 0) as produced_qty')
            ->pluck('produced_qty', 'line_id');

        $lineIds = $scrapByLine->keys()->merge($producedByLine->keys())->unique()->values();

        if ($lineIds->isEmpty()) {
            return [];
        }

        $lineNames = Line::whereIn('id', $lineIds)->pluck('name', 'id');

        return $lineIds
            ->map(function ($lineId) use ($scrapByLine, $producedByLine, $lineNames) {
                $scrap = round((float) $scrapByLine->get($lineId, 0), 2);
                $produced = round((float) $producedByLine->get($lineId, 0), 2);

                return [
                    'line_id' => (int) $lineId,
                    'line_name' => $lineNames->get($lineId, '#' . $lineId),
                    'scrap_qty' => $scrap,
                    'produced_qty' => $produced,
                    'scrap_rate_pct' => $produced > 0 ? round(($scrap / $produced) * 100, 2) : null,
                ];
            })
            ->sortByDesc('scrap_qty')
            ->values()
            ->all();
    }

    /**
     * Daily scrap-quantity trend across the period.
     */
    public function trend(Carbon $from, Carbon $to, ?int $lineId = null): array
    {
        return $this->entries($from, $to, $lineId)
            ->groupBy(fn (ScrapEntry $e) => $e->reported_at->toDateString())
            ->map(fn (Collection $rows, $date) => [
                'date' => $date,
                'qty' => round((float) $rows->sum('quantity'), 2),
                'entries' => $rows->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }
}
