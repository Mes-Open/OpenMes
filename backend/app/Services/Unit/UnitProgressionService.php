<?php

namespace App\Services\Unit;

use App\Models\Batch;
use App\Models\ProcessTemplate;
use App\Models\SerialUnit;
use App\Models\UnitStep;
use App\Models\User;
use App\Services\Serial\SerialNumberService;
use App\Services\Traceability\SerialTraceService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit step progression for batches whose template runs in 'unit'
 * execution_mode (#290) — the Unit-mode counterpart to BatchService, but
 * scoped to one SerialUnit's own UnitStep rows instead of a batch's
 * BatchStep rows. Deliberately does not touch BatchService or BatchStep:
 * every existing Batch-mode code path stays exactly as it was.
 */
class UnitProgressionService
{
    public function __construct(
        protected SerialTraceService $serials,
        protected SerialNumberService $serialNumbers,
        protected WorkOrderService $workOrderService,
    ) {}

    /**
     * Register a physical piece against a Unit-mode batch and create its
     * unit_steps pipeline — one row per BatchStep in the batch, entering at
     * step 1 (READY), the rest PENDING.
     *
     * A serial number is NOT required to register (#290 known-bugs item 3):
     * on a real line it's sometimes only known a few steps in (a nameplate
     * applied later, a scan at a downstream station), not always available
     * up front. Three ways to call this:
     *   - `$serialNo` given            → use it (idempotent: re-registering
     *     an already-known serial returns the existing unit, pipeline intact).
     *   - `$serialNo` null, `$autoGenerate` true → generate one now from the
     *     product type's SerialSequence (the old default behavior).
     *   - neither                      → register unserialized; the piece can
     *     start and progress through steps immediately, and gets a serial
     *     later via assignSerial().
     *
     * @throws \Exception
     */
    public function registerUnit(Batch $batch, ?string $serialNo = null, bool $autoGenerate = false): SerialUnit
    {
        return DB::transaction(function () use ($batch, $serialNo, $autoGenerate) {
            $this->guardUnitMode($batch);

            $workOrder = $batch->workOrder;

            if ($serialNo) {
                $unit = $this->serials->registerUnit($serialNo, [
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'batch_id' => $batch->id,
                    'status' => SerialUnit::STATUS_IN_PRODUCTION,
                    'produced_at' => now(),
                ]);
            } elseif ($autoGenerate) {
                $unit = $this->serials->registerUnit($this->serialNumbers->generateSerial($workOrder->productType), [
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'batch_id' => $batch->id,
                    'status' => SerialUnit::STATUS_IN_PRODUCTION,
                    'produced_at' => now(),
                ]);
            } else {
                // No natural key to firstOrCreate() against — always a new piece.
                $unit = SerialUnit::create([
                    'serial_no' => null,
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'batch_id' => $batch->id,
                    'status' => SerialUnit::STATUS_IN_PRODUCTION,
                    'produced_at' => now(),
                ]);
            }

            if (! UnitStep::where('serial_unit_id', $unit->id)->exists()) {
                foreach ($batch->steps()->orderBy('step_number')->get() as $step) {
                    UnitStep::create([
                        'batch_id' => $batch->id,
                        'serial_unit_id' => $unit->id,
                        'batch_step_id' => $step->id,
                        'step_number' => $step->step_number,
                        'status' => $step->step_number === 1 ? UnitStep::STATUS_READY : UnitStep::STATUS_PENDING,
                    ]);
                }
            }

            return $unit->fresh();
        });
    }

    /**
     * Assign a serial number to a piece that was registered without one
     * (#290 known-bugs item 3). Works at any point in its pipeline — nothing
     * about step progression depends on serial_no, so a piece can be mid-way
     * through its steps when this is called.
     *
     * @throws \Exception
     */
    public function assignSerial(SerialUnit $unit, ?string $serialNo = null, bool $autoGenerate = false): SerialUnit
    {
        return DB::transaction(function () use ($unit, $serialNo, $autoGenerate) {
            if ($unit->isSerialized()) {
                throw new \Exception(__('This unit already has a serial number.'));
            }

            if (! $serialNo) {
                if (! $autoGenerate) {
                    throw new \Exception(__('Provide a serial number or choose auto-generate.'));
                }
                $serialNo = $this->serialNumbers->generateSerial($unit->workOrder?->productType);
            }

            $taken = SerialUnit::where('serial_no', $serialNo)
                ->where('tenant_id', $unit->tenant_id)
                ->where('id', '!=', $unit->id)
                ->exists();
            if ($taken) {
                throw new \Exception(__('Serial number :serial is already in use.', ['serial' => $serialNo]));
            }

            $unit->update(['serial_no' => $serialNo]);

            return $unit->fresh();
        });
    }

    /**
     * @throws \Exception
     */
    public function startUnitStep(UnitStep $unitStep, User $user): UnitStep
    {
        return DB::transaction(function () use ($unitStep, $user) {
            if (! $unitStep->canStart()) {
                $this->throwValidationError($unitStep);
            }

            $unitStep->update([
                'status' => UnitStep::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'started_by_id' => $user->id,
            ]);

            return $unitStep->fresh();
        });
    }

    /**
     * @throws \Exception
     */
    public function completeUnitStep(UnitStep $unitStep, User $user): UnitStep
    {
        return DB::transaction(function () use ($unitStep, $user) {
            if (! $unitStep->canComplete()) {
                throw new \Exception(__('Unit step cannot be completed. Current status: :status', ['status' => $unitStep->status]));
            }

            $unitStep->update([
                'status' => UnitStep::STATUS_DONE,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            $this->promoteNextUnitStep($unitStep);
            $this->syncSerialUnitStatus($unitStep->serialUnit);
            $this->syncBatchStatus($unitStep->batch->fresh());

            return $unitStep->fresh();
        });
    }

    private function guardUnitMode(Batch $batch): void
    {
        $mode = $batch->workOrder->process_snapshot['execution_mode'] ?? ProcessTemplate::EXECUTION_MODE_BATCH;

        if ($mode !== ProcessTemplate::EXECUTION_MODE_UNIT) {
            throw new \Exception(__('This batch is not running in unit execution mode.'));
        }
    }

    /** The next step for this same unit becomes READY once its prerequisite clears. */
    private function promoteNextUnitStep(UnitStep $completed): void
    {
        $next = UnitStep::where('serial_unit_id', $completed->serial_unit_id)
            ->where('step_number', $completed->step_number + 1)
            ->first();

        if ($next && $next->status === UnitStep::STATUS_PENDING && $next->prerequisitesMet()) {
            $next->update(['status' => UnitStep::STATUS_READY]);
        }
    }

    /** A unit is COMPLETED once every one of its steps is DONE or SKIPPED. */
    private function syncSerialUnitStatus(SerialUnit $unit): void
    {
        $incomplete = UnitStep::where('serial_unit_id', $unit->id)
            ->whereNotIn('status', [UnitStep::STATUS_DONE, UnitStep::STATUS_SKIPPED])
            ->exists();

        if (! $incomplete && $unit->status !== SerialUnit::STATUS_COMPLETED) {
            $unit->update(['status' => SerialUnit::STATUS_COMPLETED]);
        }
    }

    /**
     * A Unit-mode batch reaches DONE only once every one of its registered
     * units has completed every step (scope doc decision #4). Deliberately
     * implemented here rather than in BatchService::updateBatchStatus() —
     * that method only ever runs from BatchService::startStep()/completeStep(),
     * which a Unit-mode batch's operator interaction never calls (it goes
     * through this service instead), so Batch-mode's own status logic is
     * never touched by this branch.
     */
    private function syncBatchStatus(Batch $batch): void
    {
        if ($batch->status === Batch::STATUS_DONE) {
            return;
        }

        $units = SerialUnit::where('batch_id', $batch->id)->get();
        if ($units->isEmpty()) {
            return;
        }

        $allComplete = $units->every(fn (SerialUnit $u) => $u->status === SerialUnit::STATUS_COMPLETED);

        if (! $allComplete) {
            if ($batch->status !== Batch::STATUS_IN_PROGRESS) {
                $batch->update([
                    'status' => Batch::STATUS_IN_PROGRESS,
                    'started_at' => $batch->started_at ?? now(),
                ]);
            }

            return;
        }

        $batch->update([
            'status' => Batch::STATUS_DONE,
            'completed_at' => now(),
            'produced_qty' => $units->count(),
        ]);

        $workOrder = $batch->workOrder;
        $totalProduced = $workOrder->batches()->where('status', Batch::STATUS_DONE)->sum('produced_qty');
        $workOrder->update(['produced_qty' => $totalProduced]);

        $this->workOrderService->updateWorkOrderStatus($workOrder);
    }

    /**
     * @throws \Exception
     */
    private function throwValidationError(UnitStep $unitStep): void
    {
        if (! in_array($unitStep->status, [UnitStep::STATUS_PENDING, UnitStep::STATUS_READY], true)) {
            throw new \Exception(__('Unit step is already :status', ['status' => $unitStep->status]));
        }

        if ($unitStep->batch->workOrder->isBlocked()) {
            throw new \Exception(__('Work order is blocked by open issues.'));
        }

        throw new \Exception(__('The previous step for this unit must be completed first.'));
    }
}
