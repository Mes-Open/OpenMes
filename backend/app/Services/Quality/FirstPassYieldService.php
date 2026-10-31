<?php

namespace App\Services\Quality;

use App\Models\QualityCheck;
use App\Support\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * First-pass yield: the share of batches that passed in-process quality
 * control the first time they were checked.
 *
 * The unit is the batch, and its *first-ever* quality check decides it:
 *  - denominator — batches whose first check falls inside [from, to);
 *  - numerator — those whose first check had every parameter in tolerance
 *    (`all_passed`).
 *
 * Why the first check, rather than every check in the window:
 *  - a batch is counted once, in the shift it was first inspected, so FPY of
 *    consecutive shifts never double-counts the same batch;
 *  - "failed, then passed on the re-check" is exactly what is not first-pass;
 *  - the routine periodic checks of a batch that already passed (a template
 *    asks for several per batch) do not inflate the denominator.
 * A later periodic failure of an already-passed batch is a scrap/quality
 * signal, not a first-pass one, and does not lower FPY.
 *
 * Inbound inspections (supplier material) and serial-unit test attempts are
 * separate flows and are not part of production FPY.
 */
class FirstPassYieldService
{
    /** Percentage rounded to one decimal, or null when there is nothing to measure. */
    public function forWindow(Carbon $from, Carbon $to, ?int $lineId = null): ?float
    {
        $breakdown = $this->breakdownForWindow($from, $to, $lineId);

        if ($breakdown === null || $breakdown['inspected'] === 0) {
            return null;
        }

        return round($breakdown['first_pass'] / $breakdown['inspected'] * 100, 1);
    }

    /**
     * The counts behind {@see forWindow()}; null when the quality module is off.
     *
     * @return array{first_pass: int, inspected: int}|null
     */
    public function breakdownForWindow(Carbon $from, Carbon $to, ?int $lineId = null): ?array
    {
        if (! ModuleRegistry::isModuleEnabled('quality')) {
            return null;
        }

        // Aggregated in SQL, so the work grows with the number of batches, not
        // with how many times each was checked.
        //
        // 1. Each batch's first-ever check time, kept when it falls inside the
        //    window. `whereHas` drops checks of deleted batches/orders.
        $firstTimes = QualityCheck::query()
            ->select('batch_id')
            ->selectRaw('MIN(checked_at) AS first_at')
            ->where('checked_at', '<', $to)
            ->whereHas('batch.workOrder', fn ($q) => $q->when($lineId !== null, fn ($q2) => $q2->where('line_id', $lineId)))
            ->groupBy('batch_id')
            ->havingRaw('MIN(checked_at) >= ?', [$from]);

        // 2. The check at that time — the lowest id when two share it.
        $firstCheckIds = DB::query()
            ->fromSub($firstTimes, 'first_times')
            ->join('quality_checks as first_check', fn ($join) => $join
                ->on('first_check.batch_id', '=', 'first_times.batch_id')
                ->on('first_check.checked_at', '=', 'first_times.first_at'))
            ->groupBy('first_times.batch_id')
            ->selectRaw('MIN(first_check.id)');

        // 3. How many of those first checks passed.
        $counts = DB::table('quality_checks')
            ->whereIn('id', $firstCheckIds)
            ->selectRaw('COUNT(*) AS inspected, SUM(CASE WHEN all_passed THEN 1 ELSE 0 END) AS first_pass')
            ->first();

        return [
            'first_pass' => (int) ($counts->first_pass ?? 0),
            'inspected' => (int) ($counts->inspected ?? 0),
        ];
    }
}
