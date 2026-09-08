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
    use \App\Http\Controllers\Concerns\StaysOnList;

    public function index()
    {
        return Inertia::render('admin/pallets/Index', [
            // Closures so the drawer's partial reload (only=[workOrders,statuses])
            // doesn't compute these just for Inertia to discard them.
            'workOrderNumbers' => fn () => WorkOrder::pluck('order_no', 'id'),
            'statusLabels' => fn () => PalletStatus::labels(),
            'labelTemplates' => fn () => $this->activeLabelTemplates(),
            // The drawer's option lists — fetched on first open, not per visit.
            'workOrders' => Inertia::optional(fn () => $this->workOrderOptions()),
            'statuses' => Inertia::optional(fn () => PalletStatus::options()),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/pallets/Create', [
            'workOrders' => $this->workOrderOptions(),
            'statuses' => PalletStatus::options(),
        ]);
    }

    public function store(PalletRequest $request)
    {
        Pallet::create($request->payload());

        return $this->saved($request, redirect()->route('admin.pallets.index'), __('Pallet created.'));
    }

    public function edit(Pallet $pallet)
    {
        return Inertia::render('admin/pallets/Edit', [
            'pallet' => $pallet->only(
                'id', 'pallet_no', 'work_order_id', 'batch_id', 'qty', 'status', 'location', 'destination', 'erp_reference',
            ),
            'workOrders' => $this->workOrderOptions(),
            'statuses' => PalletStatus::options(),
            'labelTemplates' => $this->activeLabelTemplates(),
        ]);
    }

    public function update(PalletRequest $request, Pallet $pallet)
    {
        try {
            $pallet->update($request->payload());
        } catch (\DomainException $e) {
            // Quality ship-gate (#106) rejected the closed → shipped transition.
            // Answer as a validation error on the field that caused it: a flash
            // 'error' rides a 2xx visit with an empty errors bag, which the edit
            // drawer reads as success — it would close and discard the input.
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }

        return $this->saved($request, redirect()->route('admin.pallets.index'), __('Pallet updated.'));
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
            ->with('batches:id,work_order_id,batch_number,lot_number')
            ->get(['id', 'order_no'])
            ->map(fn (WorkOrder $wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'batches' => $wo->batches->map(fn ($b) => [
                    'id' => $b->id,
                    'label' => $b->displayLabel(),
                ])->values(),
            ]);
    }

    private function activeLabelTemplates()
    {
        return LabelTemplate::where('is_active', true)
            ->where('type', LabelTemplate::TYPE_PALLET)
            ->get(['id', 'name', 'type', 'size', 'barcode_format', 'is_default']);
    }
}
