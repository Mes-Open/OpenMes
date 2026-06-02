<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViewTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewTemplateController extends Controller
{
    public function index()
    {
        $counts = ViewTemplate::withCount('lines')->get(['id'])
            ->mapWithKeys(fn ($t) => [$t->id => $t->lines_count]);

        return Inertia::render('admin/view-templates/Index', ['counts' => $counts]);
    }

    public function create()
    {
        return Inertia::render('admin/view-templates/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:100|unique:view_templates,name',
            'description'          => 'nullable|string|max:500',
            'columns'              => 'required|array|min:1|max:20',
            'columns.*.label'      => 'required|string|max:100',
            'columns.*.key'        => 'required|string|max:100',
            'columns.*.source'     => 'required|in:extra_data,field',
        ]);

        ViewTemplate::create($validated);

        return redirect()->route('admin.view-templates.index')
            ->with('success', "View template \"{$validated['name']}\" created.");
    }

    public function edit(ViewTemplate $viewTemplate)
    {
        return Inertia::render('admin/view-templates/Edit', [
            'viewTemplate' => $viewTemplate->only('id', 'name', 'description', 'columns'),
        ]);
    }

    public function update(Request $request, ViewTemplate $viewTemplate)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:100|unique:view_templates,name,' . $viewTemplate->id,
            'description'          => 'nullable|string|max:500',
            'columns'              => 'required|array|min:1|max:20',
            'columns.*.label'      => 'required|string|max:100',
            'columns.*.key'        => 'required|string|max:100',
            'columns.*.source'     => 'required|in:extra_data,field',
        ]);

        $viewTemplate->update($validated);

        return redirect()->route('admin.view-templates.index')
            ->with('success', "View template \"{$validated['name']}\" updated.");
    }

    public function destroy(ViewTemplate $viewTemplate)
    {
        $name = $viewTemplate->name;
        $viewTemplate->delete();

        return redirect()->route('admin.view-templates.index')
            ->with('success', "View template \"{$name}\" deleted.");
    }
}
