<?php

namespace App\Services\Production;

use App\Models\WorkOrder;
use App\Models\Workstation;

/**
 * The stations offered by the operator shell's workstation picker: each
 * line station with the code and name it reads by, the group it is listed
 * under, and how many active orders wait there (a step ready or running at
 * it, or a routing that begins at it).
 */
class OperatorStationPicker
{
    public function __construct(private readonly OperatorWorkstationSelection $selection) {}

    /**
     * @return list<array{id: int, code: string, name: string, group: string, count: int}>
     */
    public function forLine(int $lineId): array
    {
        $stations = Workstation::where('line_id', $lineId)
            ->where('is_active', true)
            ->with('workstationType:id,name')
            ->get();
        if ($stations->isEmpty()) {
            return [];
        }

        $orders = WorkOrder::availableForProduction()
            ->where('line_id', $lineId)
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->with('batches.steps')
            ->get();

        return $stations
            ->map(function (Workstation $station) use ($orders) {
                [$code, $name] = $this->label($station);

                return [
                    'id' => $station->id,
                    'code' => $code,
                    'name' => $name,
                    'group' => $station->workstationType?->name
                        ?: ($station->workstation_type ?: $this->prefix($code)),
                    'count' => $this->selection->workOrdersAt($orders, $station, includeNotStarted: true)->count(),
                ];
            })
            ->sort(fn (array $a, array $b) => strnatcasecmp($a['group'], $b['group']) ?: strnatcasecmp($a['code'], $b['code']))
            ->values()
            ->all();
    }

    /**
     * Stations are commonly named "FA-2 · Rolki i nadanie PSN": the part before
     * the separator is what the floor calls the station, so it is the code shown
     * (even where the record's own code differs, e.g. a tester bench "S1-01").
     *
     * @return array{0: string, 1: string}
     */
    private function label(Workstation $station): array
    {
        $parts = explode(' · ', (string) $station->name, 2);

        return count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== ''
            ? [trim($parts[0]), trim($parts[1])]
            : [(string) ($station->code ?: $station->name), (string) $station->name];
    }

    /** "FA-17" → "FA", "Offline 1" → "Offline": the family a station belongs to. */
    private function prefix(string $code): string
    {
        $prefix = trim((string) preg_replace('/[\s\-_.]*\d.*$/', '', $code));

        return $prefix !== '' ? $prefix : $code;
    }
}
