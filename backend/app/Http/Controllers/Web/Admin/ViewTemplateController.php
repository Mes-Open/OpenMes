<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViewTemplate;
use Illuminate\Http\Request;

class ViewTemplateController extends Controller
{
    public function index()
    {
        $templates = ViewTemplate::withCount('lines')->orderBy('name')->get();

        return view('admin.view-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('admin.view-templates.create');
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
        return view('admin.view-templates.edit', compact('viewTemplate'));
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
