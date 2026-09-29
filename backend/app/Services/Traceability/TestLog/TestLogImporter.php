<?php

namespace App\Services\Traceability\TestLog;

use App\Models\Line;
use App\Models\SerialUnit;
use App\Models\UnitStepHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\SerialTraceService;
use App\Support\UnitSerialisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Puts a parsed test run onto the unit's history: registers the unit on first
 * sight, stamps the row with the tester's own time (not the import's), resolves
 * the station, line and operator the log names to real records where they
 * exist, and leaves the verdict to the plant's fail policy (SerialTraceService).
 * Importing the same run twice is a no-op, keyed on the run id.
 */
class TestLogImporter
{
    public const IMPORTED = 'imported';

    public const DUPLICATE = 'duplicate';

    public function __construct(
        private readonly SerialTraceService $serials,
        private readonly UnitSerialisation $settings,
    ) {}

    /**
     * `operator` attributes every run to that user; `actor` is who to fall back
     * to when the log names nobody we know (the API token's user, for a tester).
     *
     * @param  array{work_order?: ?WorkOrder, workstation?: ?Workstation, operator?: ?User, actor?: ?User, source?: ?string}  $options
     * @return array{status: string, unit: SerialUnit, entry: ?UnitStepHistory}
     */
    public function import(TestRun $run, array $options = []): array
    {
        $serialNo = $this->settings->normalize($run->serialNo);
        $psn = $this->settings->normalize($run->psn);
        // A blank serial would match any unit still waiting for its label.
        if ($serialNo === null) {
            throw new InvalidArgumentException(__('The test run names no serial number.'));
        }

        return DB::transaction(function () use ($run, $serialNo, $psn, $options) {
            $unit = SerialUnit::where('serial_no', $serialNo)->lockForUpdate()->first();
            // A unit started on its PSN and not labelled yet: the tester knows
            // both numbers, so the SN goes onto that unit instead of a new one.
            if (! $unit && $psn !== null) {
                $unit = SerialUnit::where('psn', $psn)->whereNull('serial_no')->lockForUpdate()->first();
                $unit?->update(['serial_no' => $serialNo]);
            }
            $unit ??= $this->serials->registerUnit($serialNo, [
                'psn' => $psn,
                'work_order_id' => ($options['work_order'] ?? null)?->id,
                'status' => SerialUnit::STATUS_IN_PRODUCTION,
            ]);

            $fill = [];
            if ($psn !== null && empty($unit->psn)) {
                $fill['psn'] = $psn;
            }
            if (isset($options['work_order']) && empty($unit->work_order_id)) {
                $fill['work_order_id'] = $options['work_order']->id;
            }
            if ($fill !== []) {
                $unit->update($fill);
            }

            if ($run->runId !== null && $unit->history()->where('parameters->run_id', $run->runId)->exists()) {
                return ['status' => self::DUPLICATE, 'unit' => $unit, 'entry' => null];
            }

            $workstation = ($options['workstation'] ?? null) ?? ($run->station ? Workstation::where('code', $run->station)->first() : null);
            $operator = ($options['operator'] ?? null) ?? $this->operatorNamed($run->operator, $options['actor'] ?? null);
            $line = $run->line ? Line::where('code', $run->line)->first() : null;

            $entry = $this->serials->recordStep($unit, $operator, null, [
                // An unknown station stays unknown - recordStep's fallback to the
                // operator's own station would attribute the test to the wrong bench.
                'workstation_id' => $workstation?->id,
                'exact_workstation' => true,
                'parameters' => array_filter([
                    'event' => 'test',
                    'run_id' => $run->runId,
                    'source' => $options['source'] ?? null,
                    'station' => $run->station,
                    'line' => $line?->code ?? $run->line,
                    'operator' => $run->operator,
                    'started_at' => $run->startedAt?->toIso8601String(),
                    'ended_at' => $run->endedAt?->toIso8601String(),
                    'steps' => $run->steps,
                    'failed_steps' => $run->failedSteps,
                    ...$run->extra,
                ], fn ($v) => $v !== null && $v !== []),
                'result' => $run->verdict,
                'notes' => $this->notes($run),
                'processed_at' => $run->processedAt(),
            ]);

            return ['status' => self::IMPORTED, 'unit' => $unit->fresh(), 'entry' => $entry];
        });
    }

    /** The user the log names, by name, e-mail or username; else the fallback; a system account is not invented. */
    private function operatorNamed(?string $name, ?User $fallback = null): User
    {
        $user = $name ? User::where('name', $name)->orWhere('email', $name)->orWhere('username', $name)->first() : null;

        return $user ?? $fallback ?? User::orderBy('id')->firstOrFail();
    }

    private function notes(TestRun $run): string
    {
        $text = match ($run->verdict) {
            TestRun::FAIL => __('Test verdict FAIL').($run->failedSteps ? ' - '.__('steps').': '.implode(', ', $run->failedSteps) : ''),
            TestRun::REWORK => __('Test verdict REWORK'),
            default => __('Test verdict PASS'),
        };

        return mb_strimwidth($text, 0, 500, '…');
    }
}
