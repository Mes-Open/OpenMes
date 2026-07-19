<?php

namespace App\Http\Controllers\Web\Logistics;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePalletMovementRequest;
use App\Models\Pallet;
use App\Models\PalletMovement;
use App\Models\Worker;
use App\Services\Logistics\PalletMovementService;
use Inertia\Inertia;

/**
 * Shop-floor pallet movement (#103): a lightweight terminal where a logistics
 * operator identifies themselves, picks a pallet, and records a physical move —
 * plus the admin movement-history list surfacing who moved what.
 */
class PalletMovementController extends Controller
{
    /** The tablet/terminal "Move Pallet" screen. */
    public function terminal()
    {
        return Inertia::render('logistics/MovePallet', [
            // Badge-style picker: active logistics operators only.
            'operators' => Worker::active()->logistics()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            // Movable pallets (not yet shipped), most recent first.
            'pallets' => Pallet::where('status', '!=', PalletStatus::Shipped->value)
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'pallet_no', 'location']),
        ]);
    }

    public function store(StorePalletMovementRequest $request, PalletMovementService $service)
    {
        $service->record(
            Pallet::findOrFail($request->integer('pallet_id')),
            Worker::findOrFail($request->integer('worker_id')),
            $request->string('to_location')->toString(),
            $request->user(),
            $request->input('notes'),
        );

        return redirect()->route('logistics.move-pallet')
            ->with('success', __('Pallet movement recorded.'));
    }

    /** Admin movement history — rows arrive client-side via the Electric shape. */
    public function index()
    {
        return Inertia::render('admin/pallet-movements/Index', [
            // Lookup maps for the live table (rows stream via the shape). Bound
            // to the ids the ledger actually references so the payload can't
            // balloon to the whole pallets/workers tables. withTrashed so
            // soft-deleted pallets/operators still resolve to a label.
            'operatorNames' => Worker::withTrashed()
                ->whereIn('id', PalletMovement::query()->select('worker_id'))
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Worker $w) => [$w->id => $w->code.' — '.$w->name]),
            'palletNumbers' => Pallet::withTrashed()
                ->whereIn('id', PalletMovement::query()->select('pallet_id'))
                ->pluck('pallet_no', 'id'),
        ]);
    }
}
