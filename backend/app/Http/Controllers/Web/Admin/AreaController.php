<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    /**
     * List areas (optionally scoped to a single site).
     *
     * Used by both nested routes (admin.sites.areas.index) and any flat list.
     */
    public function index(Request $request, ?Site $site = null)
    {
        $query = Area::with('site')
            ->withCount('lines')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($site && $site->exists) {
            $query->where('site_id', $site->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $areas = $query->paginate(25)->withQueryString();
        $sites = Site::active()->orderBy('name')->get();

        return view('admin.areas.index', compact('areas', 'sites', 'site'));
    }

    public function create(?Site $site = null)
    {
        $sites = Site::active()->orderBy('name')->get();
        return view('admin.areas.create', compact('sites', 'site'));
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

        Area::create($validated);

        if ($site && $site->exists) {
            return redirect()->route('admin.sites.show', $site)
                ->with('success', 'Area created successfully.');
        }

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area created successfully.');
    }

    public function show(Area $area)
    {
        $area->load([
            'site',
            'lines' => function ($q) {
                $q->withCount('workstations')->orderBy('name');
            },
        ]);

        return view('admin.areas.show', compact('area'));
    }

    public function edit(Area $area)
    {
        $sites = Site::active()->orderBy('name')->get();
        return view('admin.areas.edit', compact('area', 'sites'));
    }

    public function update(Request $request, Area $area)
    {
        $validated = $this->validatePayload($request, $area);

        $validated['is_active'] = $request->boolean('is_active');

        $area->update($validated);

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area updated successfully.');
    }

    public function destroy(Area $area)
    {
        if ($area->lines()->count() > 0) {
            return redirect()->route('admin.areas.index')
                ->with('error', 'Cannot delete area with assigned production lines. Reassign or deactivate them first.');
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

    private function validatePayload(Request $request, ?Area $area = null): array
    {
        return $request->validate([
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
        ]);
    }
}
