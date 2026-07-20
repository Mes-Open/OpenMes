<?php

namespace App\Services\WorkOrder;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    /**
     * Create a new work order with process snapshot.
     *
     * @throws \Exception
     */
    public function createWorkOrder(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            // Build the immutable process snapshot from the selected BOM(s).
            // With no explicit selection this resolves the single active template
            // for the product type - the legacy single-BOM behaviour.
            $processSnapshot = $this->buildProcessSnapshot(
                $data['product_type_id'] ?? null,
                $data['bom_template_ids'] ?? [],
            );

            // Create work order
            $workOrder = WorkOrder::create([
                'order_no' => $data['order_no'],
                'customer_order_no' => $data['customer_order_no'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'line_id' => $data['line_id'] ?? null,
                'product_type_id' => $data['product_type_id'] ?? null,
                'process_snapshot' => $processSnapshot,
                'planned_qty' => $data['planned_qty'],
                'unit_price' => $data['unit_price'] ?? null,
                'produced_qty' => 0,
                'counting_source' => $data['counting_source'] ?? WorkOrder::COUNTING_OPERATOR,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => $data['priority'] ?? 0,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'extra_data' => $data['extra_data'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
            ]);

            // Record which BOMs back the snapshot so the selection can be shown
            // and switched later. Nothing to record when the order has no template.
            if (! empty($processSnapshot['bom_template_ids'])) {
                $this->syncBomSelection($workOrder, $processSnapshot['bom_template_ids']);
            }

            return $workOrder;
        });
    }

    /**
     * Re-select the BOM(s) backing an existing work order and rebuild its
     * snapshot. An empty selection falls back to the single active template for
     * the product type. Blocked once production has started - the snapshot is
     * the frozen recipe batches were built against, so changing it mid-flight
     * would desync requirements from what was already allocated/consumed.
     *
     * @param  array<int, int|string>  $templateIds
     *
     * @throws \Exception
     */
    public function updateBomSelection(WorkOrder $workOrder, array $templateIds): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $templateIds) {
            // Serialize against createBatch() on this work order: hold the row lock
            // while checking for batches so a concurrent batch can't slip in between
            // the check and the snapshot rewrite (the snapshot is the frozen recipe
            // batches are built from).
            WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();

            if ($workOrder->batches()->exists()) {
                throw new \Exception('Cannot change BOMs after production has started.');
            }

            $snapshot = $this->buildProcessSnapshot($workOrder->product_type_id, $templateIds);

            $workOrder->update(['process_snapshot' => $snapshot]);
            $this->syncBomSelection($workOrder, $snapshot['bom_template_ids'] ?? []);

            return $workOrder->fresh();
        });
    }

    /**
     * Build the immutable process snapshot for a work order from its BOM
     * selection. The snapshot keeps the flat `bom` list every downstream
     * consumer (requirements, allocation, costing) already reads - but that list
     * is now the material-deduplicated UNION of all selected BOMs, so a material
     * appearing in several BOMs contributes its summed requirement exactly once.
     *
     * Process structure (steps, timing) comes from the FIRST selected BOM (the
     * "primary" template); the others contribute their materials only.
     *
     * @param  array<int, int|string>  $templateIds  Explicit BOM (process template) selection; empty = legacy auto-pick.
     * @return array<string, mixed>|null Null when no template applies (order without a BOM).
     */
    public function buildProcessSnapshot(?int $productTypeId, array $templateIds = []): ?array
    {
        $templates = $this->resolveBomTemplates($productTypeId, $templateIds);

        if ($templates->isEmpty()) {
            return null;
        }

        // Snapshot each selected template once, then stitch: structure from the
        // primary, merged materials from all of them.
        $snapshots = $templates->map(fn (ProcessTemplate $t) => $t->toSnapshot());

        $snapshot = $snapshots->first();
        $merged = $this->mergeBoms($snapshots->pluck('bom')->all());
        // Steps come from the primary BOM only, so a `during` item pulled in from a
        // secondary BOM may reference a step that isn't in this order's flow - it
        // would then never be allocated. Degrade such items to `start` so the
        // material is still consumed (single-BOM orders are unaffected: their
        // `during` items always reference an existing step).
        $stepNumbers = array_column($snapshot['steps'] ?? [], 'step_number');
        $snapshot['bom'] = $this->degradeOrphanDuringItems($merged, $stepNumbers);
        $snapshot['bom_template_ids'] = $templates->pluck('id')->values()->all();
        $snapshot['bom_templates'] = $templates->map(fn (ProcessTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'version' => $t->version,
        ])->values()->all();

        return $snapshot;
    }

    /**
     * Resolve the process templates backing an order, in selection order.
     * Explicit ids win (deduplicated, order preserved); otherwise fall back to
     * the single highest-version active template for the product type.
     *
     * @param  array<int, int|string>  $templateIds
     * @return EloquentCollection<int, ProcessTemplate>
     *
     * @throws \InvalidArgumentException When a selected id is unknown or belongs to another product type.
     */
    protected function resolveBomTemplates(?int $productTypeId, array $templateIds): EloquentCollection
    {
        $ids = $this->normalizeIds($templateIds);

        if (! empty($ids)) {
            $found = ProcessTemplate::whereIn('id', $ids)
                ->where('product_type_id', $productTypeId)
                ->get()
                ->keyBy('id');

            // Fail loud rather than silently dropping an id that doesn't exist or
            // belongs to another product type. HTTP requests are already validated,
            // but this is a public service boundary (non-HTTP callers, deletion races).
            if ($found->count() !== count($ids)) {
                throw new \InvalidArgumentException(
                    'Every selected BOM must exist and belong to the work order product type.'
                );
            }

            // Preserve the caller's selection order.
            return new EloquentCollection(
                collect($ids)->map(fn ($id) => $found->get($id))->values()->all(),
            );
        }

        if ($productTypeId === null) {
            return new EloquentCollection;
        }

        $active = ProcessTemplate::where('product_type_id', $productTypeId)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();

        return new EloquentCollection($active ? [$active] : []);
    }

    /**
     * Merge several snapshot `bom` lists into one, keyed by material so a
     * material shared across BOMs is summed rather than duplicated (the
     * allocation engine keys on material per batch, so duplicates would silently
     * drop the second line). Non-material rows (defensive) pass through verbatim.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $bomLists
     * @return array<int, array<string, mixed>>
     */
    protected function mergeBoms(array $bomLists): array
    {
        $merged = [];
        $passthrough = [];

        foreach ($bomLists as $bom) {
            foreach ($bom as $row) {
                $materialId = $row['material_id'] ?? null;
                if ($materialId === null) {
                    $passthrough[] = $row;

                    continue;
                }

                $merged[$materialId] = isset($merged[$materialId])
                    ? $this->mergeBomRows($merged[$materialId], $row)
                    : $row;
            }
        }

        return array_merge(array_values($merged), $passthrough);
    }

    /**
     * Combine two BOM rows for the same material. When the scrap rate matches we
     * sum the per-unit quantities and keep the rate; when it differs we fold each
     * line's scrap into an effective per-unit quantity (rate → 0) so the total
     * required quantity stays exact. Timing collapses to the earliest either BOM
     * asks for the material.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    protected function mergeBomRows(array $a, array $b): array
    {
        $qtyA = (float) ($a['quantity_per_unit'] ?? 0);
        $qtyB = (float) ($b['quantity_per_unit'] ?? 0);
        $scrapA = (float) ($a['scrap_percentage'] ?? 0);
        $scrapB = (float) ($b['scrap_percentage'] ?? 0);

        if (abs($scrapA - $scrapB) < 1e-9) {
            $a['quantity_per_unit'] = $qtyA + $qtyB;
            $a['scrap_percentage'] = $scrapA;
        } else {
            $a['quantity_per_unit'] = $qtyA * (1 + $scrapA / 100) + $qtyB * (1 + $scrapB / 100);
            $a['scrap_percentage'] = 0.0;
        }

        $aAt = $a['consumed_at'] ?? 'start';
        $bAt = $b['consumed_at'] ?? 'start';
        $a['consumed_at'] = $this->earliestConsumedAt($aAt, $bAt);

        $aStep = $a['step_number'] ?? null;
        $bStep = $b['step_number'] ?? null;

        if ($a['consumed_at'] === 'during') {
            // Consume at the earliest step that needs the material, considering only
            // the rows that are themselves `during` (a start/end row's step is
            // irrelevant to timing), so selection order can't push it to a later step.
            $duringSteps = array_filter(
                [$aAt === 'during' ? $aStep : null, $bAt === 'during' ? $bStep : null],
                fn ($s) => $s !== null,
            );
            $a['step_number'] = $duringSteps ? min($duringSteps) : ($aStep ?? $bStep);
        } elseif ($aStep === null && $bStep !== null) {
            $a['step_number'] = $bStep;
        }

        return $a;
    }

    /**
     * Re-time any `during` BOM row whose step isn't part of this order's flow to
     * `start`, so a material carried in from a secondary BOM is still consumed
     * rather than silently stranded on a step that never runs.
     *
     * @param  array<int, array<string, mixed>>  $bom
     * @param  array<int, int>  $stepNumbers  Step numbers present in the primary snapshot.
     * @return array<int, array<string, mixed>>
     */
    protected function degradeOrphanDuringItems(array $bom, array $stepNumbers): array
    {
        return array_map(function (array $row) use ($stepNumbers) {
            if (($row['consumed_at'] ?? null) === 'during'
                && ! in_array($row['step_number'] ?? null, $stepNumbers, true)) {
                $row['consumed_at'] = 'start';
            }

            return $row;
        }, $bom);
    }

    /** Earliest of two consumption timings (start < during < end). */
    protected function earliestConsumedAt(string $a, string $b): string
    {
        $rank = ['start' => 0, 'during' => 1, 'end' => 2];

        return ($rank[$a] ?? 0) <= ($rank[$b] ?? 0) ? $a : $b;
    }

    /**
     * Sync the work order → BOM pivot to exactly the given selection, all active,
     * preserving order via sort_order.
     *
     * @param  array<int, int|string>  $templateIds
     */
    protected function syncBomSelection(WorkOrder $workOrder, array $templateIds): void
    {
        $payload = [];
        foreach ($this->normalizeIds($templateIds) as $i => $id) {
            $payload[$id] = ['is_active' => true, 'sort_order' => $i];
        }

        $workOrder->bomTemplates()->sync($payload);
    }

    /**
     * Normalize a raw id list: cast to int, drop empties, dedupe, keep order.
     *
     * @param  array<int, int|string|null>  $ids
     * @return array<int, int>
     */
    protected function normalizeIds(array $ids): array
    {
        $seen = [];
        $out = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $id = (int) $id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }

        return $out;
    }

    /**
     * Update an existing work order.
     */
    public function updateWorkOrder(WorkOrder $workOrder, array $data): WorkOrder
    {
        // Don't allow updates to completed work orders
        if ($workOrder->status === WorkOrder::STATUS_DONE) {
            throw new \Exception('Cannot update completed work order');
        }

        $workOrder->update([
            'customer_order_no' => array_key_exists('customer_order_no', $data)
                ? $data['customer_order_no']
                : $workOrder->customer_order_no,
            'customer_id' => array_key_exists('customer_id', $data)
                ? $data['customer_id']
                : $workOrder->customer_id,
            'planned_qty' => $data['planned_qty'] ?? $workOrder->planned_qty,
            'unit_price' => array_key_exists('unit_price', $data)
                ? $data['unit_price']
                : $workOrder->unit_price,
            'priority' => $data['priority'] ?? $workOrder->priority,
            'due_date' => $data['due_date'] ?? $workOrder->due_date,
            'description' => $data['description'] ?? $workOrder->description,
        ]);

        return $workOrder->fresh();
    }

    /**
     * Create a new batch for a work order.
     */
    public function createBatch(WorkOrder $workOrder, float $targetQty, ?int $workstationId = null, ?string $lotNumber = null): Batch
    {
        return DB::transaction(function () use ($workOrder, $targetQty, $workstationId, $lotNumber) {
            // Serialize against updateBomSelection() (see there): holding the
            // work-order row lock guarantees this batch is built from the committed
            // snapshot, never one that a concurrent BOM change is mid-rewrite.
            WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();
            $workOrder->refresh();

            // Calculate next batch number
            $lastBatch = $workOrder->batches()->reorder('batch_number', 'desc')->first();
            $batchNumber = $lastBatch ? $lastBatch->batch_number + 1 : 1;

            // Create batch
            $batch = Batch::create([
                'work_order_id' => $workOrder->id,
                'batch_number' => $batchNumber,
                'target_qty' => $targetQty,
                'produced_qty' => 0,
                'status' => Batch::STATUS_PENDING,
                'workstation_id' => $workstationId,
                'lot_number' => $lotNumber,
                'lot_assigned_at' => $lotNumber ? Batch::LOT_ON_START : null,
            ]);

            // Create batch steps from process snapshot (skipped if no snapshot)
            if (! empty($workOrder->process_snapshot)) {
                $this->createBatchStepsFromSnapshot($batch, $workOrder->process_snapshot);
            }

            return $batch;
        });
    }

    /**
     * Create batch steps from work order process snapshot.
     */
    protected function createBatchStepsFromSnapshot(Batch $batch, array $processSnapshot): void
    {
        $steps = $processSnapshot['steps'] ?? [];

        // Resolve the pre-selected step per variant group: the one flagged as
        // default, else the lowest step_number in the group. Siblings start
        // SKIPPED so the operator sees only the chosen path (and can switch).
        $chosen = [];
        $explicit = [];
        foreach ($steps as $s) {
            $group = $s['variant_group'] ?? null;
            if ($group === null) {
                continue;
            }
            $num = $s['step_number'];
            if (! empty($s['is_default_variant'])) {
                if (empty($explicit[$group])) {
                    $chosen[$group] = $num;
                    $explicit[$group] = true;
                }
            } elseif (empty($explicit[$group])) {
                $chosen[$group] = isset($chosen[$group]) ? min($chosen[$group], $num) : $num;
            }
        }

        foreach ($steps as $stepData) {
            $group = $stepData['variant_group'] ?? null;
            $status = BatchStep::STATUS_PENDING;
            if ($group !== null && isset($chosen[$group]) && $stepData['step_number'] !== $chosen[$group]) {
                $status = BatchStep::STATUS_SKIPPED;
            }

            BatchStep::create([
                'batch_id' => $batch->id,
                'step_number' => $stepData['step_number'],
                'name' => $stepData['name'],
                'instruction' => $stepData['instruction'] ?? null,
                'requires_confirmation' => $stepData['requires_confirmation'] ?? false,
                'workstation_id' => $stepData['workstation_id'] ?? null,
                'status' => $status,
                'is_optional' => $stepData['is_optional'] ?? false,
                'variant_group' => $group,
            ]);
        }

        // Mark the first eligible step(s) READY ("next in line") so the operator
        // can start straight away; the rest stay PENDING until their turn.
        $batch->promoteReadySteps();
    }

    /**
     * Update work order status based on batches and issues.
     */
    public function updateWorkOrderStatus(WorkOrder $workOrder): void
    {
        // Check if blocked by issues
        if ($workOrder->isBlocked()) {
            $workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);

            return;
        }

        // Check if complete
        if ($workOrder->isComplete()) {
            $workOrder->update([
                'status' => WorkOrder::STATUS_DONE,
                'completed_at' => now(),
            ]);

            return;
        }

        // Check if any batch is in progress
        $hasInProgressBatch = $workOrder->batches()
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->exists();

        if ($hasInProgressBatch) {
            $workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

            return;
        }

        // Otherwise keep as pending
        if ($workOrder->status !== WorkOrder::STATUS_PENDING &&
            $workOrder->status !== WorkOrder::STATUS_DONE) {
            $workOrder->update(['status' => WorkOrder::STATUS_PENDING]);
        }
    }

    /**
     * Get work orders for a specific user's assigned lines.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWorkOrdersForUser($user, array $filters = [])
    {
        $query = WorkOrder::forUser($user)
            ->with(['line', 'productType', 'batches.steps']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->status($filters['status']);
        }

        if (isset($filters['line_id'])) {
            $query->forLine($filters['line_id']);
        }

        // Default ordering
        $query->byPriority();

        return $query->get();
    }
}
