<?php

namespace App\Services\Packaging;

use App\Enums\PalletStatus;
use App\Models\BatchStep;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\LotPickingService;
use App\Services\Material\MaterialAllocationService;
use App\Services\Traceability\BindingException;
use App\Services\Traceability\SerialTraceService;
use App\Services\WorkOrder\BatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serialised units after the line: into a carton, onto a pallet, out of the
 * door. Every move is a row in the unit's history, so the trace reads
 * "packed in CTN-000012 at 10:41, pallet PAL-000034, shipped 26.09".
 */
class UnitPackingService
{
    /** @var array<int, array<string, mixed>> packing config per work order, for the request */
    private array $configCache = [];

    public function __construct(
        private readonly SerialTraceService $serials,
        private readonly BatchService $batches,
        private readonly MaterialAllocationService $allocations,
        private readonly LotPickingService $lotPicking,
    ) {}

    /** How many units a carton of this order holds (the packing step's size), or null when unlimited. */
    public function cartonCapacityFor(?int $workOrderId): ?int
    {
        $capacity = (int) ($this->packingConfigFor($workOrderId)['carton_capacity'] ?? 0);

        return $capacity > 0 ? $capacity : null;
    }

    /**
     * The packing step an order is on right now: running, else the one ready to
     * run. With the unit's batch known, that batch's step - an order packed in
     * several batches counts each unit on its own batch.
     */
    public function packingStepFor(?int $workOrderId, ?int $batchId = null): ?BatchStep
    {
        if (! $workOrderId) {
            return null;
        }
        if ($batchId) {
            $step = BatchStep::query()
                ->where('kind', TemplateStep::KIND_PACKING)
                ->whereIn('status', [BatchStep::STATUS_READY, BatchStep::STATUS_IN_PROGRESS])
                ->where('batch_id', $batchId)
                ->orderByRaw("CASE WHEN status = 'IN_PROGRESS' THEN 0 ELSE 1 END")
                ->orderBy('step_number')
                ->first();
            if ($step) {
                return $step;
            }
        }

        return BatchStep::query()
            ->where('kind', TemplateStep::KIND_PACKING)
            ->whereIn('status', [BatchStep::STATUS_READY, BatchStep::STATUS_IN_PROGRESS])
            ->whereHas('batch', fn ($q) => $q->where('work_order_id', $workOrderId))
            ->orderByRaw("CASE WHEN status = 'IN_PROGRESS' THEN 0 ELSE 1 END")
            ->orderBy('step_number')
            ->first();
    }

    /**
     * The packaging the routing's BOM puts on this packing step - cartons,
     * pallets, foil - with what the batch needs of each, what was reserved and
     * from which lots, and what has been used so far. `needs_pick` says the
     * station has to name the lot before packing starts (lot tracking on).
     *
     * @return array<int, array<string, mixed>>
     */
    public function packingMaterials(BatchStep $step): array
    {
        $batch = $step->batch;
        // The snapshot is read afresh: the station's step list loads the order
        // with a handful of columns, and a partial relation has no snapshot.
        $bom = $batch ? (WorkOrder::find($batch->work_order_id)?->process_snapshot['bom'] ?? []) : [];
        $lotTracking = $this->lotPicking->isLotTrackingEnabled();

        $out = [];
        foreach ($bom as $line) {
            if ((int) ($line['step_number'] ?? 0) !== (int) $step->step_number || empty($line['material_id'])) {
                continue;
            }
            $material = Material::find($line['material_id']);
            if (! $material) {
                continue;
            }
            $required = round((float) $line['quantity_per_unit'] * (float) $batch->target_qty * (1 + (float) ($line['scrap_percentage'] ?? 0) / 100), 4);
            $allocation = MaterialAllocation::where('batch_id', $batch->id)->where('material_id', $material->id)
                ->with('lotPicks.lot:id,lot_number')->first();

            $out[] = [
                'material_id' => $material->id,
                'code' => $line['material_code'] ?? $material->code,
                'name' => $line['material_name'] ?? $material->name,
                'unit_of_measure' => $line['unit_of_measure'] ?? $material->unit_of_measure,
                'per' => $line['per'] ?? BomItem::PER_UNIT,
                'quantity_per_basis' => (float) ($line['quantity_per_basis'] ?? $line['quantity_per_unit']),
                'required_qty' => $required,
                'status' => $allocation?->status ?? 'pending',
                'allocated_qty' => (float) ($allocation?->allocated_qty ?? 0),
                'consumed_qty' => (float) ($allocation?->consumed_qty ?? 0),
                'lots' => $allocation ? $allocation->lotPicks->map(fn ($pick) => ['lot_number' => $pick->lot?->lot_number, 'qty' => (float) $pick->picked_qty])->values()->all() : [],
                'needs_pick' => $lotTracking && ! $allocation && $material->tracking_type !== 'serial' && $step->status === BatchStep::STATUS_READY,
            ];
        }

        return $out;
    }

