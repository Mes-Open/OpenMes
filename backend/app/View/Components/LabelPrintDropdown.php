<?php

namespace App\View\Components;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\LabelTemplate;
use App\Models\WorkOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class LabelPrintDropdown extends Component
{
    public string $type;

    public ?int $entityId;

    public string $label;

    public string $routeBase;

    public string $paramName;

    public Collection $templates;

    public function __construct(
        ?WorkOrder $workOrder = null,
        ?Batch $batch = null,
        ?BatchStep $batchStep = null,
        ?string $type = null,
        ?string $label = null,
    ) {
        $this->type = $type ?? LabelTemplate::TYPE_WORK_ORDER;
        $this->label = $label ?? __('Print Label');

        $this->entityId = $workOrder?->id ?? $batch?->id ?? $batchStep?->id;

        $this->routeBase = match ($this->type) {
            LabelTemplate::TYPE_FINISHED_GOODS => 'packaging.labels.finished-goods',
            LabelTemplate::TYPE_WORKSTATION_STEP => 'packaging.labels.workstation-step',
            default => 'packaging.labels.work-order',
        };

        $this->paramName = match ($this->type) {
            LabelTemplate::TYPE_FINISHED_GOODS => 'batch',
            LabelTemplate::TYPE_WORKSTATION_STEP => 'batchStep',
            default => 'workOrder',
        };

        $this->templates = LabelTemplate::query()
            ->where('type', $this->type)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('components.label-print-dropdown');
    }
}
