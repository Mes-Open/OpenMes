<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreLineRequest;
use App\Http\Requests\Web\Admin\UpdateLineRequest;
use App\Models\Area;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\ProductType;
use App\Models\WorkstationState;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineManagementController extends Controller
{
    use StaysOnList;

    /** Most recent orders shown in the line's work-order panel. */
    private const WORK_ORDER_PANEL_LIMIT = 100;

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
            // Option lists for the list page's create/edit drawer. Optional, so the
            // queries only run once someone opens it — most visits never do.
            'areas' => Inertia::optional(fn () => $this->areaOptions()),
            'customFields' => Inertia::optional(fn () => app(CustomFieldService::class)->clientConfig('line')),
        ]);
    }

    /**
     * Show the form for creating a new line
     */
    public function create()
    {
        return Inertia::render('admin/lines/Create', [
            'areas' => $this->areaOptions(),
            'warehouses' => $this->warehouseOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('line'),
        ]);
    }

    /**
     * Raw-material locations a line can consume from. Only those, because a line
     * draws components, never finished goods.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function warehouseOptions(): \Illuminate\Support\Collection
    {
        return \App\Models\Warehouse::forMaterials()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($w) => ['id' => $w->id, 'name' => "{$w->name} ({$w->code})"]);
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
    public function store(StoreLineRequest $request)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validated();

        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'line') ?: null;
        }

        Line::create($validated);

        return $this->saved($request, redirect()->route('admin.lines.index'), 'Production line created successfully.');
    }

    /**
     * Display the specified line (configure page)
     */
    public function show(Line $line)
    {
        $line->load([
            'workstations.workers:id,name,workstation_id',
            'users', 'productTypes', 'viewColumns', 'viewTemplate',
        ]);
        $line->loadCount(['workOrders', 'workstations', 'users']);

        // Rows for the work-order panel, in the same shape the work-order list
        // renders — it reuses `woColumns()`, so the fields those cells read
        // (produced_qty for the meter, due_date for the countdown, priority)
        // have to be here or the panel silently shows a different table.
        //
        // Capped rather than unbounded: this is an Inertia prop, not the synced
        // shape the list page rides on, and a line with a decade of orders would
        // put all of them in the page payload. The header says when it truncated.
        // `select()` before `withCount()`, not columns passed to `get()`:
        // withCount sets `work_orders.*` on the query itself, and Query\Builder
        // only honours the columns given to get() while none are set — so the
        // list below was silently ignored and every column (custom_fields,
        // description) went into the Inertia payload.
        $workOrders = $line->workOrders()
            ->select([
                'id', 'line_id', 'order_no', 'product_type_id', 'planned_qty', 'produced_qty',
                'status', 'priority', 'due_date', 'created_at',
            ])
            ->withCount('batches')
            ->orderBy('created_at', 'desc')
            ->limit(self::WORK_ORDER_PANEL_LIMIT)
            ->get();

        $availableOperators = \App\Models\User::role('Operator')
            ->whereNotIn('id', $line->users->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $lineStatuses = LineStatus::forLine($line->id)->get();
        $allProductTypes = ProductType::active()->orderBy('name')->get(['id', 'code', 'name']);
        $assignedTypeIds = $line->productTypes->pluck('id')->toArray();
        $viewColumns = $line->viewColumns;
        $allViewTemplates = \App\Models\ViewTemplate::orderBy('name')->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'columns_count' => count($t->columns ?? []),
        ]);

        // Sorted by code so the table reads the same way the shop floor is
        // labelled — the relation's own order is insertion order, which isn't one.
        $effectiveWorkstations = $line->effectiveWorkstations()->sortBy('code')->values();

        // Current machine state per workstation, in one query. Same "latest slice
        // that hasn't ended" rule as MachineMonitorService::fleetStatus(), minus
        // its OEE maths — the table only needs the state word.
        $states = WorkstationState::whereIn('workstation_id', $line->workstations->pluck('id'))
            ->whereNull('ended_at')
            // Three columns and the ordering done in SQL: the table also carries
            // a `metadata` JSON telemetry snapshot, which was being fetched for
            // every open slice only to be sorted and thrown away in PHP.
            ->orderByDesc('started_at')
            ->get(['workstation_id', 'state', 'started_at'])
            ->groupBy('workstation_id')
            ->map(fn ($slices) => $slices->first()?->state);

        // Who is standing at each workstation. The virtual line-as-workstation
        // entry has no id, so it falls through to null on both lookups.
        $operators = $line->workstations->mapWithKeys(
            fn ($ws) => [$ws->id => $ws->workers->pluck('name')->all()]
        );

        return Inertia::render('admin/lines/Show', [
            'line' => array_merge(
                $line->only('id', 'code', 'name', 'description', 'is_active', 'default_operator_view', 'view_template_id', 'custom_fields'),
                [
                    'workstations_count' => $line->workstations_count,
                    'work_orders_count' => $line->work_orders_count,
                    'users_count' => $line->users_count,
                    'users' => $line->users->map(fn ($u) => $u->only('id', 'name', 'username'))->values(),
                    'product_types' => $line->productTypes->map(fn ($p) => $p->only('id', 'code', 'name'))->values(),
                ]
            ),
            'workOrders' => $workOrders->map(fn ($wo) => [
                'id' => $wo->id,
                'order_no' => $wo->order_no,
                'product_type_id' => $wo->product_type_id,
                'planned_qty' => $wo->planned_qty,
                'produced_qty' => $wo->produced_qty,
                'status' => $wo->status,
                'priority' => $wo->priority,
                'due_date' => $wo->due_date,
                'created_at' => $wo->created_at,
            ])->values(),
            // Lookups the shared work-order columns render from — narrowed to the
            // orders actually on this page. `pluck('name', 'id')` over the whole
            // table put the entire catalogue in the payload of every line detail
            // page, to name at most a hundred orders.
            'productTypeNames' => ProductType::whereIn(
                'id', $workOrders->pluck('product_type_id')->filter()->unique()
            )->pluck('name', 'id'),
            'batchCounts' => $workOrders->mapWithKeys(fn ($wo) => [$wo->id => $wo->batches_count]),
            'availableOperators' => $availableOperators->map(fn ($u) => $u->only('id', 'name', 'username'))->values(),
            'lineStatuses' => $lineStatuses->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'is_default' => $s->is_default,
                'line_id' => $s->line_id,
            ])->values(),
            'allProductTypes' => $allProductTypes->map(fn ($p) => $p->only('id', 'code', 'name'))->values(),
            'assignedTypeIds' => $assignedTypeIds,
            'viewColumns' => $viewColumns->map(fn ($c) => $c->only('id', 'label', 'key', 'source', 'sort_order'))->values(),
            'allViewTemplates' => $allViewTemplates->values(),
            'effectiveWorkstations' => collect($effectiveWorkstations)->map(fn ($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'code' => $ws->code,
                'is_line_itself' => $ws->is_line_itself ?? false,
                'state' => $ws->id === null ? null : $states->get($ws->id),
                'operators' => $ws->id === null ? [] : $operators->get($ws->id, []),
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
            'line' => $line->only('id', 'code', 'name', 'description', 'area_id', 'warehouse_id', 'is_active', 'custom_fields'),
            'areas' => $this->areaOptions(),
            'warehouses' => $this->warehouseOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('line'),
        ]);
    }

    /**
     * Update the specified line
     */
    public function update(UpdateLineRequest $request, Line $line)
    {
        $cf = app(CustomFieldService::class);
        $validated = $request->validated();

        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'line', $line->custom_fields) ?: null;
        }

        $line->update($validated);

        return $this->saved($request, redirect()->route('admin.lines.index'), 'Production line updated successfully.');
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
        $line->update(['is_active' => ! $line->is_active]);

        $status = $line->is_active ? 'activated' : 'deactivated';

        // Back to wherever the toggle was pressed — the list has a row action and
        // the detail page has a header button, and bouncing the latter to the
        // list threw away the page the user was configuring.
        return back()->with('success', "Production line {$status} successfully.");
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
        if (! $user->hasRole('Operator')) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Only operators can be assigned to production lines.');
        }

        // Check if already assigned
        if ($line->users()->where('user_id', $user->id)->exists()) {
            return redirect()->route('admin.lines.show', $line)
                ->with('error', 'Operator is already assigned to this line.');
        }

        $line->users()->attach($user->id);

        // Module hook: a user was assigned to a production line.
        \App\Events\User\UserAssignedToLine::dispatch($user, $line);

        return redirect()->route('admin.lines.show', $line)
            ->with('success', "Operator {$user->name} assigned successfully.");
    }

    /**
     * Sync assigned product types for a line
     */
    public function syncProductTypes(Request $request, Line $line)
    {
        $validated = $request->validate([
            'product_type_ids' => 'nullable|array',
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

        return back()->with('success', 'Default operator view set to '.ucfirst($validated['default_operator_view']).'.');
    }

    /**
     * Save workstation view columns for a line.
     */
    public function saveViewColumns(Request $request, Line $line)
    {
        $validated = $request->validate([
            'columns' => 'nullable|array|max:20',
            'columns.*.label' => 'required|string|max:100',
            'columns.*.key' => 'required|string|max:100',
            'columns.*.source' => 'required|in:extra_data,field',
        ]);

        $line->viewColumns()->delete();

        foreach (($validated['columns'] ?? []) as $i => $col) {
            $line->viewColumns()->create([
                'label' => $col['label'],
                'key' => $col['key'],
                'source' => $col['source'],
                'sort_order' => $i,
            ]);
        }

        return back()->with('success', 'Workstation view columns saved.');
    }
}