    /** Why a scan must wait: the packaging lots have not been picked yet. Null when packing may go on. */
    public function packingMaterialsGate(BatchStep $step): ?string
    {
        if ($step->status !== BatchStep::STATUS_READY) {
            return null;
        }
        $waiting = collect($this->packingMaterials($step))->filter(fn ($m) => $m['needs_pick'])->pluck('name');
        if ($waiting->isEmpty()) {
            return null;
        }

        return __('Pick the packaging lots first (:materials), then scan.', ['materials' => $waiting->implode(', ')]);
    }

    /**
     * Start the packing step from the station with the lots the operator
     * picked - the same start the operator queue does, so reservations and
     * genealogy land the same way.
     *
     * @param  array<int|string, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picks
     *
     * @throws BindingException
     */
    public function startPackingStep(BatchStep $step, User $operator, array $picks = []): BatchStep
    {
        if ($step->status !== BatchStep::STATUS_READY) {
            throw new BindingException(__('Packing step :name is not ready to start.', ['name' => $step->name]));
        }
        $byMaterial = [];
        foreach ($picks as $materialId => $rows) {
            $byMaterial[(int) $materialId] = $rows;
        }
        try {
            return $this->batches->startStep($step, $operator, $byMaterial);
        } catch (\Throwable $e) {
            throw new BindingException($e->getMessage());
        }
    }

    /**
     * A carton or pallet closed: its packaging (the BOM lines counted per
     * carton / per pallet) is used up, one more each. Booked on the reservation
     * so the station's "used" count is live; the batch's completion settles the
     * rest as it always did. Never in the way of the closing itself.
     */
    private function consumePackaging(?int $workOrderId, string $basis): void
    {
        $step = $this->packingStepFor($workOrderId);
        if (! $step || $step->status !== BatchStep::STATUS_IN_PROGRESS) {
            return;
        }
        try {
            foreach ($this->packingMaterials($step) as $line) {
                if ($line['per'] !== $basis || $line['status'] !== MaterialAllocation::STATUS_ALLOCATED) {
                    continue;
                }
                $allocation = MaterialAllocation::where('batch_id', $step->batch_id)->where('material_id', $line['material_id'])->first();
                if ($allocation) {
                    $this->allocations->recordConsumption($allocation, min((float) $allocation->allocated_qty, $line['consumed_qty'] + $line['quantity_per_basis']), (float) $allocation->scrap_qty);
                }
            }
        } catch (\Throwable $e) {
            Log::info('Packaging consumption not booked: '.$e->getMessage(), ['batch_step_id' => $step->id, 'basis' => $basis]);
        }
    }

    /** The pallet is done (closed by hand or by reaching its size): book its packaging. */
    public function palletClosed(Pallet $pallet): void
    {
        $this->consumePackaging($pallet->work_order_id, BomItem::PER_PALLET);
    }

