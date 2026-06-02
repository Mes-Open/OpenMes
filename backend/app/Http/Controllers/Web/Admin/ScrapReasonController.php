<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrapReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrapReasonController extends Controller
{
    /**
     * Display a listing of scrap reasons.
     */
    public function index(Request $request)
    {
        $query = ScrapReason::withCount('scrapEntries')
            ->orderBy('is_active', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $scrapReasons = $query->paginate(25)->withQueryString();

        return view('admin.scrap-reasons.index', [
            'scrapReasons' => $scrapReasons,
            'categories' => ScrapReason::CATEGORIES,
            'category' => $category,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new scrap reason.
     */
    public function create()
    {
        return view('admin.scrap-reasons.create', [
            'categories' => ScrapReason::CATEGORIES,
        ]);
    }

    /**
     * Store a newly created scrap reason.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:scrap_reasons,code',
            'name'        => 'required|string|max:255',
            'category'    => ['required', Rule::in(ScrapReason::CATEGORIES)],
            'description' => 'nullable|string|max:2000',
            'sort_order'  => 'nullable|integer|min:0|max:65535',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        ScrapReason::create($validated);

        return redirect()->route('admin.scrap-reasons.index')
            ->with('success', __('Scrap reason created successfully.'));
    }

    /**
     * Show the form for editing a scrap reason.
     */
    public function edit(ScrapReason $scrapReason)
    {
        return view('admin.scrap-reasons.edit', [
            'scrapReason' => $scrapReason,
            'categories' => ScrapReason::CATEGORIES,
        ]);
    }

    /**
     * Update the specified scrap reason.
     */
    public function update(Request $request, ScrapReason $scrapReason)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:20', Rule::unique('scrap_reasons', 'code')->ignore($scrapReason->id)],
            'name'        => 'required|string|max:255',
            'category'    => ['required', Rule::in(ScrapReason::CATEGORIES)],
            'description' => 'nullable|string|max:2000',
            'sort_order'  => 'nullable|integer|min:0|max:65535',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $scrapReason->update($validated);

        return redirect()->route('admin.scrap-reasons.index')
            ->with('success', __('Scrap reason updated successfully.'));
    }

    /**
     * Remove the specified scrap reason.
     */
    public function destroy(ScrapReason $scrapReason)
    {
        if ($scrapReason->scrapEntries()->exists()) {
            return redirect()->route('admin.scrap-reasons.index')
                ->with('error', __('Cannot delete scrap reason with existing entries. Deactivate it instead.'));
        }

        $scrapReason->delete();

        return redirect()->route('admin.scrap-reasons.index')
            ->with('success', __('Scrap reason deleted successfully.'));
    }

    /**
     * Toggle scrap reason active status.
     */
    public function toggleActive(ScrapReason $scrapReason)
    {
        $scrapReason->update(['is_active' => ! $scrapReason->is_active]);

        $status = $scrapReason->is_active ? __('activated') : __('deactivated');

        return redirect()->route('admin.scrap-reasons.index')
            ->with('success', __('Scrap reason :status successfully.', ['status' => $status]));
    }
}
