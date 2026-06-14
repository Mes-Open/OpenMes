<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PalletRequest;
use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\WorkOrder;
use Inertia\Inertia;

class PalletController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/pallets/Index', [
            'workOrderNumbers' => WorkOrder::pluck('order_no', 'id'),
            'statusLabels' => $this->statusLabels(),
            'labelTemplates' => $this->activeLabelTemplates(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/pallets/Create', [
            'workOrders' => $this->workOrderOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(PalletRequest $request)
    {
        Pallet::create($request->payload());

        return redirect()->route('admin.pallets.index')
            ->with('success', __('Pallet created.'));
    }

    public function edit(Pallet $pallet)
    {
        return Inertia::render('admin/pallets/Edit', [
            'pallet' => $pallet->only(
                'id', 'pallet_no', 'work_order_id', 'qty', 'status', 'location', 'erp_reference',
            ),
            'workOrders' => $this->workOrderOptions(),
            'statuses' => $this->statusOptions(),
            'labelTemplates' => $this->activeLabelTemplates(),
        ]);
    }

    public function update(PalletRequest $request, Pallet $pallet)
    {
        $pallet->update($request->payload());

        return redirect()->route('admin.pallets.index')
            ->with('success', __('Pallet updated.'));
    }

    public function destroy(Pallet $pallet)
    {
        $pallet->delete();

        return redirect()->route('admin.pallets.index')
            ->with('success', __('Pallet deleted.'));
    }

    private function workOrderOptions()
    {
        return WorkOrder::orderByDesc('id')
            ->limit(500)
            ->get(['id', 'order_no'])
            ->map(fn (WorkOrder $wo) => ['id' => $wo->id, 'order_no' => $wo->order_no]);
    }

    /** @return array<string, string> */
    private function statusLabels(): array
    {
        $labels = [];
        foreach (PalletStatus::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return array_map(
            fn (PalletStatus $c) => ['value' => $c->value, 'label' => $c->label()],
            PalletStatus::cases(),
        );
    }

    private function activeLabelTemplates()
    {
        return LabelTemplate::where('is_active', true)
            ->where('type', LabelTemplate::TYPE_PALLET)
            ->get(['id', 'name', 'type', 'size', 'barcode_format', 'is_default']);
    }
}
