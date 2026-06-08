<?php

namespace App\Services\Production;

use App\Models\Material;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Computes the production cost of a work order from material consumption,
 * labor (employee time blocks) and manually-booked additional costs.
 *
 * Pure read service — it never writes. The returned breakdown is consumed by
 * the cost report controller, its CSV export and the React detail view.
 *
 * Currency: there is no FX conversion. Amounts are summed numerically in the
 * configured default currency; when a contributing item carries a different
 * currency the breakdown raises a `mixed_currency` flag for the UI to warn on.
 */
class ProductionCostService
{
    private string $defaultCurrency;

    private float $standardWeeklyHours;

    private string $defaultPayType;

    private ?float $defaultPayRate;

    public function __construct()
    {
        // Editable in Settings → System (stored in system_settings); the
        // config/env values are the fallback default.
        $this->defaultCurrency = (string) $this->setting('default_currency', config('openmmes.default_currency', 'PLN'));
        $this->standardWeeklyHours = (float) $this->setting('standard_weekly_hours', config('openmmes.standard_weekly_hours', 40));
        $this->defaultPayType = (string) $this->setting('default_pay_type', config('openmmes.default_pay_type', 'hourly'));
        $rate = $this->setting('default_pay_rate', config('openmmes.default_pay_rate'));
        $this->defaultPayRate = ($rate === null || $rate === '') ? null : (float) $rate;
    }

