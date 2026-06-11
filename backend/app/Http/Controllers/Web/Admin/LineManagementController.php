<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\ProductType;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineManagementController extends Controller
{
    /**
     * Display a listing of production lines. Rows live-sync via the `lines_all`
     * shape; area names + counts come as props. Advanced per-line config (view
     * templates, statuses, operators, product types) stays on the show page.
     */
    public function index()
    {
        $lines = Line::withCount(['workstations', 'workOrders', 'users'])->get(['id']);

        return Inertia::render('admin/lines/Index', [
            'counts' => $lines->mapWithKeys(fn ($l) => [$l->id => [
                'workstations' => $l->workstations_count,
                'work_orders' => $l->work_orders_count,
                'operators' => $l->users_count,
            ]]),
            'areaNames' => Area::pluck('name', 'id'),
        ]);
    }

    /**
     * Show the form for creating a new line
     */
    public function create()
    {
        return Inertia::render('admin/lines/Create', [
            'areas' => $this->areaOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('line'),
        ]);
    }

    /** Areas as {id, name (with site)} options for the line form. */
    private function areaOptions(): \Illuminate\Support\Collection
    {
        return Area::with('site:id,name')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'site_id'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->site ? "{$a->name} ({$a->site->name})" : $a->name]);
    }

    /**
     * Store a newly created line
     */
    public function store(Request $request)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'code'        => 'required|string|max:50|unique:lines',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'area_id'     => 'nullable|exists:areas,id',
            'is_active'   => 'boolean',
        ], $cf->rules('line')), [], $cf->attributeNames('line'));

        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'line') ?: null;
        }

        Line::create($validated);

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line created successfully.');
    }

    /**
     * Display the specified line (configure page)
     */
    public function show(Line $line)
    {
        $line->load(['workstations', 'users', 'productTypes', 'viewColumns', 'viewTemplate']);
        $line->loadCount(['workOrders', 'workstations', 'users']);

        // work_orders has `order_no` (not work_order_number) and no product_name
        // column — the product name comes from the productType relation.
        $workOrders = $line->workOrders()
            ->with('productType:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'order_no', 'product_type_id', 'planned_qty', 'status', 'created_at']);

        $availableOperators = \App\Models\User::role('Operator')
            ->whereNotIn('id', $line->users->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $lineStatuses      = LineStatus::forLine($line->id)->get();
        $allProductTypes   = ProductType::active()->orderBy('name')->get(['id', 'code', 'name']);
        $assignedTypeIds   = $line->productTypes->pluck('id')->toArray();
        $viewColumns       = $line->viewColumns;
        $allViewTemplates  = \App\Models\ViewTemplate::orderBy('name')->get()->map(fn ($t) => [
            'id'            => $t->id,
            'name'          => $t->name,
            'columns_count' => count($t->columns ?? []),
        ]);

        $effectiveWorkstations = $line->effectiveWorkstations();

        return Inertia::render('admin/lines/Show', [
            'line' => array_merge(
                $line->only('id', 'code', 'name', 'description', 'is_active', 'default_operator_view', 'view_template_id', 'custom_fields'),
                [
                    'workstations_count' => $line->workstations_count,
                    'work_orders_count'  => $line->work_orders_count,
                    'users_count'        => $line->users_count,
                    'users'              => $line->users->map(fn ($u) => $u->only('id', 'name', 'username'))->values(),
                    'product_types'      => $line->productTypes->map(fn ($p) => $p->only('id', 'code', 'name'))->values(),
                ]
            ),
            'workOrders'          => $workOrders->map(fn ($wo) => [
                'id'                => $wo->id,
                'work_order_number' => $wo->order_no,
                'product_name'      => $wo->productType?->name,
                'planned_qty'       => $wo->planned_qty,
                'status'            => $wo->status,
                'created_at'        => $wo->created_at,
            ])->values(),
            'availableOperators'  => $availableOperators->map(fn ($u) => $u->only('id', 'name', 'username'))->values(),
            'lineStatuses'        => $lineStatuses->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'color'      => $s->color,
                'is_default' => $s->is_default,
                'line_id'    => $s->line_id,
            ])->values(),
            'allProductTypes'     => $allProductTypes->map(fn ($p) => $p->only('id', 'code', 'name'))->values(),
            'assignedTypeIds'     => $assignedTypeIds,
            'viewColumns'         => $viewColumns->map(fn ($c) => $c->only('id', 'label', 'key', 'source', 'sort_order'))->values(),
            'allViewTemplates'    => $allViewTemplates->values(),
            'effectiveWorkstations' => collect($effectiveWorkstations)->map(fn ($ws) => [
                'id'           => $ws->id,
                'name'         => $ws->name,
                'code'         => $ws->code,
                'is_line_itself' => $ws->is_line_itself ?? false,
            ])->values(),
            'customFields' => app(CustomFieldService::class)->clientConfig('line'),
        ]);
    }

    /**
     * Show the form for editing a line
     */
    public function edit(Line $line)
    {
        return Inertia::render('admin/lines/Edit', [
            'line' => $line->only('id', 'code', 'name', 'description', 'area_id', 'is_active', 'custom_fields'),
            'areas' => $this->areaOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('line'),
        ]);
    }

    /**
     * Update the specified line
     */
    public function update(Request $request, Line $line)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validate(array_merge([
            'code'        => 'required|string|max:50|unique:lines,code,' . $line->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'area_id'     => 'nullable|exists:areas,id',
            'is_active'   => 'boolean',
        ], $cf->rules('line')), [], $cf->attributeNames('line'));

        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'line', $line->custom_fields) ?: null;
        }

        $line->update($validated);

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line updated successfully.');
    }

    /**
     * Remove the specified line
     */
    public function destroy(Line $line)
    {
        // Check if line has work orders
        if ($line->workOrders()->count() > 0) {
            return redirect()->route('admin.lines.index')
                ->with('error', 'Cannot delete line with existing work orders. Deactivate it instead.');
        }

        $line->delete();

        return redirect()->route('admin.lines.index')
            ->with('success', 'Production line deleted successfully.');
    }

    /**
     * Toggle line active status
     */
    public function toggleActive(Line $line)
    {
        $line->update(['is_active' => !$line->is_active]);

        $status = $line->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.lines.index')
            ->with('success', "Production line {$status} successfully.");
    }

    /**
     * Assign an operator to the line
     */
    public function assignOperator(Request $request, Line $line)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);

        // Check if user is an operator
        if (!$user->hasRole('Operator')) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Only operators can be assigned to production lines.');
        }

        // Check if already assigned
        if ($line->users()->where('user_id', $user->id)->exists()) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Operator is already assigned to this line.');
        }

        $line->users()->attach($user->id);

        return redirect()->route('admin.lines.show', $line)
            ->with('success', "Operator {$user->name} assigned successfully.");
    }

    /**
     * Sync assigned product types for a line
     */
    public function syncProductTypes(Request $request, Line $line)
    {
        $validated = $request->validate([
            'product_type_ids'   => 'nullable|array',
            'product_type_ids.*' => 'exists:product_types,id',
        ]);

        $line->productTypes()->sync($validated['product_type_ids'] ?? []);

        return back()->with('success', 'Product types updated.');
    }

    /**
     * Unassign an operator from the line
     */
    public function unassignOperator(Line $line, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);

        $line->users()->detach($user->id);

        return redirect()->route('admin.lines.show', $line)
            ->with('success', "Operator {$user->name} unassigned successfully.");
    }

    /**
     * Assign a view template to a line.
     */
    public function assignViewTemplate(Request $request, Line $line)
    {
        $validated = $request->validate([
            'view_template_id' => 'nullable|exists:view_templates,id',
        ]);

        $line->update(['view_template_id' => $validated['view_template_id']]);

        return back()->with('success', 'View template updated.');
    }

    /**
     * Set default operator view for a line (queue or workstation).
     */
    public function setDefaultView(Request $request, Line $line)
    {
        $validated = $request->validate([
            'default_operator_view' => 'required|in:queue,workstation',
        ]);

        $line->update(['default_operator_view' => $validated['default_operator_view']]);

        return back()->with('success', 'Default operator view set to ' . ucfirst($validated['default_operator_view']) . '.');
    }

    /**
     * Save workstation view columns for a line.
     */
    public function saveViewColumns(Request $request, Line $line)
    {
        $validated = $request->validate([
            'columns'              => 'nullable|array|max:20',
            'columns.*.label'      => 'required|string|max:100',
            'columns.*.key'        => 'required|string|max:100',
            'columns.*.source'     => 'required|in:extra_data,field',
        ]);

        $line->viewColumns()->delete();

        foreach (($validated['columns'] ?? []) as $i => $col) {
            $line->viewColumns()->create([
                'label'      => $col['label'],
                'key'        => $col['key'],
                'source'     => $col['source'],
                'sort_order' => $i,
            ]);
        }

        return back()->with('success', 'Workstation view columns saved.');
    }
}
