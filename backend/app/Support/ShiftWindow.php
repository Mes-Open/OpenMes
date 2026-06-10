<?php

namespace App\Support;

use App\Models\Shift;
use Carbon\Carbon;

/**
 * Resolves the time window of the shift currently in progress, from the
 * configured Shift definitions (Shift::current). A single source of truth so
 * the packing station and the shift-handover balance compute identical windows,
 * including overnight shifts. Falls back to a fixed 06:00–18:00 / 18:00–06:00
 * split when no shift is configured.
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
        $shift = Shift::current($lineId);

        if ($shift && $shift->start_time) {
            $start = Carbon::today()->setTimeFromTimeString($shift->start_time);

            // Overnight shift in its early-morning portion → it began yesterday.
            if ($start->greaterThan(Carbon::now())) {
                $start->subDay();
            }

            $end = (clone $start)->setTimeFromTimeString($shift->end_time);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay(); // wraps past midnight
            }

            return new self($shift, $start, $end);
        }

        // Fallback: fixed 12h split.
        $now = Carbon::now();
        $hour = $now->hour;

        if ($hour >= 6 && $hour < 18) {
            $start = $now->copy()->setTime(6, 0, 0);
        } elseif ($hour >= 18) {
            $start = $now->copy()->setTime(18, 0, 0);
        } else {
            $start = $now->copy()->subDay()->setTime(18, 0, 0);
        }

        return new self(null, $start, (clone $start)->addHours(12));
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
