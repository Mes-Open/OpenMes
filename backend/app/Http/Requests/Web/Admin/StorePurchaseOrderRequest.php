<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && ($user->hasRole('Admin') || $user->can('tab:purchasing'));
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                Rule::exists('companies', 'id')->where(
                    fn ($query) => $query->whereIn('type', [Company::TYPE_SUPPLIER, Company::TYPE_BOTH])
                ),
            ],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => [
                'required',
                Rule::exists('materials', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'lines.*.quantity_ordered' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => '请选择供应商。',
            'supplier_id.exists' => '所选供应商不存在或不是供应商类型。',
            'lines.required' => '采购订单至少需要一行物料。',
            'lines.*.material_id.required' => '请选择物料。',
            'lines.*.material_id.exists' => '所选物料不存在或已停用。',
            'lines.*.quantity_ordered.required' => '请填写订购数量。',
            'lines.*.quantity_ordered.min' => '订购数量必须大于 0。',
            'expected_at.after_or_equal' => '预计到货日期不能早于下单日期。',
            'lines.*.expected_at.after_or_equal' => '行预计到货日期不能早于下单日期。',
        ];
    }
}
