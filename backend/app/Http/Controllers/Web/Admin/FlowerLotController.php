<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\FlowerLot;
use App\Models\FlowerLotAllocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Flower lot time-window station.
 *
 * The page treats the cutoff as the product: today's arrivals are ordered by
 * remaining usable time, and a lot past `usable_until` can only be scrapped or
 * downgraded. Allocation is kept here rather than hidden in a batch form so
 * the remaining window and remaining weight are checked at the same point.
 */
class FlowerLotController extends Controller
{
    public function index()
    {
        $lots = FlowerLot::query()
            ->with(['allocations'])
            ->whereDate('arrived_at', today())
            ->orderBy('usable_until')
            ->orderBy('id')
            ->limit(12)
            ->get();

        $summary = [
            'total' => $lots->count(),
            'available' => $lots->filter(fn (FlowerLot $lot) => $lot->effectiveStatus() === FlowerLot::STATUS_AVAILABLE)->count(),
            'curing' => $lots->filter(fn (FlowerLot $lot) => in_array($lot->effectiveStatus(), [FlowerLot::STATUS_ARRIVED, FlowerLot::STATUS_CURING], true))->count(),
            'expired' => $lots->filter(fn (FlowerLot $lot) => $lot->effectiveStatus() === FlowerLot::STATUS_EXPIRED)->count(),
            'allocated' => $lots->filter(fn (FlowerLot $lot) => $lot->allocations->isNotEmpty())->count(),
            'disposed' => $lots->filter(fn (FlowerLot $lot) => in_array($lot->effectiveStatus(), [FlowerLot::STATUS_SCRAPPED, FlowerLot::STATUS_DOWNGRADED], true))->count(),
        ];

        return Inertia::render('admin/flower-lots/Index', [
            'lots' => $lots->map(fn (FlowerLot $lot) => $this->lotRow($lot))->all(),
            'summary' => $summary,
        ]);
    }

