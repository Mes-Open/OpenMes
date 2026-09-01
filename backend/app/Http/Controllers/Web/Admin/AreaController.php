<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Site;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AreaController extends Controller
{
    use StaysOnList;

    /**
     * List areas (optionally scoped to a single site).
     *
     * Used by both nested routes (admin.sites.areas.index) and any flat list.
     */
    public function index(Request $request, ?Site $site = null)
    {
        $counts = \App\Models\Area::withCount('lines')->get(['id'])->mapWithKeys(fn ($a) => [$a->id => $a->lines_count]);
        $siteNames = \App\Models\Site::pluck('name', 'id');

        return Inertia::render('admin/areas/Index', [
            'counts' => $counts,
            'siteNames' => $siteNames,
            // Option lists for the list page's create/edit drawer. Optional, so the
            // queries only run once someone opens it — most visits never do.
            'sites' => Inertia::optional(fn () => $this->siteOptions()),
            'customFields' => Inertia::optional(fn () => app(CustomFieldService::class)->clientConfig('area')),
        ]);
    }

    public function create(?Site $site = null)
    {
        return Inertia::render('admin/areas/Create', [
            'sites' => $this->siteOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('area'),
        ]);
    }

    public function store(Request $request, ?Site $site = null)
    {
        $payload = $request->all();
        if ($site && $site->exists) {
            $payload['site_id'] = $site->id;
            $request->merge(['site_id' => $site->id]);
        }

        $validated = $this->validatePayload($request);

        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['custom_field_files']);
        if (app(CustomFieldService::class)->touched($request)) {
            $validated['custom_fields'] = app(CustomFieldService::class)->fromRequest($request, 'area') ?: null;
        }

        Area::create($validated);

        $onward = $site && $site->exists
            ? redirect()->route('admin.sites.show', $site)
            : redirect()->route('admin.areas.index');

        return $this->saved($request, $onward, 'Area created successfully.');
    }

    public function show(Area $area)
    {
        $area->load([
            'site',
            'lines' => function ($q) {
                $q->withCount('workstations')->orderBy('name');
            },
        ]);

        return Inertia::render('admin/areas/Show', [
            'area' => array_merge(
                $area->only('id', 'code', 'name', 'description', 'is_active', 'custom_fields'),
                [
                    'site' => $area->site ? $area->site->only('id', 'name') : null,
                    'lines' => $area->lines->map(fn ($l) => array_merge(
                        $l->only('id', 'code', 'name', 'is_active'),
                        ['workstations_count' => $l->workstations_count],
                    )),
                ],
            ),
            'customFields' => app(CustomFieldService::class)->clientConfig('area'),
        ]);
    }

    public function edit(Area $area)
    {
        return Inertia::render('admin/areas/Edit', [
            'area' => $area->only('id', 'site_id', 'code', 'name', 'description', 'is_active', 'custom_fields'),
            'sites' => $this->siteOptions(),
            'customFields' => app(CustomFieldService::class)->clientConfig('area'),
        ]);
    }

    public function update(Request $request, Area $area)
    {
        $validated = $this->validatePayload($request, $area);

        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['custom_field_files']);
        if (app(CustomFieldService::class)->touched($request)) {
            $validated['custom_fields'] = app(CustomFieldService::class)->fromRequest($request, 'area', $area->custom_fields) ?: null;
        }

        $area->update($validated);

        return $this->saved($request, redirect()->route('admin.areas.index'), 'Area updated successfully.');
    }

    public function destroy(Area $area)
    {
        if ($area->lines()->count() > 0) {
            return redirect()->route('admin.areas.index')
                ->with('error', __('Cannot delete area with assigned production lines. Reassign or deactivate them first.'));
        }

        $area->delete();

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area deleted successfully.');
    }

    public function toggleActive(Area $area)
    {
        $area->update(['is_active' => ! $area->is_active]);

        $status = $area->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.areas.index')
            ->with('success', "Area {$status} successfully.");
    }

    /** Site dropdown for the create/edit form, wherever it's rendered. */
    private function siteOptions()
    {
        return Site::active()->orderBy('name')->get(['id', 'name']);
    }

    private function validatePayload(Request $request, ?Area $area = null): array
    {
        $cf = app(CustomFieldService::class);

        return $request->validate(array_merge([
            'site_id'     => ['required', 'exists:sites,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => [
                'required', 'string', 'max:50',
                Rule::unique('areas', 'code')
                    ->where(fn ($q) => $q->where('site_id', $request->input('site_id')))
                    ->ignore($area?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active'   => ['nullable', 'boolean'],
        ], $cf->rules('area')), [], $cf->attributeNames('area'));
    }
}
