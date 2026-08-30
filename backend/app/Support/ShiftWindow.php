<?php

namespace App\Support;

use App\Models\Shift;
use Carbon\Carbon;

/**
 * Resolves the time window of a shift occurrence from the configured Shift
 * definitions. A single source of truth so the packing station, the
 * shift-handover balance and the shift monitor compute identical windows,
 * including overnight shifts — `at()` is the one rule, and `current()` is that
 * rule asked about now. Falls back to a fixed 06:00–18:00 / 18:00–06:00 split
 * when no shift is configured or none is scheduled.
 */
class ShiftWindow
{
    public ?Shift $shift;

    public Carbon $start;

    public Carbon $end;

    public string $businessDate;

    private function __construct(?Shift $shift, Carbon $start, Carbon $end)
    {
        $this->shift = $shift;
        $this->start = $start;
        $this->end = $end;
        $this->businessDate = $start->toDateString();
    }

    public static function current(?int $lineId = null): self
    {
        // One resolver, one rule. This used to reach for Shift::current(), which
        // asks `days_of_week` about today rather than about the day the shift
        // started — so at 01:30 on a Saturday it disagreed with at() about
        // whether the Friday night shift was running, and which answer you got
        // depended on which entry point you called.
        return self::at($lineId, Carbon::now()) ?? self::fallbackAround(Carbon::now());
    }

    /**
     * The fixed 12h split containing an instant — the shape used when no shift
     * is configured, or none is scheduled.
     *
     * Takes the instant rather than reading the clock, because a caller asking
     * about a past day needs a window on *that* day. `current()` used to own
     * this arithmetic and answer only for now(), so every caller that fell
     * through to it while resolving some other date was silently handed today.
     */
    public static function fallbackAround(Carbon $at): self
    {
        $hour = $at->hour;

        if ($hour >= 6 && $hour < 18) {
            $start = $at->copy()->setTime(6, 0, 0);
        } elseif ($hour >= 18) {
            $start = $at->copy()->setTime(18, 0, 0);
        } else {
            $start = $at->copy()->subDay()->setTime(18, 0, 0);
        }

        return new self(null, $start, (clone $start)->addHours(12));
    }

    /**
     * The shift a line runs whose occurrence *starts* on the given date, or
     * null if the line has none scheduled that day.
     *
     * This is what "show me the 26th" means on a screen that pages by day: a
     * night shift opening at 22:00 on the 26th belongs to the 26th, even though
     * most of its minutes fall on the 27th. Resolving that by handing an
     * instant to `at()` cannot work — the instant would have to be inside the
     * shift, which is the answer being asked for.
     */
    public static function startingOnDate(?int $lineId, Carbon $date): ?self
    {
        return Shift::where('is_active', true)
            ->when($lineId, fn ($q) => $q->where(fn ($q2) => $q2->where('line_id', $lineId)->orWhereNull('line_id')))
            ->get()
            ->map(fn (Shift $shift) => self::startingOn($shift, $date))
            ->filter(fn (self $window) => $window->isScheduled() && $window->start->isSameDay($date))
            ->sortBy(fn (self $window) => $window->start->getTimestamp())
            ->first();
    }

    /**
     * The occurrence of one specific shift pattern that contains — or most
     * recently preceded — $at. `current()` answers "which shift is running
     * now?"; this answers "when exactly did *this* shift run?", which is what
     * a screen that can page back through shifts needs.
     */
    public static function occurrence(Shift $shift, Carbon $at): self
    {
        $start = $at->copy()->setTimeFromTimeString($shift->start_time);
        $end = (clone $start)->setTimeFromTimeString($shift->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // wraps past midnight
        }

        // $at falls before this occurrence opened, so it belongs to the previous one.
        if ($at->lessThan($start)) {
            $start->subDay();
            $end->subDay();
        }

        return new self($shift, $start, $end);
    }

    /**
     * The occurrence of a shift that *started* on the given date.
     *
     * `occurrence()` takes an instant and rolls back a day when that instant
     * falls before the shift opened — right for "which occurrence contains this
     * moment", wrong for "the night of the 26th", where a caller passing
     * midnight would be handed the 25th. Callers were pre-aligning the argument
     * to the shift's own start time to defeat that branch, which meant knowing
     * how it works to call it correctly. This says it directly.
     */
    public static function startingOn(Shift $shift, Carbon $date): self
    {
        return self::occurrence($shift, $date->copy()->setTimeFromTimeString($shift->start_time));
    }

    /**
     * The shift a line is running at an arbitrary moment, or null if none is.
     *
     * `current()` answers this for `now()` only. Callers that need it for some
     * other instant — replaying a timeline, opening a past shift — went on to
     * hand-roll it, and every hand-rolled copy dropped the two rules that make
     * the answer correct: a shift only runs on its `days_of_week`, and a shift
     * with no `line_id` is plant-wide and runs on every line.
     */
    public static function at(?int $lineId, Carbon $at): ?self
    {
        return Shift::where('is_active', true)
            ->when($lineId, fn ($q) => $q->where(fn ($q2) => $q2->where('line_id', $lineId)->orWhereNull('line_id')))
            ->get()
            ->map(fn (Shift $shift) => self::occurrence($shift, $at))
            ->first(fn (self $window) => $window->contains($at) && $window->isScheduled());
    }

    /**
     * Whether this occurrence was scheduled to run at all.
     *
     * `days_of_week` is asked about the day the shift *started*, not the day it
     * is being asked about — a Friday 22:00–06:00 night runs into Saturday
     * without becoming a Saturday shift, and a Sunday night is not scheduled
     * merely because Monday is.
     */
    public function isScheduled(): bool
    {
        $days = $this->shift?->days_of_week;

        return ! is_array($days) || in_array((int) $this->start->format('N'), $days, true);
    }

    public function contains(Carbon $at): bool
    {
        return $at->greaterThanOrEqualTo($this->start) && $at->lessThan($this->end);
    }

    public function durationMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * @return array{name: string, code: ?string, start: string, end: string}|null
     */
    public function shiftPayload(): ?array
    {
        if (! $this->shift) {
            return null;
        }

        return [
            'name' => $this->shift->name,
            'code' => $this->shift->code,
            'start' => substr((string) $this->shift->start_time, 0, 5),
            'end' => substr((string) $this->shift->end_time, 0, 5),
        ];
    }
}
