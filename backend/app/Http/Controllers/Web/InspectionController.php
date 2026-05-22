<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Services\Quality\DispositionService;
use App\Services\Quality\InboundInspectionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InspectionController extends Controller
{
    public function __construct(private InboundInspectionService $service) {}

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['pending', 'recent', 'failed'], true)
            ? $request->query('tab')
            : 'pending';

        $query = Inspection::with(['material', 'inspector', 'issue']);

        $query = match ($tab) {
            'pending' => $query->where('status', Inspection::STATUS_PENDING)->orderBy('started_at'),
            'failed' => $query->where('status', Inspection::STATUS_FAIL)->orderByDesc('completed_at'),
            default => $query->whereIn('status', ['pass', 'fail', 'conditional_pass'])->orderByDesc('completed_at'),
        };

        if ($d = $request->query('disposition')) {
            $query->where('disposition', $d);
        }

        $inspections = $query->limit(100)->get();

        $stats = [
            'pending' => Inspection::where('status', Inspection::STATUS_PENDING)->count(),
            'recent_fail' => Inspection::where('status', Inspection::STATUS_FAIL)
                ->where('completed_at', '>=', now()->subDays(30))->count(),
        ];

        $selectedDisposition = $request->query('disposition');

        return view('inspections.index', compact('inspections', 'tab', 'stats', 'selectedDisposition'));
    }

    public function create()
    {
        return view('inspections.create', [
            'materials' => Material::orderBy('name')->get(),
            'plans' => InspectionPlan::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|integer|exists:materials,id',
            'lot_number' => 'required|string|max:100',
            'quantity_received' => 'nullable|numeric|min:0',
            'supplier_lot_ref' => 'nullable|string|max:100',
            'inspection_plan_id' => 'nullable|integer|exists:inspection_plans,id',
        ]);

        $material = Material::findOrFail($validated['material_id']);
        $plan = isset($validated['inspection_plan_id'])
            ? InspectionPlan::findOrFail($validated['inspection_plan_id'])
            : null;

        $inspection = $this->service->start(
            $material,
            $validated['lot_number'],
            $validated['quantity_received'] ?? null,
            $plan,
            $request->user(),
            $validated['supplier_lot_ref'] ?? null,
        );

        return redirect()->route('inspections.show', $inspection)
            ->with('success', __('Inspection started.'));
    }

    public function show(Inspection $inspection)
    {
        return view('inspections.show', [
            'inspection' => $inspection->load(['results', 'material', 'plan', 'inspector', 'issue']),
        ]);
    }

    public function recordResult(Request $request, Inspection $inspection)
    {
        abort_unless($inspection->isPending(), 422);

        $validated = $request->validate([
            'results' => 'required|array',
            'results.*.id' => 'required|integer',
            'results.*.value_numeric' => 'nullable|numeric',
            'results.*.value_boolean' => 'nullable|in:0,1,true,false',
            'results.*.value_text' => 'nullable|string|max:1000',
            'results.*.notes' => 'nullable|string|max:1000',
        ]);

        foreach ($validated['results'] as $payload) {
            $result = $inspection->results()->find($payload['id']);
            if (! $result) {
                continue;
            }
            $data = [];
            if (array_key_exists('value_numeric', $payload)) {
                $data['value_numeric'] = $payload['value_numeric'];
            }
            if (array_key_exists('value_boolean', $payload) && $payload['value_boolean'] !== null) {
                $data['value_boolean'] = filter_var($payload['value_boolean'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('value_text', $payload)) {
                $data['value_text'] = $payload['value_text'];
            }
            if (array_key_exists('notes', $payload)) {
                $data['notes'] = $payload['notes'];
            }
            $this->service->recordResult($result, $data);
        }

        return redirect()->route('inspections.show', $inspection)
            ->with('success', __('Measurements saved.'));
    }

    public function complete(Request $request, Inspection $inspection)
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);

        $completed = $this->service->complete($inspection, $validated['notes'] ?? null);

        $msg = match ($completed->status) {
            Inspection::STATUS_PASS => __('Inspection passed.'),
            Inspection::STATUS_CONDITIONAL => __('Inspection passed conditionally (non-required criteria failed).'),
            Inspection::STATUS_FAIL => __('Inspection failed — non-conformance issue created.'),
            default => __('Inspection completed.'),
        };

        return redirect()->route('inspections.show', $inspection)->with('success', $msg);
    }

    public function disposition(Request $request, Inspection $inspection, DispositionService $service)
    {
        $validated = $request->validate([
            'disposition' => [
                'required',
                'string',
                Rule::in(array_diff(Inspection::DISPOSITIONS, [Inspection::DISPOSITION_PENDING])),
            ],
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $service->apply(
                $inspection,
                $validated['disposition'],
                $validated['notes'] ?? null,
                auth()->user(),
            );
        } catch (\Throwable $e) {
            return back()->with('error', __('Failed to apply disposition: :err', ['err' => $e->getMessage()]));
        }

        return back()->with('success', __('Disposition recorded.'));
    }
}
