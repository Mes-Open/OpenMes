<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Site;
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

    public function create()
    {
        $companies = \App\Models\Company::active()->orderBy('name')->get(['id', 'name']);
        return Inertia::render('admin/sites/Create', ['companies' => $companies]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $validated['is_active'] = $request->boolean('is_active', true);

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
                $site->only('id', 'code', 'name', 'description', 'address', 'city', 'country', 'timezone', 'is_active'),
                [
                    'company' => $site->company ? $site->company->only('id', 'name') : null,
                    'areas' => $site->areas->map(fn ($a) => array_merge(
                        $a->only('id', 'code', 'name', 'is_active'),
                        ['lines_count' => $a->lines_count],
                    )),
                    'lines' => $site->lines->map(fn ($l) => $l->only('id', 'code', 'name', 'is_active')),
                ],
            ),
        ]);
    }

    public function edit(Site $site)
    {
        $companies = \App\Models\Company::active()->orderBy('name')->get(['id', 'name']);
        return Inertia::render('admin/sites/Edit', ['site' => $site->only('id', 'company_id', 'code', 'name', 'description', 'address', 'city', 'country', 'timezone', 'is_active'), 'companies' => $companies]);
    }

    public function update(Request $request, Site $site)
    {
        $validated = $this->validatePayload($request, $site);

        $validated['is_active'] = $request->boolean('is_active');

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

        $site->delete();

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
            $codeRule .= ',' . $site->id;
        }

        return $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => $codeRule,
            'company_id'  => 'nullable|exists:companies,id',
            'description' => 'nullable|string|max:2000',
            'address'     => 'nullable|string|max:500',
            'city'        => 'nullable|string|max:100',
            'country'     => 'nullable|string|size:2',
            'timezone'    => 'nullable|string|max:50',
            'is_active'   => 'nullable|boolean',
        ]);
    }
}
