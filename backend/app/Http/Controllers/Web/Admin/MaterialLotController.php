<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialLotController extends Controller
{
    public function index(Request $request)
    {
        $query = MaterialLot::query()
            ->with(['material', 'source', 'inspection'])
            ->orderByDesc('received_at');

        if ($search = $request->input('search')) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('lot_number', 'like', $like)
                    ->orWhere('supplier_lot_no', 'like', $like)
                    ->orWhere('supplier_reference', 'like', $like);
            });
        }

        if ($materialId = $request->input('material_id')) {
            $query->where('material_id', $materialId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($expiryFilter = $request->input('expiry')) {
            if ($expiryFilter === 'expired') {
                $query->whereNotNull('expiry_date')->where('expiry_date', '<', now()->toDateString());
            } elseif ($expiryFilter === 'soon') {
                $query->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            }
        }

        if ($supplier = $request->input('supplier')) {
            $query->where('supplier_lot_no', 'like', '%' . $supplier . '%');
        }

        $lots = $query->paginate(25)->withQueryString();
        $materials = Material::orderBy('name')->get(['id', 'name', 'code', 'unit_of_measure']);

        return view('admin.material-lots.index', [
            'lots' => $lots,
            'materials' => $materials,
            'statuses' => MaterialLot::STATUSES,
        ]);
    }

    public function show(MaterialLot $materialLot)
    {
        $materialLot->load([
            'material',
            'source',
            'inspection',
            'createdBy',
            'sublots',
            // Consumption history with batch step + WO for forward genealogy
            'consumptions.batchStep.batch.workOrder',
            'consumptions.sublot',
            'consumptions.recordedBy',
        ]);

        return view('admin.material-lots.show', ['lot' => $materialLot]);
    }

    public function create()
    {
        return view('admin.material-lots.create', [
            'lot' => new MaterialLot([
                'received_at' => now(),
                'status' => MaterialLot::STATUS_RECEIVED,
            ]),
            'materials' => Material::orderBy('name')->get(),
            'sources' => MaterialSource::orderBy('external_name')->get(),
            'statuses' => MaterialLot::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateLot($request);
        // On creation, available defaults to received unless explicitly given.
        $data['quantity_available'] = $data['quantity_available'] ?? $data['quantity_received'];
        $data['created_by_id'] = $request->user()?->id;

        MaterialLot::create($data);

        return redirect()->route('admin.material-lots.index')
            ->with('success', __('Material lot created.'));
    }

    public function edit(MaterialLot $materialLot)
    {
        return view('admin.material-lots.edit', [
            'lot' => $materialLot,
            'materials' => Material::orderBy('name')->get(),
            'sources' => MaterialSource::orderBy('external_name')->get(),
            'statuses' => MaterialLot::STATUSES,
        ]);
    }

    public function update(Request $request, MaterialLot $materialLot)
    {
        $data = $this->validateLot($request, $materialLot);
        $materialLot->update($data);

        return redirect()->route('admin.material-lots.index')
            ->with('success', __('Material lot updated.'));
    }

    /**
     * Soft-delete semantics: we never hard-delete a lot that has already been
     * partially consumed (the genealogy chain must remain intact). Instead, we
     * transition it to 'rejected'. Only an untouched lot can be removed.
     */
    public function destroy(MaterialLot $materialLot)
    {
        $received = (float) $materialLot->quantity_received;
        $available = (float) $materialLot->quantity_available;

        if (abs($received - $available) > 1e-9) {
            return redirect()->route('admin.material-lots.index')
                ->with('error', __('Cannot delete a lot with recorded consumption. Mark it rejected instead.'));
        }

        if ($materialLot->consumptions()->exists()) {
            return redirect()->route('admin.material-lots.index')
                ->with('error', __('Cannot delete a lot referenced by batch genealogy.'));
        }

        $materialLot->delete();

        return redirect()->route('admin.material-lots.index')
            ->with('success', __('Material lot deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLot(Request $request, ?MaterialLot $existing = null): array
    {
        $lotNumberRule = ['required', 'string', 'max:100'];
        // Uniqueness scoped to the current tenant. On update we exclude the row itself.
        $tenantId = $request->user()?->tenant_id;
        $unique = Rule::unique('material_lots', 'lot_number')
            ->where(fn ($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q);
        if ($existing) {
            $unique = $unique->ignore($existing->id);
        }
        $lotNumberRule[] = $unique;

        return $request->validate([
            'lot_number' => $lotNumberRule,
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'source_id' => ['nullable', 'integer', 'exists:material_sources,id'],
            'quantity_received' => ['required', 'numeric', 'min:0'],
            'quantity_available' => ['nullable', 'numeric', 'min:0'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'received_at' => ['required', 'date'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
            'status' => ['required', Rule::in(MaterialLot::STATUSES)],
            'supplier_lot_no' => ['nullable', 'string', 'max:100'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'inspection_id' => ['nullable', 'integer', 'exists:inspections,id'],
        ]);
    }
}