    /** The configured reporting currency. */
    public function defaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    /**
     * Read a system_settings value, falling back to the given default when the
     * key is absent (or the table is unavailable during install).
     */
    private function setting(string $key, mixed $default): mixed
    {
        try {
            $row = DB::table('system_settings')->where('key', $key)->value('value');

            return $row !== null ? (json_decode($row, true) ?? $default) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Full cost breakdown for a single work order.
     */
    public function breakdown(WorkOrder $workOrder): array
    {
        $materials = $this->materialCost($workOrder);
        $labor = $this->laborCost($workOrder);
        $additional = $this->additionalCost($workOrder);

        $total = round($materials['total'] + $labor['total'] + $additional['total'], 2);
        $producedQty = (float) $workOrder->produced_qty;

        $mixedCurrency = $materials['mixed_currency']
            || $labor['mixed_currency']
            || $additional['mixed_currency'];

        return [
            'work_order_id' => $workOrder->id,
            'order_no' => $workOrder->order_no,
            'produced_qty' => $producedQty,
            'currency' => $this->defaultCurrency,
            'mixed_currency' => $mixedCurrency,
            'materials' => $materials,
            'labor' => $labor,
            'additional' => $additional,
            'total_cost' => $total,
            'cost_per_unit' => $producedQty > 0 ? round($total / $producedQty, 4) : null,
        ];
    }

    /**
     * Material cost. Uses actual recorded consumption when present, otherwise
     * falls back to the planned BOM recipe scaled by produced quantity.
     */
    public function materialCost(WorkOrder $workOrder): array
    {
        $consumed = $workOrder->materialAllocations
            ->filter(fn ($a) => (float) $a->consumed_qty > 0);

        if ($consumed->isNotEmpty()) {
            return $this->buildBucket($consumed->map(function ($allocation) {
                $unitPrice = $allocation->unit_price_snapshot !== null
                    ? (float) $allocation->unit_price_snapshot
                    : (float) ($allocation->material?->unit_price ?? 0);
                $currency = $allocation->price_currency_snapshot
                    ?: ($allocation->material?->price_currency ?: $this->defaultCurrency);
                $qty = (float) $allocation->consumed_qty;

                return [
                    'material_code' => $allocation->material?->code,
                    'material_name' => $allocation->material?->name,
                    'source' => 'actual',
                    'qty' => round($qty, 4),
                    'unit_price' => round($unitPrice, 4),
                    'currency' => $currency,
                    'line_total' => round($qty * $unitPrice, 2),
                ];
            })->all());
        }

        return $this->buildBucket($this->bomFallbackItems($workOrder));
    }

    /**
     * Labor cost from `work` employee activities attributed to this work order,
     * grouped per worker and priced by the worker's compensation mode.
     */
    public function laborCost(WorkOrder $workOrder): array
    {
        $producedQty = (float) $workOrder->produced_qty;

        // Hours of productive work per worker on this WO.
        $byWorker = $workOrder->employeeActivities
            ->filter(fn ($a) => $a->type === 'work' && $a->worker)
            ->groupBy('worker_id');

        // Piece-rate workers split the WO output proportionally to logged hours.
        $pieceHoursByWorker = [];
        foreach ($byWorker as $workerId => $activities) {
            $worker = $activities->first()->worker;
            if ($this->payTypeOf($worker) === 'piece_rate') {
                $pieceHoursByWorker[$workerId] = $this->hoursOf($activities);
            }
        }
        $totalPieceHours = array_sum($pieceHoursByWorker);
        $pieceWorkerCount = count($pieceHoursByWorker);

        $items = [];
        foreach ($byWorker as $workerId => $activities) {
            $worker = $activities->first()->worker;
            $hours = $this->hoursOf($activities);
            $rate = $worker->effectivePayRate() ?? $this->defaultPayRate ?? 0.0;
            // Currency is system-wide (Settings → System), not per worker.
            $currency = $this->defaultCurrency;
            $payType = $this->payTypeOf($worker);

            if ($payType === 'piece_rate') {
                $pieces = $totalPieceHours > 0
                    ? $producedQty * ($pieceHoursByWorker[$workerId] / $totalPieceHours)
                    : ($pieceWorkerCount > 0 ? $producedQty / $pieceWorkerCount : 0.0);
                $basis = round($pieces, 4);
                $basisUnit = 'pcs';
                $lineTotal = $rate * $pieces;
            } elseif ($payType === 'weekly') {
                $effectiveHourly = $this->standardWeeklyHours > 0 ? $rate / $this->standardWeeklyHours : 0.0;
                $basis = round($hours, 4);
                $basisUnit = 'h';
                $lineTotal = $effectiveHourly * $hours;
                $rate = $effectiveHourly;
            } else { // hourly (and default fallback)
                $basis = round($hours, 4);
                $basisUnit = 'h';
                $lineTotal = $rate * $hours;
            }

            $items[] = [
                'worker_code' => $worker->code,
                'worker_name' => $worker->name,
                'pay_type' => $payType,
                'basis' => $basis,
                'basis_unit' => $basisUnit,
                'rate' => round($rate, 4),
                'currency' => $currency,
                'line_total' => round($lineTotal, 2),
            ];
        }

        return $this->buildBucket($items);
    }

    /**
     * Manually-booked additional costs for this work order.
     */
    public function additionalCost(WorkOrder $workOrder): array
    {
        return $this->buildBucket($workOrder->additionalCosts->map(fn ($cost) => [
            'description' => $cost->description,
            'currency' => $cost->currency ?: $this->defaultCurrency,
            'line_total' => round((float) $cost->amount, 2),
        ])->all());
    }

    /**
     * Planned BOM items priced at the material's current unit price, scaled by
     * produced quantity (including scrap allowance). Prefers the immutable
     * process snapshot stored on the work order, then the product's template.
     */
    private function bomFallbackItems(WorkOrder $workOrder): array
    {
        $producedQty = (float) $workOrder->produced_qty;
        if ($producedQty <= 0) {
            return [];
        }

        $bom = $workOrder->process_snapshot['bom'] ?? null;

        if (! empty($bom)) {
            $materialIds = collect($bom)->pluck('material_id')->filter()->unique();
            $materials = Material::whereIn('id', $materialIds)->get()->keyBy('id');

            return collect($bom)->map(function ($line) use ($producedQty, $materials) {
                $material = $materials->get($line['material_id'] ?? null);
                $qtyPerUnit = (float) ($line['quantity_per_unit'] ?? 0);
                $scrapPct = (float) ($line['scrap_percentage'] ?? 0);
                $qty = $qtyPerUnit * $producedQty * (1 + $scrapPct / 100);
                $unitPrice = (float) ($material?->unit_price ?? 0);

                return [
                    'material_code' => $line['material_code'] ?? $material?->code,
                    'material_name' => $line['material_name'] ?? $material?->name,
                    'source' => 'bom',
                    'qty' => round($qty, 4),
                    'unit_price' => round($unitPrice, 4),
                    'currency' => $material?->price_currency ?: $this->defaultCurrency,
                    'line_total' => round($qty * $unitPrice, 2),
                ];
            })->all();
        }

        // No snapshot — fall back to the product's live process template BOM.
        $template = $workOrder->productType?->processTemplates()->with('bomItems.material')->first();
        if (! $template) {
            return [];
        }

        return $template->bomItems->map(function ($item) use ($producedQty) {
            $qty = $item->calculateRequiredQuantity($producedQty);
            $unitPrice = (float) ($item->material?->unit_price ?? 0);

            return [
                'material_code' => $item->material?->code,
                'material_name' => $item->material?->name,
                'source' => 'bom',
                'qty' => round($qty, 4),
                'unit_price' => round($unitPrice, 4),
                'currency' => $item->material?->price_currency ?: $this->defaultCurrency,
                'line_total' => round($qty * $unitPrice, 2),
            ];
        })->all();
    }

    /**
     * Effective compensation mode for a worker: their own pay type, or the
     * system-wide default when none is set.
     */
    private function payTypeOf($worker): string
    {
        return $worker->pay_type ?: $this->defaultPayType;
    }

    /**
     * Sum the productive hours of a set of activities.
     */
    private function hoursOf($activities): float
    {
        return $activities->sum(fn ($a) => $a->durationMinutes()) / 60;
    }

    /**
     * Wrap a list of cost items into a bucket with total + currency metadata.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function buildBucket(array $items): array
    {
        $total = round(collect($items)->sum('line_total'), 2);
        $mixed = collect($items)
            ->pluck('currency')
            ->filter()
            ->contains(fn ($c) => $c !== $this->defaultCurrency);

        return [
            'total' => $total,
            'currency' => $this->defaultCurrency,
            'mixed_currency' => $mixed,
            'items' => array_values($items),
        ];
    }
}
