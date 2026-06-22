<?php

namespace App\Services\Quality;

use App\Models\Issue;
use App\Models\IssueAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations for the non-conformance module (#11): a Pareto distribution of
 * issues by type, a disposition breakdown, open-by-type counts and the overdue
 * corrective/preventive action count. Reused by the report page and the
 * dashboard widgets.
 */
class NonConformanceReportService
{
    /**
     * Pareto of non-conformances by issue type over a window: count + summed
     * non-conforming quantity per type, sorted desc by count, with the running
     * cumulative percentage (the classic 80/20 view).
     */
    public function pareto(Carbon $from, Carbon $to): array
    {
        $issues = Issue::with('issueType:id,name')
            ->whereBetween('reported_at', [$from, $to])
            ->get(['id', 'issue_type_id', 'non_conforming_qty', 'reported_at']);

        $types = $issues
            ->groupBy('issue_type_id')
            ->map(function (Collection $rows) {
                $type = $rows->first()->issueType;

                return [
                    'issue_type_id' => $type?->id,
                    'name' => $type?->name ?? __('Unknown'),
                    'count' => $rows->count(),
                    'nc_qty' => round((float) $rows->sum('non_conforming_qty'), 2),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $total = (int) $types->sum('count');
        $running = 0;

        $types = $types->map(function (array $row) use ($total, &$running) {
            $running += $row['count'];
            $row['pct'] = $total > 0 ? round(($row['count'] / $total) * 100, 2) : 0.0;
            $row['cumulative_pct'] = $total > 0 ? round(($running / $total) * 100, 2) : 0.0;

            return $row;
        })->values();

        return [
            'total_count' => $total,
            'total_nc_qty' => round((float) $issues->sum('non_conforming_qty'), 2),
            'types' => $types->all(),
        ];
    }

    /**
     * Count of issues per disposition (#11) — drives the disposition summary
     * widget. Returns every disposition key (including zero buckets).
     */
    public function dispositionSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Issue::query();
        if ($from && $to) {
            $query->whereBetween('reported_at', [$from, $to]);
        }

        $counts = $query->selectRaw('disposition, COUNT(*) as total')
            ->groupBy('disposition')
            ->pluck('total', 'disposition');

        $summary = [];
        foreach (\App\Enums\IssueDisposition::values() as $value) {
            $summary[$value] = (int) ($counts[$value] ?? 0);
        }

        return $summary;
    }

    /**
     * Open (OPEN/ACKNOWLEDGED) non-conformances grouped by issue type — drives
     * the "open non-conformances by type" widget.
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function openByType(): array
    {
        return Issue::with('issueType:id,name')
            ->open()
            ->get(['id', 'issue_type_id'])
            ->groupBy('issue_type_id')
            ->map(fn (Collection $rows) => [
                'name' => $rows->first()->issueType?->name ?? __('Unknown'),
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Count of outstanding corrective/preventive actions past their due date.
     */
    public function overdueActionsCount(): int
    {
        return IssueAction::overdue()->count();
    }
}
