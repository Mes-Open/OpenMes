<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index()
    {
        return Inertia::render('admin/companies/Index');
    }

    /**
     * Show the form for creating a new company.
     */
    public function create()
    {
        return Inertia::render('admin/companies/Create');
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
        return Inertia::render('admin/companies/Edit', [
            'company' => $company->only('id', 'code', 'name', 'tax_id', 'type', 'email', 'phone', 'address', 'is_active'),
        ]);
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
