<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\BatchStep;
use Livewire\Component;

class BatchStepList extends Component
{
    public $batchId;
    public $batch;

    public function mount($batchId)
    {
        $this->batchId = $batchId;
        $this->loadBatch();
    }

    public function loadBatch()
    {
        $this->batch = Batch::with([
            'steps.startedBy',
            'steps.completedBy',
            'workOrder.productType'
        ])->find($this->batchId);
    }

    public function startStep($stepId)
    {
        $step = BatchStep::find($stepId);

        if (!$step || $step->batch_id != $this->batchId) {
            session()->flash('error', 'Step not found.');
            return;
        }

        if ($step->status !== 'PENDING') {
            session()->flash('error', 'Step is not in pending status.');
            return;
        }

        $step->update([
            'status' => 'IN_PROGRESS',
            'started_at' => now(),
            'started_by' => auth()->id(),
        ]);

        $this->loadBatch();
        session()->flash('success', 'Step started successfully.');
    }

    public function completeStep($stepId)
    {
        $step = BatchStep::find($stepId);

        if (!$step || $step->batch_id != $this->batchId) {
            session()->flash('error', 'Step not found.');
            return;
        }

        if ($step->status !== 'IN_PROGRESS') {
            session()->flash('error', 'Step is not in progress.');
            return;
        }

        $step->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        // Update batch and work order status
        $this->updateBatchStatus();
        $this->loadBatch();
        session()->flash('success', 'Step completed successfully.');
    }

    protected function updateBatchStatus()
    {
        $batch = Batch::find($this->batchId);

        $allSteps = $batch->steps;
        $completedSteps = $allSteps->where('status', 'COMPLETED')->count();
        $totalSteps = $allSteps->count();

        if ($completedSteps === $totalSteps) {
            $batch->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Update work order completed qty
            $workOrder = $batch->workOrder;
            $workOrder->increment('completed_qty', $batch->actual_qty);

            // Check if all batches are completed
            $allBatches = $workOrder->batches;
            $completedBatches = $allBatches->where('status', 'COMPLETED')->count();

            if ($completedBatches === $allBatches->count() && $workOrder->completed_qty >= $workOrder->planned_qty) {
                $workOrder->update([
                    'status' => 'COMPLETED',
                    'completed_at' => now(),
                ]);
            }
        } elseif ($batch->status === 'PENDING') {
            $batch->update(['status' => 'IN_PROGRESS']);

            if ($batch->workOrder->status === 'PENDING') {
                $batch->workOrder->update(['status' => 'IN_PROGRESS']);
            }
        }
    }

    public function render()
    {
        return view('livewire.batch-step-list');
    }
}
