<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use App\Services\Traceability\TraceabilityService;
use App\Support\UnitSerialisation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Material traceability / genealogy console.
 *
 * Resolves a finished-goods LOT, a material lot, a supplier LOT, or a serial
 * number and renders its full genealogy tree — the "recall readiness" view.
 */
class TraceabilityController extends Controller
{
    public function __construct(
        private readonly TraceabilityService $tracer,
        private readonly SerialTraceService $serials,
    ) {}

    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        // Serials, process serials and component identifiers are stored the way
        // the plant normalises them (case, spacing); look them up the same way.
        $identifier = app(UnitSerialisation::class)->normalize($term) ?? $term;
        $result = null;

        if ($term !== '') {
            $resolved = $this->tracer->resolve($term);

            if ($resolved && $resolved['type'] === 'pallet') {
                $result = [
                    'type' => 'pallet',
                    'data' => $this->tracer->palletTrace($resolved['model']),
                ];
            } elseif ($resolved && $resolved['type'] === 'batch') {
                $result = [
                    'type' => 'batch',
                    'data' => $this->mapBatch($this->tracer->batchGenealogy($resolved['model'])),
                ];
            } elseif ($resolved && $resolved['type'] === 'material_lot') {
                $lot = $resolved['model'];
                $lot->loadMissing('material:id,name,code');
                $result = [
                    'type' => 'material_lot',
                    // recallImpact() / backwardTraceLot() already return clean arrays.
                    'recall' => $this->tracer->recallImpact(collect([$lot])),
                    'forward' => $this->mapForward($this->tracer->forwardTrace($lot)),
                    'backward' => $this->tracer->backwardTraceLot($lot),
                    // Serialised units the lot was scanned into as a component - the
                    // recall answer for parts that never went through batch consumption.
                    'units' => $this->tracer->componentTrace(app(UnitSerialisation::class)->normalize($lot->lot_number) ?? $lot->lot_number)['units'],
                ];
            } elseif ($unit = SerialUnit::where(function ($q) use ($term, $identifier) {
                $q->whereIn('serial_no', [$term, $identifier])->orWhereIn('psn', [$term, $identifier]);
            })->first()) {
                $result = [
                    'type' => 'serial',
                    'recall' => $this->tracer->recallImpactForSerial($unit),
                    'components' => $this->tracer->componentLineJourneys($unit)['components'],
                    'installed' => $this->tracer->installedComponents($unit),
                    // A sub-assembly: the products it went into (empty for a finished product).
                    'installed_in' => $unit->serial_no ? $this->tracer->componentTrace($unit->serial_no)['units'] : [],
                    'data' => $this->mapSerial($this->serials->getHistory($unit)),
                ];
            } elseif ($componentId = SerialUnitComponent::whereIn('identifier', [$term, $identifier])->value('identifier')) {
                // A component known only by the serial on its label: which units hold it.
                $result = [
                    'type' => 'component',
                    'data' => $this->tracer->componentTrace($componentId),
                ];
            } elseif ($workOrder = WorkOrder::where('order_no', $term)->first()) {
                // The order number is what the shop floor knows - and what the
                // console's own orders table links with.
                $result = [
                    'type' => 'work_order',
                    'data' => $this->tracer->workOrderTrace($workOrder),
                ];
            } elseif (WorkOrder::where('customer_order_no', $term)->exists()) {
                // Customer order number is non-unique → aggregate all matching WOs.
                $result = [
                    'type' => 'customer_order',
                    'data' => $this->tracer->customerOrderTrace($term),
                ];
            }
        }

