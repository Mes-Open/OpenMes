<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Services\Material\MaterialAllocationService;
use App\Services\WorkOrder\BatchService;
use Livewire\Component;

class BatchStepList extends Component
{
    public int $batchId;

    public ?Batch $batch = null;

    public bool $showAllocationConfirm = false;

    public array $allocationPreview = [];

    public ?int $pendingStepId = null;

    public function mount(int $batchId): void
    {
        $this->batchId = $batchId;
        $this->loadBatch();
    }

    /** Estimated durations keyed by step_number, from process_snapshot */
    public array $estimatedDurations = [];

    public function loadBatch(): void
    {
        $this->batch = Batch::with([
            'steps.startedBy',
            'steps.completedBy',
            'steps.workstation',
            'workOrder.productType',
        ])->find($this->batchId);

        if ($this->batch) {
            $snapshot = $this->batch->workOrder->process_snapshot ?? [];
            $this->estimatedDurations = collect($snapshot['steps'] ?? [])
                ->pluck('estimated_duration_minutes', 'step_number')
                ->toArray();
        }
    }

    public function startStep(int $stepId): void
    {
        $step = BatchStep::find($stepId);

        if (! $step || $step->batch_id !== $this->batchId) {
            session()->flash('error', 'Step not found.');

            return;
        }

        // If batch is PENDING and has BOM, show allocation confirmation first
        if ($this->batch->status === Batch::STATUS_PENDING) {
            $bom = $this->batch->workOrder->process_snapshot['bom'] ?? [];
            if (! empty($bom)) {
                $this->allocationPreview = app(MaterialAllocationService::class)
                    ->previewForBatch($this->batch);
                $this->pendingStepId = $stepId;
                $this->showAllocationConfirm = true;

                return;
            }
        }

        $this->executeStartStep($step);
    }

    public function confirmAllocation(): void
    {
        if (! $this->pendingStepId) {
            return;
        }

        $step = BatchStep::find($this->pendingStepId);
        if (! $step) {
            session()->flash('error', 'Step not found.');

            return;
        }

        $this->showAllocationConfirm = false;
        $this->allocationPreview = [];
        $this->pendingStepId = null;

        $this->executeStartStep($step);
    }

    public function cancelAllocation(): void
    {
        $this->showAllocationConfirm = false;
        $this->allocationPreview = [];
        $this->pendingStepId = null;
    }

    protected function executeStartStep(BatchStep $step): void
    {
        try {
            app(BatchService::class)->startStep($step, auth()->user());
            $this->loadBatch();
            session()->flash('success', 'Step started. Materials have been allocated.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeStep(int $stepId): void
    {
        $step = BatchStep::find($stepId);

        if (! $step || $step->batch_id !== $this->batchId) {
            session()->flash('error', 'Step not found.');

            return;
        }

        try {
            app(BatchService::class)->completeStep($step, auth()->user());
            $this->loadBatch();
            session()->flash('success', 'Step completed.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.batch-step-list');
    }
}
