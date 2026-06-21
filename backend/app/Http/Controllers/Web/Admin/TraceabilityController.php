<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SerialUnit;
use App\Models\WorkOrder;
use App\Services\Traceability\SerialTraceService;
use App\Services\Traceability\TraceabilityService;
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
                ];
            } elseif ($unit = SerialUnit::where('serial_no', $term)->first()) {
                $result = [
                    'type' => 'serial',
                    'recall' => $this->tracer->recallImpactForSerial($unit),
                    'components' => $this->tracer->componentLineJourneys($unit)['components'],
                    'data' => $this->mapSerial($this->serials->getHistory($unit)),
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
        ]);
    }

    /** Flatten batchGenealogy() into the shape the React page consumes. */
    private function mapBatch(array $g): array
    {
        $b = $g['batch'];
        $byStep = $g['consumptions_by_step'];

        return [
            'batch' => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'lot_number' => $b->lot_number,
                'work_order' => $b->workOrder ? [
                    'order_no' => $b->workOrder->order_no,
                    'product' => $b->workOrder->productType?->name,
                ] : null,
                'steps' => $b->steps->map(fn ($s) => [
                    'id' => $s->id,
                    'step_number' => $s->step_number,
                    'name' => $s->name,
                    'status' => $s->status,
                    'workstation' => $s->workstation?->name,
                    'completed_by' => $s->completedBy?->name,
                    'completed_at' => $s->completed_at ? Carbon::parse($s->completed_at)->format('Y-m-d H:i') : null,
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
        ];
    }

    /** Flatten a serial unit + its process history. */
    private function mapSerial(SerialUnit $u): array
    {
        return [
            'serial_no' => $u->serial_no,
            'status' => $u->status,
            'product' => $u->workOrder?->productType?->name ?? $u->material?->name,
            'work_order' => $u->workOrder?->order_no,
            'history' => $u->history->map(fn ($h) => [
                'workstation' => $h->workstation?->name,
                'line' => $h->workstation?->line?->name,
                'step' => $h->batchStep?->name,
                'operator' => $h->operator?->name,
                'processed_at' => $h->processed_at ? Carbon::parse($h->processed_at)->format('Y-m-d H:i:s') : null,
                'result' => $h->result,
                'parameters' => $h->parameters,
            ])->values(),
        ];
    }
}