        return Inertia::render('admin/traceability/Index', [
            'term' => $term,
            'result' => $result,
            // id => name lookups for the browse tables, whose rows are synced
            // collections carrying only the foreign keys.
            'lineNames' => Line::pluck('name', 'id'),
            'productTypeNames' => ProductType::pluck('name', 'id'),
            'customerNames' => Customer::pluck('name', 'id'),
            'materialNames' => Material::pluck('name', 'id'),
            'workOrderNumbers' => WorkOrder::pluck('order_no', 'id'),
        ]);
    }

    /** Flatten batchGenealogy() into the shape the React page consumes. */
    private function mapBatch(array $g): array
    {
        $b = $g['batch'];
        $byStep = $g['consumptions_by_step'];
        $steps = $b->steps->sortBy('step_number')->values();

        return [
            'batch' => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'lot_number' => $b->lot_number,
                'status' => $b->status,
                'work_order' => $b->workOrder ? [
                    'order_no' => $b->workOrder->order_no,
                    'product' => $b->workOrder->productType?->name,
                ] : null,
                // Timings alongside the genealogy: when each step started and ended,
                // how long it ran, and how long the batch waited between steps.
                'lead_time_minutes' => $this->batchLeadTime($b->steps),
                'steps' => $steps->map(fn ($s, $i) => [
                    'id' => $s->id,
                    'step_number' => $s->step_number,
                    'name' => $s->name,
                    'status' => $s->status,
                    'workstation' => $s->workstation?->name,
                    'completed_by' => $s->completedBy?->name,
                    'started_at' => $s->started_at ? Carbon::parse($s->started_at)->format('Y-m-d H:i') : null,
                    'completed_at' => $s->completed_at ? Carbon::parse($s->completed_at)->format('Y-m-d H:i') : null,
                    'duration_minutes' => $s->duration_minutes ?? self::minutesBetween($s->started_at, $s->completed_at),
                    'waited_minutes' => $i > 0 ? self::minutesBetween($steps[$i - 1]->completed_at, $s->started_at) : null,
                    'consumptions' => ($byStep[$s->id] ?? collect())->map(fn ($c) => [
                        'lot_number' => $c->materialLot?->lot_number,
                        'material' => $c->materialLot?->material?->name,
                        'quantity' => (float) $c->quantity_consumed,
                    ])->values(),
                ])->values(),
                'output_lots' => $b->outputLots->map(fn ($o) => [
                    'lot_number' => $o->lot_number,
                ])->values(),
            ],
            'distinct_input_lots' => $g['distinct_input_lots']->map(fn ($lot) => [
                'material' => $lot->material?->name,
                'material_code' => $lot->material?->code,
                'lot_number' => $lot->lot_number,
                'supplier_lot_no' => $lot->supplier_lot_no,
                'source_container_no' => $lot->source_container_no,
                'status' => $lot->status,
            ])->values(),
        ];
    }

    /** Flatten forwardTrace() into a clean shape. */
    private function mapForward(array $f): array
    {
        return [
            'lot' => $f['lot'],
            'work_orders' => $f['work_orders']->map(fn ($wo) => [
                'order_no' => $wo->order_no,
                'product' => $wo->productType?->name,
                'status' => $wo->status,
            ])->values(),
            'total_consumed' => $f['total_consumed'],
            // Finished-goods forward leg: pallet(s) packed onto + customer order(s).
            // Already plain arrays/strings from the service - passed straight through.
            'is_finished_good' => $f['is_finished_good'],
            'pallets' => $f['pallets'],
            'customer_orders' => $f['customer_orders'],
        ];
    }

    /** Whole minutes from one stamp to the next; null unless both exist and are in order. */
    private static function minutesBetween($from, $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }
        $a = Carbon::parse($from);
        $b = Carbon::parse($to);

        return $b->lt($a) ? null : (int) $a->diffInMinutes($b);
    }

    /** First start to last finish across the batch's steps. */
    private function batchLeadTime($steps): ?int
    {
        $starts = $steps->pluck('started_at')->filter();
        $ends = $steps->pluck('completed_at')->filter();
        if ($starts->isEmpty() || $ends->isEmpty()) {
            return null;
        }

        return self::minutesBetween($starts->map(fn ($t) => Carbon::parse($t))->min(), $ends->map(fn ($t) => Carbon::parse($t))->max());
    }

    /** Flatten a serial unit + its process history. */
    private function mapSerial(SerialUnit $u): array
    {
        // Timings from the stamps the stations left: the gap since the previous
        // record, a test's own run time (the tester reports start and end), and
        // the lead time from the first record to shipping (or to the last record).
        $history = $u->history->sortBy('processed_at')->values();
        $first = $history->first()?->processed_at;
        $last = $u->shipped_at ?? $history->last()?->processed_at;

        return [
            'lead_time_seconds' => $first && $last && ! Carbon::parse($last)->lt(Carbon::parse($first)) ? (int) Carbon::parse($first)->diffInSeconds(Carbon::parse($last)) : null,
            'lead_time_to_shipping' => (bool) $u->shipped_at,
            'serial_no' => $u->serial_no,
            'psn' => $u->psn,
            'status' => $u->status,
            'product' => $u->workOrder?->productType?->name ?? $u->material?->name,
            'work_order' => $u->workOrder?->order_no,
            'carton_no' => $u->carton?->carton_no,
            'pallet_no' => $u->pallet?->pallet_no,
            'packed_at' => $u->packed_at?->format('Y-m-d H:i'),
            'shipped_at' => $u->shipped_at?->format('Y-m-d H:i'),
            'history' => $history->map(fn ($h, $i) => [
                'workstation' => $h->workstation?->name,
                'line' => $h->workstation?->line?->name,
                'step' => $h->batchStep?->name,
                'operator' => $h->operator?->name,
                'processed_at' => $h->processed_at ? Carbon::parse($h->processed_at)->format('Y-m-d H:i:s') : null,
                'since_previous_seconds' => $i > 0 && $h->processed_at && $history[$i - 1]->processed_at
                    ? max(0, (int) Carbon::parse($history[$i - 1]->processed_at)->diffInSeconds(Carbon::parse($h->processed_at), false))
                    : null,
                'duration_seconds' => ! empty($h->parameters['started_at']) && ! empty($h->parameters['ended_at'])
                    ? max(0, (int) Carbon::parse($h->parameters['started_at'])->diffInSeconds(Carbon::parse($h->parameters['ended_at']), false))
                    : null,
                'result' => $h->result,
                'parameters' => $h->parameters,
                'notes' => $h->notes,
            ])->values(),
        ];
    }
}
