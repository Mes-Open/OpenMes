<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\CompleteInspectionRequest;
use App\Http\Requests\Quality\InspectionDispositionRequest;
use App\Http\Requests\Quality\RecordInspectionResultRequest;
use App\Http\Requests\Quality\StoreInspectionRequest;
use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\InspectionResult;
use App\Models\Line;
use App\Models\Material;
use App\Models\User;
use App\Services\Production\OperatorWorkstationSelection;
use App\Services\Quality\DispositionService;
use App\Services\Quality\InboundInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbound inspections on the operator tablet, for whoever holds the
 * "Quality inspector" or "Quality disposition" permission.
 *
 * Authorised by InspectionPolicy — the Form Requests for writes, authorize()
 * for reads — exactly as the API is (Api\V1\InspectionController), and writing
 * through the same services, so the two cannot drift apart.
 */
class InspectionController extends Controller
{
    public function __construct(private InboundInspectionService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inspection::class);

        $with = ['material:id,code,name,unit_of_measure', 'inspector:id,name'];

        return Inertia::render('operator/inspections/Index', [
            'line' => $this->line($request),
            'pending' => Inspection::with($with)
                ->where('status', Inspection::STATUS_PENDING)
                ->orderByDesc('started_at')
                ->limit(50)
                ->get(),
            'awaitingDisposition' => Inspection::with($with)
                ->where('status', '!=', Inspection::STATUS_PENDING)
                ->where(fn ($q) => $q->whereNull('disposition')->orWhere('disposition', Inspection::DISPOSITION_PENDING))
                ->orderByDesc('completed_at')
                ->limit(50)
                ->get(),
            'materials' => $this->materialOptions(),
            'can' => $this->abilities($request->user()),
        ]);
    }

    public function store(StoreInspectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $material = Material::findOrFail($validated['material_id']);
        // No plan picked: use the published plan that applies to this
        // material — its own before its type's before a generic one — so the
        // inspector gets its criteria to fill in.
        $plan = isset($validated['inspection_plan_id'])
            ? InspectionPlan::findOrFail($validated['inspection_plan_id'])
            : InspectionPlan::applicableTo($material)->get()
                ->sortBy(fn (InspectionPlan $p) => $p->material_id ? 0 : ($p->material_type_id ? 1 : 2))
                ->first();

        $inspection = $this->service->start(
            $material,
            $validated['lot_number'],
            isset($validated['quantity_received']) ? (float) $validated['quantity_received'] : null,
            $plan,
            $request->user(),
            $validated['supplier_lot_ref'] ?? null,
            $validated['source_container_no'] ?? null,
        );

        return redirect()->route('operator.inspections.show', $inspection)
            ->with('success', __('Inspection started.'));
    }

    public function show(Request $request, Inspection $inspection): Response
    {
        $this->authorize('view', $inspection);

        return Inertia::render('operator/inspections/Show', [
            'line' => $this->line($request),
            'inspection' => $inspection->load([
                'results' => fn ($q) => $q->orderBy('id'),
                'material:id,code,name,unit_of_measure',
                'plan:id,name,version',
                'inspector:id,name',
            ]),
            'dispositions' => array_values(array_filter(
                Inspection::DISPOSITIONS,
                fn ($d) => $d !== Inspection::DISPOSITION_PENDING,
            )),
            'can' => $this->abilities($request->user(), $inspection),
        ]);
    }

    public function recordResult(RecordInspectionResultRequest $request, Inspection $inspection, InspectionResult $result): RedirectResponse
    {
        if ($result->inspection_id !== $inspection->id) {
            abort(404);
        }
        if (! $inspection->isPending()) {
            return back()->with('error', __('Cannot edit results of a completed inspection.'));
        }

        $this->service->recordResult($result, $request->validated());

        return back()->with('success', __('Result saved.'));
    }

    public function complete(CompleteInspectionRequest $request, Inspection $inspection): RedirectResponse
    {
        // InspectionNotCompletableException renders itself as an error flash.
        $this->service->complete($inspection, $request->validated()['notes'] ?? null, $request->user());

        return back()->with('success', __('Inspection completed.'));
    }

    public function disposition(InspectionDispositionRequest $request, Inspection $inspection, DispositionService $disposition): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $disposition->apply($inspection, $validated['disposition'], $validated['notes'] ?? null, $request->user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Disposition applied.'));
    }

    /**
     * Active materials for the start form, each flagged with whether a
     * published plan applies to it — without one the inspection would have no
     * criteria to fill in, so the form says so up front.
     *
     * @return list<array{id: int, code: string, name: string, unit_of_measure: ?string, has_plan: bool}>
     */
    private function materialOptions(): array
    {
        $plans = InspectionPlan::active()->published()->get(['material_id', 'material_type_id']);
        $generic = $plans->contains(fn ($p) => $p->material_id === null && $p->material_type_id === null);
        $materialIds = $plans->pluck('material_id')->filter()->flip();
        $typeIds = $plans->pluck('material_type_id')->filter()->flip();

        return Material::active()->orderBy('name')
            ->get(['id', 'code', 'name', 'unit_of_measure', 'material_type_id'])
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'unit_of_measure' => $m->unit_of_measure,
                'has_plan' => $generic || $materialIds->has($m->id) || ($m->material_type_id && $typeIds->has($m->material_type_id)),
            ])
            ->all();
    }

    /**
     * The selected line, so the operator top bar (and its tabs) render. The
     * screen itself does not depend on a line — inspections are per delivery.
     */
    private function line(Request $request): ?array
    {
        $lineId = app(OperatorWorkstationSelection::class)->resolveLine($request);

        return $lineId ? Line::find($lineId)?->only('id', 'name') : null;
    }

    /**
     * What the screen may offer — UX only, the Form Requests enforce it.
     *
     * @return array{perform: bool, disposition: bool}
     */
    private function abilities(User $user, ?Inspection $inspection = null): array
    {
        return [
            'perform' => $user->can('create', Inspection::class),
            'disposition' => $user->can('disposition', $inspection ?? new Inspection),
        ];
    }
}
