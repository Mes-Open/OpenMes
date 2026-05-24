<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\MaterialLot;
use App\Models\MaterialSublot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Read API + consumption recording for {@see MaterialLot}.
 *
 * Genealogy queries are answered with two eager-loaded paths:
 *   forward  — consumptions → batchStep → batch → workOrder + product_type
 *   backward — material → bomItems (where this lot's material was consumed
 *              by upstream batches) plus parent-lot lookup for sublot chains
 *
 * Both endpoints return a flat shape; pagination is intentional only on the
 * index — detail / genealogy fits in one response for a single lot.
 */
class MaterialLotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MaterialLot::query()->with(['material', 'source', 'inspection']);

        if ($materialId = $request->query('material_id')) {
            $query->where('material_id', $materialId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplier = $request->query('supplier_lot_no')) {
            $query->where('supplier_lot_no', 'like', '%' . $supplier . '%');
        }
        if ($expiring = $request->query('expiring_within_days')) {
            $until = now()->addDays((int) $expiring)->toDateString();
            $query->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [now()->toDateString(), $until]);
        }
        if ($request->boolean('available_only')) {
            $query->available();
        }

        $perPage = max(1, min((int) $request->query('per_page', 30), 100));
        $page = $query->orderByDesc('received_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(MaterialLot $materialLot): JsonResponse
    {
        $materialLot->load(['material', 'source', 'inspection', 'sublots', 'createdBy']);

        return response()->json(['data' => $materialLot]);
    }

    /**
     * Forward genealogy: where did this lot go?
     *
     * Returns each batch step that consumed this lot (with its parent batch /
     * work order / product type), plus the consumed quantity per record.
     */
    public function forwardGenealogy(MaterialLot $materialLot): JsonResponse
    {
        $consumptions = $materialLot->consumptions()
            ->with([
                'batchStep:id,batch_id,name,step_number,status',
                'batchStep.batch:id,work_order_id,lot_number,quantity_produced',
                'batchStep.batch.workOrder:id,product_type_id,lot_number,status',
                'batchStep.batch.workOrder.productType:id,name,code',
                'sublot:id,parent_lot_id,sublot_number',
                'recordedBy:id,name',
            ])
            ->orderByDesc('consumed_at')
            ->get();

        return response()->json([
            'data' => [
                'lot' => $materialLot->only(['id', 'lot_number', 'material_id', 'status']),
                'consumptions' => $consumptions,
                'total_consumed' => (float) $consumptions->sum('quantity_consumed'),
                'consumed_in_steps' => $consumptions->pluck('batch_step_id')->unique()->values(),
            ],
        ]);
    }

    /**
     * Backward genealogy: what fed into this lot?
     *
     * Two answers depending on lot type:
     *   - Inbound raw lot: returns the inspection + supplier reference (terminal).
     *   - Semi-finished lot tied to a batch (extra_data.source_batch_id): returns
     *     all lots consumed by that source batch.
     *
     * NB: we don't yet model batch-output lots formally, so this endpoint
     * returns terminal data for raw lots and reads the optional
     * `extra_data.source_batch_id` hint to support hand-rolled chains.
     */
    public function backwardGenealogy(MaterialLot $materialLot): JsonResponse
    {
        $sourceBatchId = data_get($materialLot->extra_data, 'source_batch_id');
        $upstream = collect();

        if ($sourceBatchId) {
            $upstream = BatchStepLotConsumption::query()
                ->whereHas('batchStep', fn ($q) => $q->where('batch_id', $sourceBatchId))
                ->with([
                    'materialLot:id,lot_number,material_id,status',
                    'materialLot.material:id,name,code',
                    'batchStep:id,batch_id,name,step_number',
                ])
                ->orderByDesc('consumed_at')
                ->get();
        }

        return response()->json([
            'data' => [
                'lot' => $materialLot->only(['id', 'lot_number', 'material_id', 'status']),
                'inspection' => $materialLot->inspection,
                'supplier_lot_no' => $materialLot->supplier_lot_no,
                'supplier_reference' => $materialLot->supplier_reference,
                'source_batch_id' => $sourceBatchId,
                'upstream_consumptions' => $upstream,
            ],
        ]);
    }

    /**
     * Record a single consumption event against a batch step.
     *
     * Wrapped in a DB transaction so that genealogy insertion and the lot's
     * own decrement-and-status update happen atomically — partial writes would
     * leave the lot/qty out of sync with the audit trail.
     */
    public function consume(Request $request, MaterialLot $materialLot): JsonResponse
    {
        $data = $request->validate([
            'batch_step_id' => ['required', 'integer', 'exists:batch_steps,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'sublot_id' => ['nullable', 'integer', Rule::exists('material_sublots', 'id')
                ->where('parent_lot_id', $materialLot->id)],
        ]);

        if (! $materialLot->isAvailable()) {
            return response()->json([
                'message' => __('Lot is not available for consumption (status=:status).', ['status' => $materialLot->status]),
            ], 422);
        }

        $quantity = (float) $data['quantity'];

        try {
            $consumption = DB::transaction(function () use ($materialLot, $data, $quantity, $request) {
                // consume() throws on overflow — bubble up as 422.
                $materialLot->consume($quantity);

                $row = BatchStepLotConsumption::create([
                    'batch_step_id' => $data['batch_step_id'],
                    'material_lot_id' => $materialLot->id,
                    'sublot_id' => $data['sublot_id'] ?? null,
                    'quantity_consumed' => $quantity,
                    'consumed_at' => now(),
                    'recorded_by_id' => $request->user()?->id,
                ]);

                if (! empty($data['sublot_id'])) {
                    MaterialSublot::whereKey($data['sublot_id'])
                        ->update(['status' => MaterialSublot::STATUS_CONSUMED]);
                }

                return $row;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('Consumption recorded'),
            'data' => [
                'consumption' => $consumption->load(['materialLot', 'batchStep']),
                'lot' => $materialLot->fresh(['material']),
            ],
        ], 201);
    }
}
