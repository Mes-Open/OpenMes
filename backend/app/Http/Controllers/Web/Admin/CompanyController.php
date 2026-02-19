<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index(Request $request)
    {
        $query = Company::orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $companies = $query->paginate(25)->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    /**
     * Show the form for creating a new company.
     */
    public function create()
    {
        return view('admin.companies.create');
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:companies',
            'name'        => 'required|string|max:255',
            'tax_id'      => 'nullable|string|max:50',
            'type'        => 'required|string|in:supplier,customer,both',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'address'     => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Company::create($validated);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company created successfully.');
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:companies,code,' . $company->id,
            'name'        => 'required|string|max:255',
            'tax_id'      => 'nullable|string|max:50',
            'type'        => 'required|string|in:supplier,customer,both',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'address'     => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $company->update($validated);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified company.
     */
    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Toggle company active status.
     */
    public function toggleActive(Company $company)
    {
        $company->update(['is_active' => ! $company->is_active]);

        $status = $company->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.companies.index')
            ->with('success', "Company {$status} successfully.");
    }
}
