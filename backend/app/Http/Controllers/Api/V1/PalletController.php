<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PalletRequest;
use App\Models\Pallet;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pallets (finished-goods packing units), mirroring the web admin pallets screen
 * (Pages/admin/pallets): pallet no, work order, qty, status, quality status,
 * location, ERP reference. Full CRUD; quality_status is derived (read-only) and
 * label printing (PDF/ZPL) stays on the web admin.
 */
class PalletController extends Controller
{
    private function present(Pallet $p): array
    {
        return [
            'id' => $p->id,
            'pallet_no' => $p->pallet_no,
            'work_order_id' => $p->work_order_id,
            'order_no' => $p->workOrder?->order_no,
            'batch_id' => $p->batch_id,
            'qty' => $p->qty,
            'status' => $p->status?->value,
            'quality_status' => $p->quality_status,
            'location' => $p->location,
            'erp_reference' => $p->erp_reference,
            'shipped_at' => optional($p->shipped_at)->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $pallets = Pallet::with('workOrder:id,order_no')
            ->when(in_array($status, ['open', 'closed', 'shipped'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Pallet $p) => $this->present($p));

        return response()->json(['data' => $pallets]);
    }

    /** Work-order + status catalogs for the create/edit form. */
    public function formMeta(): JsonResponse
    {
        return response()->json(['data' => [
            'statuses' => collect(PalletStatus::cases())->map(fn (PalletStatus $s) => [
                'value' => $s->value,
                'label' => ucfirst($s->value),
            ]),
            'work_orders' => WorkOrder::orderByDesc('id')->limit(500)->get(['id', 'order_no'])
                ->map(fn (WorkOrder $w) => ['id' => $w->id, 'order_no' => $w->order_no]),
        ]]);
    }

    public function store(PalletRequest $request): JsonResponse
    {
        try {
            $pallet = Pallet::create($request->validated());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($pallet->fresh('workOrder'))], 201);
    }

    public function update(PalletRequest $request, Pallet $pallet): JsonResponse
    {
        try {
            $pallet->update($request->validated());
        } catch (\DomainException $e) {
            // e.g. the quality ship-gate (#106): can't ship before QC passes.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($pallet->fresh('workOrder'))]);
    }

    public function destroy(Pallet $pallet): JsonResponse
    {
        $pallet->delete();

        return response()->json(null, 204);
    }
}
