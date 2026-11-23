<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFlavorStandard;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerFlavorStandardController extends Controller
{
    use StaysOnList;

    private const BASE_PATH = '/admin/customer-flavor-standards';

    public function index()
    {
        $standards = CustomerFlavorStandard::query()
            ->with(['customer:id,name,code', 'productType:id,name,code'])
            ->orderByDesc('effective_from')
            ->orderBy('customer_id')
            ->orderBy('code')
            ->get()
            ->map(fn (CustomerFlavorStandard $standard) => [
                'id' => $standard->id,
                'code' => $standard->code,
                'name' => $standard->name,
                'version' => $standard->version,
                'status' => $standard->status,
                'customer_id' => $standard->customer_id,
                'customer_name' => $standard->customer?->name,
                'customer_code' => $standard->customer?->code,
                'product_type_id' => $standard->product_type_id,
                'product_name' => $standard->productType?->name ?? '全部产品',
                'product_code' => $standard->productType?->code,
                'effective_from' => $standard->effective_from?->toDateString(),
                'effective_to' => $standard->effective_to?->toDateString(),
                'moisture_min' => $standard->moisture_min !== null ? (float) $standard->moisture_min : null,
                'moisture_max' => $standard->moisture_max !== null ? (float) $standard->moisture_max : null,
                'sensory_aroma' => $standard->sensory_aroma,
                'sensory_taste' => $standard->sensory_taste,
                'sensory_liquor' => $standard->sensory_liquor,
                'blend_tolerance' => $standard->blend_tolerance !== null ? (float) $standard->blend_tolerance : null,
                'foreign_matter_required' => $standard->foreign_matter_required,
                'net_weight_min' => $standard->net_weight_min !== null ? (float) $standard->net_weight_min : null,
                'net_weight_max' => $standard->net_weight_max !== null ? (float) $standard->net_weight_max : null,
                'packaging_requirement' => $standard->packaging_requirement,
                'notes' => $standard->notes,
                'parameters_count' => count($standard->parameters()),
                'updated_at' => $standard->updated_at?->toDateTimeString(),
            ])
            ->values();

        return Inertia::render('admin/customer-flavor-standards/Index', [
            'rows' => $standards,
            'customers' => Customer::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Customer $customer) => [
                    'value' => $customer->id,
                    'label' => $customer->code ? "{$customer->name}（{$customer->code}）" : $customer->name,
                ]),
            'productTypes' => ProductType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (ProductType $productType) => [
                    'value' => $productType->id,
                    'label' => $productType->code ? "{$productType->name}（{$productType->code}）" : $productType->name,
                ]),
            'basePath' => self::BASE_PATH,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['status'] = $validated['status'] ?? CustomerFlavorStandard::STATUS_ACTIVE;
        $validated['foreign_matter_required'] = $request->boolean('foreign_matter_required', true);
        $validated['created_by_id'] = $request->user()?->id;
        $validated['approved_by_id'] = $request->user()?->id;
        $validated['approved_at'] = now();
        $validated['effective_from'] = $validated['effective_from'] ?? now()->toDateString();

        CustomerFlavorStandard::create($validated);

        return $this->saved($request, redirect()->to(self::BASE_PATH),
            __('客户风味标准已创建。'));
    }

    public function update(Request $request, CustomerFlavorStandard $customerFlavorStandard)
    {
        $validated = $this->validated($request, $customerFlavorStandard);
        $validated['status'] = $validated['status'] ?? $customerFlavorStandard->status;
        $validated['foreign_matter_required'] = $request->boolean('foreign_matter_required');

        $customerFlavorStandard->update($validated);

        return $this->saved($request, redirect()->to(self::BASE_PATH),
            __('客户风味标准已更新。'));
    }

    public function destroy(CustomerFlavorStandard $customerFlavorStandard)
    {
        $customerFlavorStandard->delete();

        return redirect()->to(self::BASE_PATH)
            ->with('success', __('客户风味标准已删除。'));
    }

    public function toggleStatus(CustomerFlavorStandard $customerFlavorStandard)
    {
        $customerFlavorStandard->update([
            'status' => $customerFlavorStandard->status === CustomerFlavorStandard::STATUS_ACTIVE
                ? CustomerFlavorStandard::STATUS_RETIRED
                : CustomerFlavorStandard::STATUS_ACTIVE,
        ]);

        $status = $customerFlavorStandard->status === CustomerFlavorStandard::STATUS_ACTIVE ? '启用' : '停用';

        return redirect()->to(self::BASE_PATH)
            ->with('success', __('客户风味标准已:status。', ['status' => $status]));
    }

    private function validated(Request $request, ?CustomerFlavorStandard $standard = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:80',
                Rule::unique('customer_flavor_standards', 'code')
                    ->where(fn ($query) => $query
                        ->where('version', $request->input('version', 'V1'))
                        ->where('tenant_id', $request->user()?->tenant_id))
                    ->ignore($standard?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(CustomerFlavorStandard::STATUSES)],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'moisture_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'moisture_max' => ['nullable', 'numeric', 'min:0', 'max:100', 'gte:moisture_min'],
            'sensory_aroma' => ['nullable', 'string', 'max:255'],
            'sensory_taste' => ['nullable', 'string', 'max:255'],
            'sensory_liquor' => ['nullable', 'string', 'max:255'],
            'blend_tolerance' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'foreign_matter_required' => ['nullable', 'boolean'],
            'net_weight_min' => ['nullable', 'numeric', 'min:0'],
            'net_weight_max' => ['nullable', 'numeric', 'min:0', 'gte:net_weight_min'],
            'packaging_requirement' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
