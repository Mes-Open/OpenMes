<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Services\WorkOrder\BatchService;
use Livewire\Component;

class BatchStepList extends Component
{
    public int $batchId;
    public ?Batch $batch = null;

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

        if (!$step || $step->batch_id !== $this->batchId) {
            session()->flash('error', 'Step not found.');
            return;
        }

        try {
            app(BatchService::class)->startStep($step, auth()->user());
            $this->loadBatch();
            session()->flash('success', 'Step started.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeStep(int $stepId): void
    {
        $step = BatchStep::find($stepId);

        if (!$step || $step->batch_id !== $this->batchId) {
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
