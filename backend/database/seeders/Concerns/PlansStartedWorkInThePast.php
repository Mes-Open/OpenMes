<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shift starts for demo orders that are already running.
 *
 * An order the demo marks IN_PROGRESS or DONE has, by definition, started. Give
 * it a `planned_start_at` in the future and the row contradicts itself — and
 * WorkOrder::assertProductionAvailable() is right to refuse the next save of it,
 * which is what a reseed does.
 *
 * The seeders used to write `now()->setTime(6, 0)` and rely on somebody running
 * them after six in the morning. Before that hour the whole demo kit refused to
 * reseed, and the test suite went red every night it ran early — including on
 * main. Worse for the customer than for us: sample data loaded at half five gave
 * them orders they could not touch until the shift they were nominally already
 * running in.
 *
 * Rewinding a day rather than clamping to "an hour ago" keeps the tidy shift
 * hour the screens are built to show, and "started yesterday at six" is a
 * truthful thing for a running order to say.
 */
trait PlansStartedWorkInThePast
{
    protected function shiftStartAlreadyPast(int $hour, int $minute = 0): Carbon
    {
        $at = now()->setTime($hour, $minute);

        return $at->isFuture() ? $at->subDay() : $at;
    }
}