    /**
     * The packing step's configuration that governs an order: the frozen batch
     * step first, the order's snapshot when no batch exists yet, nothing when
     * the routing has no packing step (then the station's defaults apply).
     *
     * @return array<string, mixed>
     */
    public function packingConfigFor(?int $workOrderId): array
    {
        if (! $workOrderId) {
            return [];
        }
        if (array_key_exists($workOrderId, $this->configCache)) {
            return $this->configCache[$workOrderId];
        }

        $step = BatchStep::query()
            ->where('kind', TemplateStep::KIND_PACKING)
            ->whereHas('batch', fn ($q) => $q->where('work_order_id', $workOrderId))
            ->orderBy('step_number')
            ->first();
        $config = $step?->config;
        if ($config === null) {
            foreach (WorkOrder::find($workOrderId)?->process_snapshot['steps'] ?? [] as $snapshotStep) {
                if (($snapshotStep['kind'] ?? null) === TemplateStep::KIND_PACKING) {
                    $config = $snapshotStep['config'] ?? [];
                    break;
                }
            }
        }

        return $this->configCache[$workOrderId] = $config ?? [];
    }

    /**
     * The label template the packing step names, for the container level it
     * packs into ('unit', 'carton' or 'pallet'); null means the type's default.
     */
    public function labelTemplateIdFor(?int $workOrderId, string $level): ?int
    {
        $config = $this->packingConfigFor($workOrderId);
        if (($config['unit'] ?? 'carton') !== $level || empty($config['label_template_id'])) {
            return null;
        }

        return (int) $config['label_template_id'];
    }

