<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    /**
     * List sites with filters.
     */
    public function index(Request $request)
    {
        $query = Site::with('company')
            ->withCount(['areas', 'lines'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sites     = $query->paginate(25)->withQueryString();
        $companies = Company::active()->orderBy('name')->get();

        return view('admin.sites.index', compact('sites', 'companies'));
    }

    public function create()
    {
        $companies = Company::active()->orderBy('name')->get();
        return view('admin.sites.create', compact('companies'));
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

        return view('admin.sites.show', compact('site'));
    }

    public function edit(Site $site)
    {
        $companies = Company::active()->orderBy('name')->get();
        return view('admin.sites.edit', compact('site', 'companies'));
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
