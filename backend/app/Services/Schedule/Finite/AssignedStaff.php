<?php

namespace App\Services\Schedule\Finite;

use App\Extension\Contracts\WorkforceProvider;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonInterface;

/**
 * Who staffs which line, read from what core records: the operator accounts
 * assigned to a line, the active non-logistics workers assigned to its
 * workstations and the workers of any crew the workforce module assigns to it.
 *
 * Nothing is cached here — {@see AssignedStaffCapacityProvider} keeps its own
 * copy for one run, while {@see fingerprint()} must see the data as it is now,
 * or a draft could never notice that its staffing changed.
 *
 * A module that binds its own WorkforceCapacityProvider counts people its own
 * way; the fingerprint still follows these core sources plus the module's
 * crews and absences, so a signal only that module knows is not covered.
 */
final class AssignedStaff
{
    /**
     * line id (or 'none') => people, keyed `w:<worker id>` or, for an account
     * with no worker record, `u:<user id>`. One query per kind of assignment.
     *
     * @return array<int|string, list<string>>
     */
    public static function byLine(WorkforceProvider $people): array
    {
        $staff = [];
        $users = User::with('lines:id')->whereHas('lines')->get(['id', 'worker_id']);
        foreach ($users as $user) {
            foreach ($user->lines as $line) {
                $staff[$line->id][] = $user->worker_id ? 'w:'.$user->worker_id : 'u:'.$user->id;
            }
        }

        $workers = Worker::active()->where('is_logistics', false)->with('workstation:id,line_id')->get(['id', 'workstation_id']);
        foreach ($workers as $worker) {
            $staff[$worker->workstation?->line_id ?? 'none'][] = 'w:'.$worker->id;
        }

        // A crew explicitly assigned to lines staffs all of them.
        foreach ($people->crewsForCapacity() as $crew) {
            if (! $crew->relationLoaded('lines') || $crew->lines->isEmpty()) {
                continue;
            }
            $members = $crew->workers->filter(fn ($w) => ($w->is_active ?? true) && ! ($w->is_logistics ?? false))
                ->map(fn ($w) => 'w:'.$w->id)->all();
            foreach ($crew->lines as $line) {
                $staff[$line->id] = [...($staff[$line->id] ?? []), ...$members];
            }
        }

        return array_map(fn (array $ids) => array_values(array_unique($ids)), $staff);
    }

    /**
     * A digest of the staffing a run counted between $start and $end: who
     * staffs each line, and, for each day of the window, which of those people
     * are absent. Worker activity and the logistics flag count through their
     * effect on the line map; an absence of someone who staffs no line, or on a
     * day outside the window, changes nothing the scheduler used.
     */
    public static function fingerprint(WorkforceProvider $people, CarbonInterface $start, CarbonInterface $end): string
    {
        $staff = self::byLine($people);
        ksort($staff);
        $staffed = [];
        foreach ($staff as $line => $ids) {
            sort($ids);
            $staff[$line] = $ids;
            foreach ($ids as $key) {
                if (str_starts_with($key, 'w:')) {
                    $staffed[(int) substr($key, 2)] = true;
                }
            }
        }

        $absent = [];
        for ($day = $start->toImmutable()->startOfDay(); $day->lessThan($end); $day = $day->addDay()) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $people->absentWorkerIds($day)),
                fn (int $id) => isset($staffed[$id]),
            )));
            sort($ids);
            if ($ids !== []) {
                $absent[$day->toDateString()] = $ids;
            }
        }

        return md5(json_encode([$staff, $absent]));
    }
}
