<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Support\ProductionFlow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The workstation an operator is working at, shared by the Queue and
 * Workstation views so switching between them keeps the selection.
 *
 * Source order: `?workstation=` query param (persisted to the session;
 * `all` or empty clears it), then the session, then — unless the caller opts
 * out with `$fallBackToAccount` — a workstation account's own assigned workstation.
 */
class OperatorWorkstationSelection
{
    public function resolve(Request $request, int $lineId, bool $allowOtherLines = false, bool $fallBackToAccount = true): ?Workstation
    {
        if ($request->has('workstation')) {
            $raw = $request->query('workstation');
            $id = is_numeric($raw) ? (int) $raw : null;
            $request->session()->put('selected_workstation_id', $id);
        } else {
            $user = $request->user();
            $id = $request->session()->get('selected_workstation_id')
                ?? ($fallBackToAccount && $user?->account_type === 'workstation' ? $user->workstation_id : null);
        }

        if (! $id) {
            return null;
        }

        // A workstation may belong to another line when routing spans lines.
        return Workstation::whereKey($id)
            ->when(! $allowOtherLines, fn ($q) => $q->where('line_id', $lineId))
            ->first();
    }

    /**
     * Work orders with at least one batch whose current step runs on the workstation.
     * With $includeNotStarted, also the orders {@see notStartedAt()} returns,
     * keeping the input order.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return Collection<int, WorkOrder>
     */
    public function workOrdersAt(Collection $workOrders, Workstation $workstation, bool $includeNotStarted = false): Collection
    {
        $workOrders->loadMissing('batches.steps');

        return $workOrders->filter(fn (WorkOrder $wo) => $this->hasBatchAt($wo, $workstation)
            || ($includeNotStarted && $this->isNotStartedAt($wo, $workstation))
        )->values();
    }

    /**
     * Active orders without a live batch whose routing starts at the workstation,
     * so the operator there can start them.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return Collection<int, WorkOrder>
     */
    public function notStartedAt(Collection $workOrders, Workstation $workstation): Collection
    {
        $workOrders->loadMissing('batches.steps');

        return $workOrders->filter(fn (WorkOrder $wo) => $this->isNotStartedAt($wo, $workstation))->values();
    }

    private function liveBatches(WorkOrder $wo): Collection
    {
        return $wo->batches->where('status', '!=', Batch::STATUS_CANCELLED);
    }

    private function hasBatchAt(WorkOrder $wo, Workstation $workstation): bool
    {
        $transfer = ProductionFlow::isTransfer();

        foreach ($this->liveBatches($wo) as $batch) {
            // Transfer flow: several stations work on the same batch at once, so
            // the order is "at" every station with a step running or holding
            // pieces (READY means some have arrived), not only the current one.
            if ($transfer) {
                $here = $batch->steps->first(fn ($step) => (int) $step->workstation_id === (int) $workstation->id
                    && in_array($step->status, [BatchStep::STATUS_READY, BatchStep::STATUS_IN_PROGRESS], true));
                if ($here) {
                    return true;
                }

                continue;
            }

            $currentStep = $batch->currentStep();
            if ($currentStep && (int) $currentStep->workstation_id === (int) $workstation->id) {
                return true;
            }
        }

        return false;
    }

    private function isNotStartedAt(WorkOrder $wo, Workstation $workstation): bool
    {
        return $this->liveBatches($wo)->isEmpty()
            && in_array($wo->status, WorkOrder::ACTIVE_STATUSES, true)
            && $this->routingStartsAt($wo, $workstation);
    }

    /**
     * Whether the first step of the order's process snapshot runs on the
     * workstation. When the first step is a variant group, any of its
     * alternatives counts — the operator picks one on start.
     */
    private function routingStartsAt(WorkOrder $wo, Workstation $workstation): bool
    {
        $steps = collect($wo->process_snapshot['steps'] ?? [])->sortBy('step_number')->values();
        $first = $steps->first();
        if (! $first) {
            return false;
        }

        $group = $first['variant_group'] ?? null;
        $firstSteps = $group === null
            ? collect([$first])
            : $steps->where('variant_group', $group);

        return $firstSteps->contains(fn ($s) => (int) ($s['workstation_id'] ?? 0) === (int) $workstation->id);
    }
}
