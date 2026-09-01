<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQualityControlTriggerRequest;
use App\Http\Requests\UpdateQualityControlTriggerRequest;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\QualityCheckTemplate;
use App\Models\QualityControlTrigger;
use App\Models\Workstation;
use Inertia\Inertia;

class QualityControlTriggerController extends Controller
{
    use StaysOnList;

    public function index()
    {
        return Inertia::render('admin/quality-control-triggers/Index', [
            'templateNames' => QualityCheckTemplate::pluck('name', 'id'),
            'lineNames' => Line::pluck('name', 'id'),
            'workstationNames' => Workstation::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
            // Option lists for the list page's create/edit drawer — the same set
            // the standalone form gets. Optional, so the queries only run once
            // someone opens it; most visits to the list never do.
            'templates' => Inertia::optional(fn () => QualityCheckTemplate::orderBy('name')->get(['id', 'name'])),
            'lines' => Inertia::optional(fn () => Line::orderBy('name')->get(['id', 'name'])),
            'workstations' => Inertia::optional(fn () => Workstation::orderBy('name')->get(['id', 'name'])),
            'productTypes' => Inertia::optional(fn () => ProductType::orderBy('name')->get(['id', 'name'])),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/quality-control-triggers/Create', $this->formOptions());
    }

    public function store(StoreQualityControlTriggerRequest $request)
    {
        $trigger = QualityControlTrigger::create($request->validated());

        return $this->saved($request, redirect()->route('admin.quality-control-triggers.index'), __('Quality control trigger ":name" created.', ['name' => $trigger->name]));
    }

    public function edit(QualityControlTrigger $qualityControlTrigger)
    {
        return Inertia::render('admin/quality-control-triggers/Edit', array_merge($this->formOptions(), [
            'trigger' => $qualityControlTrigger->only(
                'id', 'name', 'trigger_type', 'quality_check_template_id', 'line_id', 'workstation_id',
                'product_type_id', 'threshold_n', 'downtime_min_minutes', 'is_blocking', 'is_active',
            ),
        ]));
    }

    public function update(UpdateQualityControlTriggerRequest $request, QualityControlTrigger $qualityControlTrigger)
    {
        $qualityControlTrigger->update($request->validated());

        return $this->saved($request, redirect()->route('admin.quality-control-triggers.index'), __('Quality control trigger ":name" updated.', ['name' => $qualityControlTrigger->name]));
    }

    public function destroy(QualityControlTrigger $qualityControlTrigger)
    {
        $name = $qualityControlTrigger->name;
        $qualityControlTrigger->delete();

        return redirect()->route('admin.quality-control-triggers.index')
            ->with('success', __('Quality control trigger ":name" deleted.', ['name' => $name]));
    }

    public function toggleActive(QualityControlTrigger $qualityControlTrigger)
    {
        $qualityControlTrigger->update(['is_active' => ! $qualityControlTrigger->is_active]);

        return redirect()->back()->with('success', __('Quality control trigger updated.'));
    }

    private function formOptions(): array
    {
        return [
            'templates' => QualityCheckTemplate::orderBy('name')->get(['id', 'name']),
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'workstations' => Workstation::orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::orderBy('name')->get(['id', 'name']),
        ];
    }
}
