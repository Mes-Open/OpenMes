<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteController extends Controller
{
    /**
     * List sites with filters.
     */
    public function index(Request $request)
    {
        $counts = \App\Models\Site::withCount(['areas', 'lines'])->get(['id'])->mapWithKeys(fn ($s) => [$s->id => ['areas' => $s->areas_count, 'lines' => $s->lines_count]]);
        $companyNames = \App\Models\Company::pluck('name', 'id');

        return Inertia::render('admin/sites/Index', ['counts' => $counts, 'companyNames' => $companyNames]);
    }

    public function create(CustomFieldService $cf)
    {
        $companies = \App\Models\Company::active()->orderBy('name')->get(['id', 'name']);
        return Inertia::render('admin/sites/Create', ['companies' => $companies, 'customFields' => $cf->clientConfig('site')]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['custom_field_files']);
        if (app(CustomFieldService::class)->touched($request)) {
            $validated['custom_fields'] = app(CustomFieldService::class)->fromRequest($request, 'site') ?: null;
        }

        Site::create($validated);

        return redirect()->route('admin.sites.index')
            ->with('success', 'Site created successfully.');
    }

    public function show(Site $site)
    {
        $site->load([
            'company',
            'areas' => function ($q) {
                $q->withCount('lines')->orderBy('name');
            },
            'lines' => function ($q) {
                $q->orderBy('name');
            },
        ]);

        return Inertia::render('admin/sites/Show', [
            'site' => array_merge(
                $site->only('id', 'code', 'name', 'description', 'address', 'city', 'country', 'timezone', 'is_active', 'custom_fields'),
                [
                    'company' => $site->company ? $site->company->only('id', 'name') : null,
                    'areas' => $site->areas->map(fn ($a) => array_merge(
                        $a->only('id', 'code', 'name', 'is_active'),
                        ['lines_count' => $a->lines_count],
                    )),
                    'lines' => $site->lines->map(fn ($l) => $l->only('id', 'code', 'name', 'is_active')),
                ],
            ),
            'customFields' => app(CustomFieldService::class)->clientConfig('site'),
        ]);
    }

    public function edit(Site $site, CustomFieldService $cf)
    {
        $companies = \App\Models\Company::active()->orderBy('name')->get(['id', 'name']);
        return Inertia::render('admin/sites/Edit', ['site' => $site->only('id', 'company_id', 'code', 'name', 'description', 'address', 'city', 'country', 'timezone', 'is_active', 'custom_fields'), 'companies' => $companies, 'customFields' => $cf->clientConfig('site')]);
    }

    public function update(Request $request, Site $site)
    {
        $validated = $this->validatePayload($request, $site);

        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['custom_field_files']);
        if (app(CustomFieldService::class)->touched($request)) {
            $validated['custom_fields'] = app(CustomFieldService::class)->fromRequest($request, 'site', $site->custom_fields) ?: null;
        }

        $site->update($validated);

        return redirect()->route('admin.sites.index')
            ->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site)
    {
        if ($site->areas()->count() > 0) {
            return redirect()->route('admin.sites.index')
                ->with('error', 'Cannot delete site with existing areas. Deactivate it instead.');
        }

        try {
            $site->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.sites.index')
                ->with('error', 'Cannot delete: this site is still referenced elsewhere. Deactivate it instead.');
        }

        return redirect()->route('admin.sites.index')
            ->with('success', 'Site deleted successfully.');
    }

    public function toggleActive(Site $site)
    {
        $site->update(['is_active' => ! $site->is_active]);

        $status = $site->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.sites.index')
            ->with('success', "Site {$status} successfully.");
    }

    private function validatePayload(Request $request, ?Site $site = null): array
    {
        $codeRule = 'required|string|max:50|unique:sites,code';
        if ($site) {
            $codeRule .= ','.$site->id;
        }

        $cf = app(CustomFieldService::class);

        return $request->validate(array_merge([
            'name'        => 'required|string|max:255',
            'code'        => $codeRule,
            'company_id'  => 'nullable|exists:companies,id',
            'description' => 'nullable|string|max:2000',
            'address'     => 'nullable|string|max:500',
            'city'        => 'nullable|string|max:100',
            'country'     => 'nullable|string|size:2',
            'timezone'    => 'nullable|string|max:50',
            'is_active'   => 'nullable|boolean',
        ], $cf->rules('site')), [], $cf->attributeNames('site'));
    }
}
