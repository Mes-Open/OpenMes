<?php

namespace App\Services\Schedule\Finite;

use Carbon\CarbonInterface;

/**
 * What to schedule and within which window.
 *
 * `scope` decides which orders the run may move: `unplanned` places only
 * orders without a minute plan around everything already planned; `all`
 * replans every active order that has not started. Orders outside the run keep
 * their placement and count as committed load. `respectMaterial`,
 * `respectWorkforce` and `respectQuality` switch the material, people and
 * quality-hold constraints off for a run.
 */
final class SchedulingRequest
{
    const SCOPE_UNPLANNED = 'unplanned';

    const SCOPE_ALL = 'all';

    const SCOPES = [self::SCOPE_UNPLANNED, self::SCOPE_ALL];

    /**
     * @param  list<int>|null  $workOrderIds  restrict the run to these orders
     */
    public function __construct(
        public readonly CarbonInterface $start,
        public readonly CarbonInterface $end,
        public readonly string $scope = self::SCOPE_UNPLANNED,
        public readonly bool $respectMaterial = true,
        public readonly ?array $workOrderIds = null,
        public readonly bool $respectWorkforce = true,
        public readonly bool $respectQuality = true,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'scope' => $this->scope,
            'respect_material' => $this->respectMaterial,
            'respect_workforce' => $this->respectWorkforce,
            'respect_quality' => $this->respectQuality,
            'work_order_ids' => $this->workOrderIds,
        ];
    }
}
