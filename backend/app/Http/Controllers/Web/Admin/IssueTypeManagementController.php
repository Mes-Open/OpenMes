<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use Illuminate\Http\Request;

class IssueTypeManagementController extends Controller
{
    public function index()
    {
        $issueTypes = IssueType::withCount('issues')->orderBy('name')->get();

        return view('admin.issue-types.index', compact('issueTypes'));
    }

    public function create()
    {
        return view('admin.issue-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:issue_types,code',
            'name'        => 'required|string|max:100',
            'severity'    => 'required|in:LOW,MEDIUM,HIGH,CRITICAL',
            'is_blocking' => 'boolean',
        ]);

        $validated['is_blocking'] = $request->boolean('is_blocking');
        $validated['is_active']   = true;

        IssueType::create($validated);

        return redirect()->route('admin.issue-types.index')
            ->with('success', "Issue type \"{$validated['name']}\" created.");
    }

    public function edit(IssueType $issueType)
    {
        return view('admin.issue-types.edit', compact('issueType'));
    }

    public function update(Request $request, IssueType $issueType)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:issue_types,code,' . $issueType->id,
            'name'        => 'required|string|max:100',
            'severity'    => 'required|in:LOW,MEDIUM,HIGH,CRITICAL',
            'is_blocking' => 'boolean',
        ]);

        $validated['is_blocking'] = $request->boolean('is_blocking');

        $issueType->update($validated);

        return redirect()->route('admin.issue-types.index')
            ->with('success', "Issue type \"{$issueType->name}\" updated.");
    }

    public function destroy(IssueType $issueType)
    {
        if ($issueType->issues()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete an issue type that has existing issues.');
        }

        $name = $issueType->name;
        $issueType->delete();

        return redirect()->route('admin.issue-types.index')
            ->with('success', "Issue type \"{$name}\" deleted.");
    }

    public function toggleActive(IssueType $issueType)
    {
        $issueType->update(['is_active' => !$issueType->is_active]);

        return redirect()->back()
            ->with('success', "Issue type \"{$issueType->name}\" " . ($issueType->is_active ? 'activated' : 'deactivated') . '.');
    }
}
