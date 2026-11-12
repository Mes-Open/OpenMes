<?php

namespace App\Services\Schedule\Finite;

use App\Extension\Contracts\ExplainsWorkforceCapacity;
use App\Extension\Contracts\WorkforceCapacityProvider;
use App\Models\Line;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Whether a draft has the people it needs, shift by shift: for every active
 * line and every shift run in the horizon, who is assigned, who is away, what
 * the plan asks of that run and how many people it is short — plus, per
 * absent person, the work orders their absence delayed or blocked.
 *
 * Read once, after a run has placed everything; nothing is written. Rows with
 * no demand, no absence and no shortfall are left out, so a long horizon stays
 * small in the draft's summary.
 *
 * The shortfall of a run is the largest of: the booked peak minus the people
 * present (real when the run ignored people, or for committed or pinned work),
 * and — for every people reason whose window starts in the run on that line —
 * what the step needed on top of what was booked, minus who was there. The
 * second is what a step pushed to a later day leaves unmet on this one.
 *
 * Per line, not pooled: someone on two lines is counted on both.
 */
final class StaffingOverview
{
    /** Reason codes that carry people counts and absent workers. */
    private const PEOPLE_CODES = ['delayed_by_staffing', 'workforce_capacity', 'qualification'];

    /**
     * @param  Collection<int, Shift>  $shifts  the shifts the run's calendar was built from
     * @param  Collection<int, Line>  $lines  active lines, keyed by id
     * @param  list<array{line_id: int, tracked: bool, assigned: ?int}>  $workforce  {@see FiniteScheduler} per-line staffing
     */
    public function __construct(
        private readonly Collection $shifts,
        private readonly Collection $lines,
        private readonly Timeline $timeline,
        private readonly WorkforceCapacityProvider $provider,
        private readonly array $workforce,
        private readonly int $from,
        private readonly int $to,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $operations  the run's result rows
     * @return array{shifts: list<array<string, mixed>>, absences: list<array<string, mixed>>}
     */
    public function build(array $operations): array
    {
        $explains = $this->provider instanceof ExplainsWorkforceCapacity ? $this->provider : null;
        $byLine = collect($this->workforce)->keyBy('line_id');
        $people = $this->peopleReasons($operations);

        $rows = [];
        $absentIds = [];
        $lines = $this->lines->sortBy(fn (Line $line) => [mb_strtolower((string) $line->name), $line->id]);
        foreach ($lines as $line) {
            $lineId = (int) $line->id;
            $tracked = (bool) ($byLine->get($lineId)['tracked'] ?? false);
            $assigned = $byLine->get($lineId)['assigned'] ?? null;

            foreach (ShiftOccurrences::occurrences($this->shifts, $lineId, $this->from, $this->to) as $run) {
                $start = max($run['start'], $this->from);
                $end = min($run['end'], $this->to);
                if ($end <= $start) {
                    continue;
                }
                $from = ResourceCalendar::toCarbon($start);
                $to = ResourceCalendar::toCarbon($end);

                $present = $tracked ? $this->provider->availableOperators($lineId, $from, $to) : null;
                $absent = $tracked ? ($explains?->absentWorkerIds($lineId, $from, $to) ?? []) : [];
                $peak = $this->timeline->laborInUse($lineId, $start, $end);

                $shortfall = null;
                $blocked = 0;
                if ($present !== null) {
                    $shortfall = max(0, $peak - $present);
                    foreach ($people[$lineId] ?? [] as $reason) {
                        if ($reason['window_start'] >= $start && $reason['window_start'] < $end) {
                            $shortfall = max($shortfall, $reason['required'] + $reason['in_use'] - $reason['available']);
                            $blocked += $reason['blocked'] ? 1 : 0;
                        }
                    }
                }

                $personHours = round($this->timeline->laborMinutes($lineId, $start, $end) / 60, 1);
                if ($personHours <= 0 && $absent === [] && ! $shortfall && $blocked === 0) {
                    continue;
                }
                foreach ($absent as $id) {
                    $absentIds[$id] = true;
                }

                $rows[] = [
                    'line_id' => $lineId,
                    'line_name' => (string) $line->name,
                    'date' => $run['date'],
                    'shift_id' => $run['shift_id'],
                    'shift_name' => $run['shift_name'],
                    'start' => $from->toIso8601String(),
                    'end' => $to->toIso8601String(),
                    'tracked' => $tracked,
                    'assigned' => $tracked ? $assigned : null,
                    'present' => $present,
                    'absent' => array_values(array_map('intval', $absent)),
                    'person_hours' => $personHours,
                    'peak_operators' => $peak,
                    'blocked_operations' => $blocked,
                    'shortfall' => $shortfall,
                ];
            }
        }

        // One name lookup for every row.
        $names = $absentIds === [] ? [] : ($explains?->workerNames(array_keys($absentIds)) ?? []);
        foreach ($rows as &$row) {
            $row['absent'] = array_map(fn (int $id) => ['id' => $id, 'name' => $names[$id] ?? null], $row['absent']);
        }
        unset($row);

        return ['shifts' => $rows, 'absences' => $this->absences($rows, $operations)];
    }

    /**
     * People reasons per line, with their window start in epoch minutes.
     *
     * @param  list<array<string, mixed>>  $operations
     * @return array<int, list<array{window_start: int, required: int, available: int, in_use: int, blocked: bool}>>
     */
    private function peopleReasons(array $operations): array
    {
        $out = [];
        foreach ($operations as $op) {
            foreach ($op['reasons'] ?? [] as $reason) {
                if (! in_array($reason['code'] ?? null, self::PEOPLE_CODES, true) || ! isset($reason['window_start'], $reason['line_id'])) {
                    continue;
                }
                $out[(int) $reason['line_id']][] = [
                    'window_start' => ResourceCalendar::toMinutes(Carbon::parse($reason['window_start'])),
                    'required' => (int) $reason['required'],
                    'available' => (int) $reason['available'],
                    'in_use' => (int) $reason['in_use'],
                    'blocked' => ! $op['status']->isScheduled(),
                ];
            }
        }

        return $out;
    }

    /**
     * Each absent person once: the dates and lines they are missing from,
     * and the orders whose people reasons name them.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>>
     */
    private function absences(array $rows, array $operations): array
    {
        $affected = [];
        foreach ($operations as $op) {
            foreach ($op['reasons'] ?? [] as $reason) {
                if (! in_array($reason['code'] ?? null, self::PEOPLE_CODES, true)) {
                    continue;
                }
                foreach ($reason['absent_worker_ids'] ?? [] as $id) {
                    $affected[(int) $id][(int) $op['work_order_id']] = ['work_order_id' => (int) $op['work_order_id'], 'order_no' => $op['order_no']];
                }
            }
        }

        $people = [];
        foreach ($rows as $row) {
            foreach ($row['absent'] as $worker) {
                $entry = $people[$worker['id']] ?? ['worker_id' => $worker['id'], 'name' => $worker['name'], 'dates' => [], 'lines' => []];
                $entry['dates'][$row['date']] = $row['date'];
                $entry['lines'][$row['line_id']] = ['id' => $row['line_id'], 'name' => $row['line_name']];
                $people[$worker['id']] = $entry;
            }
        }

        $out = [];
        foreach ($people as $id => $entry) {
            $dates = array_values($entry['dates']);
            sort($dates);
            $out[] = [
                'worker_id' => $entry['worker_id'],
                'name' => $entry['name'],
                'dates' => $dates,
                'lines' => array_values($entry['lines']),
                'affected_orders' => array_values($affected[$id] ?? []),
            ];
        }
        usort($out, fn ($a, $b) => [$a['dates'][0], mb_strtolower((string) $a['name']), $a['worker_id']]
            <=> [$b['dates'][0], mb_strtolower((string) $b['name']), $b['worker_id']]);

        return $out;
    }
}
