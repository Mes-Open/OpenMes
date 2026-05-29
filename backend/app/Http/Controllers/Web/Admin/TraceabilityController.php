<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SerialUnit;
use App\Services\Traceability\SerialTraceService;
use App\Services\Traceability\TraceabilityService;
use Illuminate\Http\Request;

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

            if ($resolved && $resolved['type'] === 'batch') {
                $result = [
                    'type' => 'batch',
                    'data' => $this->tracer->batchGenealogy($resolved['model']),
                ];
            } elseif ($resolved && $resolved['type'] === 'material_lot') {
                $lot = $resolved['model'];
                $result = [
                    'type' => 'material_lot',
                    'forward' => $this->tracer->forwardTrace($lot),
                    'backward' => $this->tracer->backwardTraceLot($lot),
                ];
            } else {
                // Fall back to serial-number lookup
                $unit = SerialUnit::where('serial_no', $term)->first();
                if ($unit) {
                    $result = [
                        'type' => 'serial',
                        'data' => $this->serials->getHistory($unit),
                    ];
                }
            }
        }

        return view('admin.traceability.index', [
            'term' => $term,
            'result' => $result,
        ]);
    }
}
