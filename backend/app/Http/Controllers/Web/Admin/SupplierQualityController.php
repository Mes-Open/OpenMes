<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\MaterialLotDisposal;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;

/**
 * Monthly incoming-quality scorecard per supplier.
 *
 * Supplier master data is not modelled yet; the supplier is the name captured
 * on the receipt / lot. Lots without a name are grouped as "未指定供应商" so a
 * missing field is visible instead of silently dropped.
 */
class SupplierQualityController extends Controller
{
    public function index(Request $request)
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        $all = $month === 'all';

        if (! $all && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = $all ? null : Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $all ? null : (clone $start)->endOfMonth();

        $lots = MaterialLot::query()
            ->with([
                'material:id,name,code,unit_of_measure',
                'inspection.results',
                'disposals',
                'warehouse:id,name',
            ])
            ->whereNotNull('inspection_id')
            ->when(! $all, fn ($query) => $query->whereBetween('received_at', [$start, $end]))
            ->orderByDesc('received_at')
            ->get();

        $rows = $lots
            ->groupBy(fn (MaterialLot $lot) => $lot->supplier_name ?: '未指定供应商')
            ->map(function ($group, $supplier) {
                $inspections = $group->pluck('inspection')->filter();
                $pass = $inspections->whereIn('status', ['pass', 'conditional_pass'])->count();
                $fail = $inspections->where('status', 'fail')->count();
                $inspected = $pass + $fail;
                $disposals = $group->flatMap->disposals;

                $topDefects = $inspections
                    ->flatMap->results
                    ->where('is_passed', false)
                    ->pluck('criterion_name')
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->take(3)
                    ->map(fn ($count, $name) => "{$name}（{$count}）")
                    ->values()
                    ->all();

                return [
                    'supplier' => $supplier,
                    'lots' => $group->count(),
                    'inspected' => $inspected,
                    'pass' => $pass,
                    'fail' => $fail,
                    'pass_rate' => $inspected > 0 ? round($pass / $inspected * 100, 1) : null,
                    'returned_qty' => (float) $disposals->where('disposition', MaterialLotDisposal::DISPOSITION_RETURN)->sum('quantity'),
                    'scrapped_qty' => (float) $disposals->where('disposition', MaterialLotDisposal::DISPOSITION_SCRAP)->sum('quantity'),
                    'rejected_qty' => (float) $disposals->where('disposition', MaterialLotDisposal::DISPOSITION_REJECT)->sum('quantity'),
                    'held_lots' => $group->where('status', MaterialLot::STATUS_QUARANTINE)->count(),
                    'held_qty' => round($group->where('status', MaterialLot::STATUS_QUARANTINE)->sum(fn (MaterialLot $lot) => $lot->remainingPhysicalQuantity()), 3),
                    'top_defects' => $topDefects,
                    'last_received_at' => $group->max('received_at')?->toIso8601String(),
                ];
            })
            ->sortByDesc(fn ($row) => [$row['fail'], $row['lots']])
            ->values();

        $summary = [
            'suppliers' => $rows->count(),
            'lots' => $rows->sum('lots'),
            'inspected' => $rows->sum('inspected'),
            'pass_rate' => $rows->sum('inspected') > 0
                ? round($rows->sum('pass') / $rows->sum('inspected') * 100, 1)
                : null,
            'returned_qty' => round($rows->sum('returned_qty'), 3),
            'held_lots' => $rows->sum('held_lots'),
        ];

        $monthOptions = collect(range(0, 11))->map(function ($offset) {
            $date = now()->subMonths($offset);

            return ['value' => $date->format('Y-m'), 'label' => $date->format('Y 年 m 月')];
        })->prepend(['value' => 'all', 'label' => '全部月份'])->values();

        return Inertia::render('admin/supplier-quality/Index', [
            'rows' => $rows,
            'summary' => $summary,
            'month' => $month,
            'monthOptions' => $monthOptions,
        ]);
    }
}
