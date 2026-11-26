<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PalletRequest;
use App\Http\Requests\RecordPalletConditionRequest;
use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\PalletConditionEvent;
use App\Models\WorkOrder;
use App\Services\Logistics\PalletConditionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PalletController extends Controller
{
    use \App\Http\Controllers\Concerns\StaysOnList;

    public function index(Request $request)
    {
        // /admin/pallets/{id}/edit lands here with ?edit={id}: the record is
        // handed over so the page opens its drawer on it, like every other list.
        $editPallet = $request->integer('edit')
            ? Pallet::find($request->integer('edit'))?->only('id', 'pallet_no', 'work_order_id', 'batch_id', 'qty', 'status', 'location', 'destination', 'erp_reference')
            : null;

        return Inertia::render('admin/pallets/Index', [
            'editPallet' => $editPallet,
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

    /** Editing happens in the list's drawer; a deep link to the old page opens it there. */
    public function edit(Pallet $pallet)
    {
        return redirect()->route('admin.pallets.index', ['edit' => $pallet->id]);
    }

    public function update(PalletRequest $request, Pallet $pallet)
    {
        try {
            // On the row as it is now, under a lock: the ship gates in Pallet::saving
            // read the condition and quality status from this model, and a pallet
            // marked damaged on the move terminal after the drawer opened must not
            // ship on the stale copy. PalletConditionService::record takes the same lock.
            DB::transaction(fn () => Pallet::whereKey($pallet->getKey())->lockForUpdate()->firstOrFail()
                ->update($request->payload()));
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

    /** Mark the pallet physically damaged, or restore it to good (always with a note). */
    public function recordCondition(RecordPalletConditionRequest $request, Pallet $pallet, PalletConditionService $service)
    {
        $service->record($pallet, $request->condition(), $request->validated('note'), $request->user(), PalletConditionEvent::SOURCE_MANUAL);

        return redirect()->route('admin.pallets.index')->with('success', $pallet->isDamaged()
            ? __('Pallet :no marked as damaged.', ['no' => $pallet->pallet_no])
            : __('Pallet :no restored to good condition.', ['no' => $pallet->pallet_no]));
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
