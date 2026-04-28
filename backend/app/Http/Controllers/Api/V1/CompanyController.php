<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $query = Company::query();
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);
        return response()->json(['data' => $company]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:supplier,customer,both'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $c = Company::create($data);
        return response()->json(['message' => 'Company created', 'data' => $c], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($company->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'required', 'in:supplier,customer,both'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $company->update($data);
        return response()->json(['message' => 'Company updated', 'data' => $company->fresh()]);
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);
        $company->delete();
        return response()->json(['message' => 'Company deleted']);
    }

    public function toggleActive(Company $company): JsonResponse
    {
        $this->authorize('update', $company);
        $company->update(['is_active' => !$company->is_active]);
        return response()->json(['message' => $company->is_active ? 'Activated' : 'Deactivated', 'data' => $company]);
    }
}
