<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Concerns\ServesBothSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Logistics\ShipmentLoadingException;
use App\Services\Logistics\ShipmentLoadingService;
use App\Support\Integration\ShipmentPresenter;
use Inertia\Inertia;

/**
 * Shipments for supervisors and admins (#400): plan a load with its expected
 * list (pasted or from a CSV), follow it on the detail page, cancel it. Served
 * under both /admin and /supervisor (ServesBothSections). Rows, expected units
 * and the scan log all stream from synced collections; the props are lookups.
 */
class ShipmentController extends Controller
{
    use ServesBothSections;

    public function __construct(private readonly ShipmentLoadingService $service) {}

    public function index()
    {
        $this->authorize('viewAny', Shipment::class);

        return Inertia::render('admin/shipments/Index', [
            'basePath' => $this->basePath('/shipments'),
            'statusLabels' => ShipmentStatus::labels(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Shipment::class);

        return Inertia::render('admin/shipments/Create', [
            'basePath' => $this->basePath('/shipments'),
        ]);
    }

    public function store(StoreShipmentRequest $request)
    {
        $shipment = $this->service->create($request->shipmentAttributes(), $request->expectedItems(), $request->user());

        return redirect()->to($this->sectionRoute('shipments.show', $shipment))
            ->with('success', __('Shipment :no created with :count expected unit(s).', [
                'no' => $shipment->shipment_no,
                'count' => $shipment->items()->count(),
            ]));
    }

    public function show(Shipment $shipment)
    {
        $this->authorize('view', $shipment);

        return Inertia::render('admin/shipments/Show', [
            'basePath' => $this->basePath('/shipments'),
            'shipment' => ShipmentPresenter::header($shipment->load(ShipmentPresenter::RELATIONS)),
            'statusLabels' => ShipmentStatus::labels(),
            'userNames' => $this->userNames(),
            'terminalHref' => route('logistics.loading.show', $shipment),
        ]);
    }

    public function edit(Shipment $shipment)
    {
        $this->authorize('update', $shipment);

        return Inertia::render('admin/shipments/Edit', [
            'basePath' => $this->basePath('/shipments'),
            'shipment' => ShipmentPresenter::header($shipment) + [
                'items_text' => $shipment->items()->orderBy('id')->get()
                    ->map(fn ($i) => $i->unit_type.','.$i->identifier)->implode("\n"),
            ],
        ]);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment)
    {
        try {
            $this->service->update($shipment, $request->shipmentAttributes(), $request->expectedItems(), $request->user());
        } catch (ShipmentLoadingException $e) {
            return back()->withErrors(['items_text' => $e->getMessage()]);
        }

        return redirect()->to($this->sectionRoute('shipments.show', $shipment))
            ->with('success', __('Shipment updated.'));
    }

    public function cancel(Shipment $shipment)
    {
        $this->authorize('cancel', $shipment);

        try {
            $this->service->cancel($shipment);
        } catch (ShipmentLoadingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Shipment cancelled.'));
    }

    public function destroy(Shipment $shipment)
    {
        $this->authorize('delete', $shipment);

        if ($shipment->status === ShipmentStatus::Loading) {
            return back()->with('error', __('A shipment being loaded cannot be deleted. Cancel it first.'));
        }

        $shipment->delete();

        return redirect()->to($this->sectionRoute('shipments.index'))
            ->with('success', __('Shipment deleted.'));
    }

    /** id → name of the people who may appear in a scan log. */
    private function userNames()
    {
        return User::query()->orderBy('id')->limit(2000)->pluck('name', 'id');
    }
}