    /**
     * A pallet that reached the packing step's size closes itself, the way a
     * full carton does: counting closed cartons when the step packs into
     * cartons, units when it packs straight onto pallets. Returns whether it
     * closed just now, so the station can print its label and drop the bench.
     */
    public function closePalletIfFull(?Pallet $pallet): bool
    {
        if (! $pallet || ! $pallet->isOpen()) {
            return false;
        }
        $config = $this->packingConfigFor($pallet->work_order_id);
        $capacity = (int) ($config['pallet_capacity'] ?? 0);
        if ($capacity < 1) {
            return false;
        }
        $filled = ($config['unit'] ?? 'carton') === 'pallet'
            ? $pallet->units()->count()
            : $pallet->cartons()->where('status', UnitCarton::STATUS_CLOSED)->count();
        if ($filled < $capacity) {
            return false;
        }

        // Only the request that actually closes it books the pallet's packaging.
        $closed = Pallet::whereKey($pallet->id)->where('status', PalletStatus::Open->value)
            ->update(['status' => PalletStatus::Closed->value, 'active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        if ($closed === 0) {
            return false;
        }
        $pallet->refresh();
        $this->palletClosed($pallet);

        return true;
    }

    /**
     * A unit leaves production here. With a carton it goes into the box (and
     * onto the box's pallet, if it already has one); with a pallet alone it goes
     * straight onto the pallet; with neither it is just marked packed.
     *
     * @throws BindingException
     */
    /** @param  ?int  $workstationId  the bench the operator picked for this session; their account's bench otherwise */
    /**
     * A unit the line has taken off (scrapped, held, already shipped) is not
     * packed - checked before anything else happens at the bench (the scale).
     *
     * @throws BindingException
     */
    public function assertPackable(SerialUnit $unit): void
    {
        if (in_array($unit->status, [...SerialUnit::TERMINAL_STATUSES, SerialUnit::STATUS_BLOCKED], true)) {
            throw new BindingException(__('Unit :sn is :status and cannot be packed.', ['sn' => $unit->display_id, 'status' => $unit->status_label]));
        }
    }

    /** @param  array<string, mixed>  $measured  what was measured at packing (the weight), kept on the "packed" event */
    public function packUnit(SerialUnit $unit, User $operator, ?UnitCarton $carton = null, ?Pallet $pallet = null, ?int $workstationId = null, array $measured = []): SerialUnit
    {
        [$unit, $firstPacking] = DB::transaction(function () use ($unit, $operator, $carton, $pallet, $workstationId, $measured) {
            // Read again under lock: two benches, or a scan racing the box's close,
            // must not both see room in the same carton or an open pallet.
            $unit = SerialUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $carton = $carton ? UnitCarton::whereKey($carton->id)->lockForUpdate()->firstOrFail() : null;
            $pallet = $pallet ? Pallet::whereKey($pallet->id)->lockForUpdate()->firstOrFail() : null;

            $this->assertPackable($unit);
            if ($carton && ! $carton->isOpen()) {
                throw new BindingException(__('Carton :no is closed.', ['no' => $carton->carton_no]));
            }
            if ($unit->carton_id && $carton && $unit->carton_id !== $carton->id) {
                throw new BindingException(__('Unit :sn is already in carton :no.', ['sn' => $unit->display_id, 'no' => $unit->carton?->carton_no]));
            }
            // The box is full at the packing step's size: the server holds that
            // line, whatever the station's auto-close managed to do.
            $capacity = $this->cartonCapacityFor($unit->work_order_id);
            if ($carton && $unit->carton_id !== $carton->id && $capacity && $carton->qty >= $capacity) {
                throw new BindingException(__('Carton :no is full (:count units).', ['no' => $carton->carton_no, 'count' => $capacity]));
            }

            // A box already on a pallet takes the unit there; another pallet would
            // split the box. A box with no pallet yet goes onto this one first.
            if ($carton && $pallet && $carton->pallet_id && $carton->pallet_id !== $pallet->id) {
                throw new BindingException(__('Carton :no is already on pallet :pallet.', ['no' => $carton->carton_no, 'pallet' => $carton->pallet?->pallet_no]));
            }
            if ($carton && $pallet && ! $carton->pallet_id) {
                $carton = $this->assignCartonToPallet($carton, $pallet, $operator, $workstationId);
                $unit->refresh();
            }

            $pallet ??= $carton?->pallet;
            // A closed or shipped pallet takes nothing more, not even through a box still on it.
            if ($pallet && ! $pallet->isOpen()) {
                throw new BindingException(__('Pallet :no is not open.', ['no' => $pallet->pallet_no]));
            }
            // A box or pallet belongs to an order; a unit from another order does not go in it.
            foreach ([$carton?->carton_no => $carton, $pallet?->pallet_no => $pallet] as $no => $container) {
                if ($container && $unit->work_order_id && $container->work_order_id && $container->work_order_id !== $unit->work_order_id) {
                    throw new BindingException(__('Unit :sn belongs to another work order than :no.', ['sn' => $unit->display_id, 'no' => $no]));
                }
            }
            // A unit already on a pallet stays there; moving it is a supervisor's job, not a rescan.
            if ($pallet && $unit->pallet_id && $unit->pallet_id !== $pallet->id) {
                throw new BindingException(__('Unit :sn is already on pallet :no.', ['sn' => $unit->display_id, 'no' => $unit->pallet?->pallet_no]));
            }
            $alreadyInCarton = $carton && $unit->carton_id === $carton->id;
            $newOnPallet = $pallet && $unit->pallet_id !== $pallet->id;
            $firstPacking = $unit->packed_at === null;
            // The same label read twice (a wedge scanner, a nervous hand) changes
            // nothing and writes nothing: the history keeps one "packed" per move.
            if (! $firstPacking && ($alreadyInCarton || (! $carton && ! $newOnPallet))) {
                return [$unit, false];
            }

            $unit->fill([
                'carton_id' => $carton?->id ?? $unit->carton_id,
                'pallet_id' => $pallet?->id ?? $unit->pallet_id,
                'packed_at' => $unit->packed_at ?? now(),
                'status' => $unit->status === SerialUnit::STATUS_IN_PRODUCTION ? SerialUnit::STATUS_COMPLETED : $unit->status,
            ])->save();

            if ($carton && ! $alreadyInCarton) {
                $carton->increment('qty');
            }
            // "Pieces on pallet" counts serialised units too, whether they land
            // there directly or through a carton that is already on it.
            if ($newOnPallet) {
                $pallet->increment('qty');
                $pallet->recomputeQualityStatus();
            }

            $this->serials->recordStep($unit, $operator, null, [
                'workstation_id' => $workstationId ?? $operator->workstation_id,
                'parameters' => array_filter([
                    'event' => 'packed',
                    'psn' => $unit->psn,
                    'carton_no' => $carton?->carton_no,
                    'pallet_no' => $pallet?->pallet_no,
                    ...$measured,
                ], fn ($v) => $v !== null && $v !== ''),
            ]);

            return [$unit->fresh(), $firstPacking];
        });

        // One unit counts once on the packing step, however often its label is
        // rescanned - and only once the packing itself is safely committed.
        if ($firstPacking) {
            $this->countOnPackingStep($unit, $operator);
        }

        return $unit;
    }

    /**
     * The routing's packing step, if the order has one, gets the piece: that
     * is what makes packing a step with a station, a duration and a count like
     * any other, instead of activity beside the routing. A READY step is
     * started by the first piece. Nothing here may stop the packing itself -
     * a step the flow rules refuse (not reached yet, a required control still
     * open, production not yet available) is logged and skipped, whatever the
     * rule throws.
     */
    private function countOnPackingStep(SerialUnit $unit, User $operator): void
    {
        $step = $this->packingStepFor($unit->work_order_id, $unit->batch_id);
        if (! $step) {
            return;
        }

        try {
            if ($step->status === BatchStep::STATUS_READY) {
                $step = $this->batches->startStep($step, $operator);
            }
            $this->batches->recordQuantity($step, $operator, 1, 0);
        } catch (\Throwable $e) {
            Log::info('Packing step not counted: '.$e->getMessage(), ['batch_step_id' => $step->id, 'serial_unit_id' => $unit->id]);
        }
    }

    /**
     * The packing steps a station works on right now: every order with a
     * packing step that is ready or running, with the step's configuration.
     * With a workstation selected only steps bound to it (or to none) show.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openPackingSteps(?int $workstationId = null): array
    {
        return BatchStep::query()
            ->where('kind', TemplateStep::KIND_PACKING)
            ->whereIn('status', [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY, BatchStep::STATUS_IN_PROGRESS])
            ->when($workstationId, fn ($q) => $q->where(fn ($w) => $w->whereNull('workstation_id')->orWhere('workstation_id', $workstationId)))
            ->with(['batch:id,work_order_id,batch_number,target_qty', 'batch.workOrder:id,order_no,product_type_id,planned_qty', 'batch.workOrder.productType:id,name', 'workstation:id,name'])
            ->whereHas('batch.workOrder', fn ($q) => $q->packable())
            ->orderBy('status')->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (BatchStep $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status,
                'step_number' => $s->step_number,
                'config' => $s->config ?? [],
                'workstation' => $s->workstation?->name,
                'batch_number' => $s->batch?->batch_number,
                'target_qty' => (float) ($s->batch?->target_qty ?? 0),
                'passed_qty' => (float) $s->passed_qty,
                'work_order_id' => $s->batch?->work_order_id,
                'order_no' => $s->batch?->workOrder?->order_no,
                'product' => $s->batch?->workOrder?->productType?->name,
                'materials' => $this->packingMaterials($s),
            ])
            ->values()
            ->all();
    }

    /** @throws BindingException when the pallet is closed or belongs to another order */
    public function openCarton(User $operator, ?WorkOrder $workOrder = null, ?Pallet $pallet = null, ?int $workstationId = null): UnitCarton
    {
        if ($pallet && ! $pallet->isOpen()) {
            throw new BindingException(__('Pallet :no is not open.', ['no' => $pallet->pallet_no]));
        }
        if ($pallet && $workOrder && $pallet->work_order_id && $pallet->work_order_id !== $workOrder->id) {
            throw new BindingException(__('Pallet :no belongs to another work order.', ['no' => $pallet->pallet_no]));
        }
        UnitCarton::where('active_by_id', $operator->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);

        return UnitCarton::create([
            'work_order_id' => $workOrder?->id ?? $pallet?->work_order_id,
            'pallet_id' => $pallet?->id,
            'status' => UnitCarton::STATUS_OPEN,
            'created_by_id' => $operator->id,
            'active_by_id' => $operator->id,
            'active_workstation_id' => $workstationId ?? $operator->workstation_id,
            'activated_at' => now(),
        ]);
    }

    /** @throws BindingException on an empty carton - a box with nothing in it has no label to print */
    public function closeCarton(UnitCarton $carton, User $operator): UnitCarton
    {
        return DB::transaction(function () use ($carton, $operator) {
            // Under lock: the station's auto-close and a click on "Close" may land
            // together, and the box's packaging is booked once.
            $carton = UnitCarton::whereKey($carton->id)->lockForUpdate()->firstOrFail();
            if (! $carton->isOpen()) {
                return $carton;
            }
            if ($carton->units()->count() === 0) {
                throw new BindingException(__('Carton :no is empty.', ['no' => $carton->carton_no]));
            }
            $carton->update(['status' => UnitCarton::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_id' => $operator->id, 'active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
            $this->consumePackaging($carton->work_order_id, BomItem::PER_CARTON);

            return $carton->fresh();
        });
    }

    /**
     * Put a carton (and every unit in it) on a pallet. The pallet's piece count
     * grows by the carton's, so "pieces on pallet" stays true for serialised
     * goods that never went through the EAN scan.
     */
    public function assignCartonToPallet(UnitCarton $carton, Pallet $pallet, User $operator, ?int $workstationId = null): UnitCarton
    {
        return DB::transaction(function () use ($carton, $pallet, $operator, $workstationId) {
            if (! $pallet->isOpen()) {
                throw new BindingException(__('Pallet :no is not open.', ['no' => $pallet->pallet_no]));
            }
            if ($carton->pallet_id === $pallet->id) {
                return $carton;
            }
            if ($carton->pallet_id !== null) {
                throw new BindingException(__('Carton :no is already on pallet :pallet.', ['no' => $carton->carton_no, 'pallet' => $carton->pallet?->pallet_no]));
            }
            // A pallet belongs to an order, like a carton does: the two must agree.
            if ($carton->work_order_id && $pallet->work_order_id && $carton->work_order_id !== $pallet->work_order_id) {
                throw new BindingException(__('Carton :no belongs to another order than pallet :pallet.', ['no' => $carton->carton_no, 'pallet' => $pallet->pallet_no]));
            }
            // A unit already counted on another pallet does not move silently.
            $elsewhere = $carton->units()->whereNotNull('pallet_id')->where('pallet_id', '!=', $pallet->id)->first();
            if ($elsewhere) {
                throw new BindingException(__('Unit :sn is already on pallet :no.', ['sn' => $elsewhere->display_id, 'no' => $elsewhere->pallet?->pallet_no]));
            }

            $carton->update(['pallet_id' => $pallet->id]);
            // Only the units not yet on this pallet count and get a history row:
            // one may have been scanned straight onto it before the box was placed.
            $arriving = $carton->units()->where(fn ($q) => $q->whereNull('pallet_id')->orWhere('pallet_id', '!=', $pallet->id))->get();
            if ($arriving->isNotEmpty()) {
                $pallet->increment('qty', $arriving->count());
            }

            foreach ($arriving as $unit) {
                $unit->update(['pallet_id' => $pallet->id]);
                $this->serials->recordStep($unit, $operator, null, [
                    'workstation_id' => $workstationId ?? $operator->workstation_id,
                    'parameters' => ['event' => 'palletised', 'carton_no' => $carton->carton_no, 'pallet_no' => $pallet->pallet_no],
                ]);
            }
            // Only now are the carton's units on the pallet: its quality is theirs.
            $pallet->recomputeQualityStatus();

            return $carton->fresh();
        });
    }

    /**
     * The pallet left: every unit on it is shipped, scrap excepted. Called from
     * the pallet's own status transition so it holds whichever screen ships it.
     */
    public function markPalletShipped(Pallet $pallet, ?User $actor = null): int
    {
        // A held unit does not leave with the pallet unnoticed: it keeps its hold.
        $units = SerialUnit::where('pallet_id', $pallet->id)
            ->whereNotIn('status', [SerialUnit::STATUS_SCRAPPED, SerialUnit::STATUS_SHIPPED, SerialUnit::STATUS_BLOCKED])
            ->get();

        foreach ($units as $unit) {
            $unit->update(['status' => SerialUnit::STATUS_SHIPPED, 'shipped_at' => $pallet->shipped_at ?? now()]);
            if ($actor) {
                $this->serials->recordStep($unit, $actor, null, [
                    'parameters' => ['event' => 'shipped', 'pallet_no' => $pallet->pallet_no],
                ]);
            }
        }

        return $units->count();
    }
}