    public function show(FlowerLot $flowerLot)
    {
        $flowerLot->load([
            'allocations.batch.workOrder.productType',
            'allocations.batch.workOrder.customer',
            'allocations.allocatedBy',
            'disposedBy',
        ]);

        $batches = Batch::query()
            ->with(['workOrder.productType', 'workOrder.customer'])
            ->whereIn('status', [Batch::STATUS_DONE, Batch::STATUS_IN_PROGRESS])
            ->orderBy('batch_number')
            ->get()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'lot_number' => $batch->lot_number,
                'product' => $batch->workOrder?->productType?->name,
                'customer' => $batch->workOrder?->customer?->name ?? $batch->workOrder?->customer_name,
                'planned_qty' => (float) $batch->target_qty,
                'unit_of_measure' => $batch->workOrder?->productType?->unit_of_measure ?? 'kg',
                'status' => $batch->status,
            ])
            ->all();

        $effectiveStatus = $flowerLot->effectiveStatus();
        $allocatedWeight = (float) $flowerLot->allocations->sum('weight_kg');
        $remainingWeight = in_array($effectiveStatus, [FlowerLot::STATUS_EXPIRED, FlowerLot::STATUS_SCRAPPED, FlowerLot::STATUS_DOWNGRADED], true)
            ? 0.0
            : max(0, (float) $flowerLot->weight_kg - $allocatedWeight);
        $remainingSeconds = $flowerLot->remainingSeconds();
        $hasAllocation = $flowerLot->allocations->isNotEmpty();

        return Inertia::render('admin/flower-lots/Show', [
            'flowerLot' => [
                'id' => $flowerLot->id,
                'code' => $flowerLot->code,
                'variety' => $flowerLot->variety,
                'origin' => $flowerLot->origin,
                'supplier_name' => $flowerLot->supplier_name,
                'picked_at' => $flowerLot->picked_at?->toIso8601String(),
                'arrived_at' => $flowerLot->arrived_at?->toIso8601String(),
                'usable_from' => $flowerLot->usable_from?->toIso8601String(),
                'usable_until' => $flowerLot->usable_until?->toIso8601String(),
                'weight_kg' => (float) $flowerLot->weight_kg,
                'opening_rate' => $flowerLot->opening_rate !== null ? (float) $flowerLot->opening_rate : null,
                'curing_temperature' => $flowerLot->curing_temperature !== null ? (float) $flowerLot->curing_temperature : null,
                'storage_location' => $flowerLot->storage_location,
                'status' => $effectiveStatus,
                'stored_status' => $flowerLot->status,
                'status_label' => $flowerLot->statusLabel(),
                'urgency' => $flowerLot->urgency(),
                'remaining_seconds' => $remainingSeconds,
                'notes' => $flowerLot->notes,
                'disposition_reason' => $flowerLot->disposition_reason,
                'disposed_at' => $flowerLot->disposed_at?->toIso8601String(),
                'disposed_by' => $flowerLot->disposedBy?->name,
                'allocated_weight' => $allocatedWeight,
                'remaining_weight' => $remainingWeight,
                'has_allocation' => $hasAllocation,
                'can_mark_available' => in_array($effectiveStatus, [FlowerLot::STATUS_ARRIVED, FlowerLot::STATUS_CURING], true),
                'can_allocate' => $effectiveStatus === FlowerLot::STATUS_AVAILABLE && $remainingWeight > 0,
                'can_dispose' => ! in_array($effectiveStatus, [FlowerLot::STATUS_SCRAPPED, FlowerLot::STATUS_DOWNGRADED], true),
                'workflow' => [
                    ['key' => 'arrive', 'label' => '到厂', 'done' => true, 'at' => $flowerLot->arrived_at?->toIso8601String()],
                    ['key' => 'cure', 'label' => '养护', 'done' => ! in_array($effectiveStatus, [FlowerLot::STATUS_ARRIVED, FlowerLot::STATUS_CURING], true), 'at' => null],
                    ['key' => 'ready', 'label' => '可用', 'done' => in_array($effectiveStatus, [FlowerLot::STATUS_AVAILABLE, FlowerLot::STATUS_ALLOCATED, FlowerLot::STATUS_EXPIRED], true), 'at' => $flowerLot->usable_from?->toIso8601String()],
                    ['key' => 'allocate', 'label' => '付窨', 'done' => $hasAllocation, 'at' => $flowerLot->allocations->first()?->allocated_at?->toIso8601String()],
                ],
            ],
            'allocations' => $flowerLot->allocations
                ->map(fn (FlowerLotAllocation $allocation) => [
                    'id' => $allocation->id,
                    'weight_kg' => (float) $allocation->weight_kg,
                    'seep_count' => $allocation->seep_count,
                    'allocated_at' => $allocation->allocated_at?->toIso8601String(),
                    'allocated_by' => $allocation->allocatedBy?->name,
                    'batch' => $allocation->batch ? [
                        'id' => $allocation->batch->id,
                        'batch_number' => $allocation->batch->batch_number,
                        'lot_number' => $allocation->batch->lot_number,
                        'product' => $allocation->batch->workOrder?->productType?->name,
                        'customer' => $allocation->batch->workOrder?->customer?->name ?? $allocation->batch->workOrder?->customer_name,
                    ] : null,
                ])
                ->sortByDesc('allocated_at')
                ->values()
                ->all(),
            'batches' => $batches,
        ]);
    }

    public function markAvailable(Request $request, FlowerLot $flowerLot)
    {
        $data = $request->validate([
            'curing_temperature' => ['required', 'numeric', 'between:0,50'],
            'opening_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        $effectiveStatus = $flowerLot->effectiveStatus();

        if ($effectiveStatus === FlowerLot::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'opening_rate' => '这个批次已经超过可用截止，只能报废或降级，不能标记可用。',
            ]);
        }

        if (! in_array($effectiveStatus, [FlowerLot::STATUS_ARRIVED, FlowerLot::STATUS_CURING], true)) {
            throw ValidationException::withMessages([
                'opening_rate' => '只有到厂或养护中的鲜花批次可以标记可用。',
            ]);
        }

        $flowerLot->update([
            'curing_temperature' => $data['curing_temperature'],
            'opening_rate' => $data['opening_rate'],
            'usable_from' => now(),
            'status' => FlowerLot::STATUS_AVAILABLE,
        ]);

        return back()->with('success', "鲜花批次 {$flowerLot->code} 已标记可用。");
    }

    public function allocate(Request $request, FlowerLot $flowerLot)
    {
        $data = $request->validate([
            'batch_id' => ['required', 'integer', Rule::exists('batches', 'id')],
            'weight_kg' => ['required', 'numeric', 'min:0.1'],
            'seep_count' => ['required', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($flowerLot->effectiveStatus() !== FlowerLot::STATUS_AVAILABLE) {
            throw ValidationException::withMessages([
                'weight_kg' => '只有可用状态的鲜花批次可以付窨。',
            ]);
        }

        if ($flowerLot->remainingSeconds() <= 0 || $flowerLot->usable_until->isPast()) {
            throw ValidationException::withMessages([
                'weight_kg' => '可用窗口已结束，不能付窨；只能报废或降级。',
            ]);
        }

        if (FlowerLotAllocation::query()
            ->where('flower_lot_id', $flowerLot->id)
            ->where('batch_id', $data['batch_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'batch_id' => '这个批次已经付窨过该鲜花批，不能重复付窨。',
            ]);
        }

        $remainingWeight = $flowerLot->remainingWeight();
        if ((float) $data['weight_kg'] > $remainingWeight + 0.001) {
            throw ValidationException::withMessages([
                'weight_kg' => "本次最多还能付窨 {$remainingWeight} kg。",
            ]);
        }

        $allocation = FlowerLotAllocation::create([
            'flower_lot_id' => $flowerLot->id,
            'batch_id' => $data['batch_id'],
            'weight_kg' => $data['weight_kg'],
            'seep_count' => $data['seep_count'],
            'allocated_by_id' => $request->user()?->id,
            'allocated_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($flowerLot->remainingWeight() <= 0.001) {
            $flowerLot->update(['status' => FlowerLot::STATUS_ALLOCATED]);
        }

        return back()->with('success', '鲜花批次 '.$flowerLot->code.' 已付窨 '.sprintf('%g', (float) $allocation->weight_kg).' kg。');
    }

    public function dispose(Request $request, FlowerLot $flowerLot)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['scrap', 'downgrade'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (in_array($flowerLot->effectiveStatus(), [FlowerLot::STATUS_SCRAPPED, FlowerLot::STATUS_DOWNGRADED], true)) {
            throw ValidationException::withMessages(['reason' => '这个批次已经处置过了。']);
        }

        $flowerLot->update([
            'status' => $data['action'] === 'scrap' ? FlowerLot::STATUS_SCRAPPED : FlowerLot::STATUS_DOWNGRADED,
            'disposition_reason' => $data['reason'],
            'disposed_at' => now(),
            'disposed_by_id' => $request->user()?->id,
        ]);

        return back()->with('success', "鲜花批次 {$flowerLot->code} 已".($data['action'] === 'scrap' ? '报废。' : '降级。'));
    }

    private function lotRow(FlowerLot $lot): array
    {
        $allocatedWeight = (float) $lot->allocations->sum('weight_kg');
        $effectiveStatus = $lot->effectiveStatus();
        $remainingWeight = in_array($effectiveStatus, [FlowerLot::STATUS_EXPIRED, FlowerLot::STATUS_SCRAPPED, FlowerLot::STATUS_DOWNGRADED], true)
            ? 0.0
            : max(0, (float) $lot->weight_kg - $allocatedWeight);

        return [
            'id' => $lot->id,
            'code' => $lot->code,
            'variety' => $lot->variety,
            'origin' => $lot->origin,
            'supplier_name' => $lot->supplier_name,
            'arrived_at' => $lot->arrived_at?->toIso8601String(),
            'usable_from' => $lot->usable_from?->toIso8601String(),
            'usable_until' => $lot->usable_until?->toIso8601String(),
            'weight_kg' => (float) $lot->weight_kg,
            'allocated_weight' => $allocatedWeight,
            'remaining_weight' => $remainingWeight,
            'opening_rate' => $lot->opening_rate !== null ? (float) $lot->opening_rate : null,
            'curing_temperature' => $lot->curing_temperature !== null ? (float) $lot->curing_temperature : null,
            'status' => $effectiveStatus,
            'status_label' => $lot->statusLabel(),
            'urgency' => $lot->urgency(),
            'remaining_seconds' => $lot->remainingSeconds(),
        ];
    }
}
