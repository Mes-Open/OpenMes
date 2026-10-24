<?php

namespace App\Services\Quality;

use App\Models\QualityCheck;
use App\Support\ModuleRegistry;
use Carbon\Carbon;

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

        // Batches with any check in the window — only these can have their first
        // check there. `whereHas` drops checks of deleted batches/orders.
        $batchIds = QualityCheck::query()
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->whereHas('batch.workOrder', fn ($q) => $q->when($lineId !== null, fn ($q2) => $q2->where('line_id', $lineId)))
            ->distinct()
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return ['first_pass' => 0, 'inspected' => 0];
        }

        // Every earlier-or-in-window check of those batches, oldest first (ties
        // by id), so the first row per batch is its first-ever check.
        $firstChecks = QualityCheck::query()
            ->whereIn('batch_id', $batchIds)
            ->where('checked_at', '<', $to)
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get(['id', 'batch_id', 'checked_at', 'all_passed'])
            ->unique('batch_id')
            ->filter(fn (QualityCheck $check) => $check->checked_at->greaterThanOrEqualTo($from));

        return [
            'first_pass' => $firstChecks->where('all_passed', true)->count(),
            'inspected' => $firstChecks->count(),
        ];
    }
}
