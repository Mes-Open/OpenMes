<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Receive material against one open purchase order.
 *
 * The request validates shape only; the controller checks line ownership and
 * remaining quantity against the live posted receipts under a transaction lock.
 */
class StorePurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && (
            $user->hasRole('Admin')
            || $user->can('tab:purchasing')
            || $user->can('tab:warehouse')
        );
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'reference' => ['required', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required' => '请选择采购订单。',
            'warehouse_id.required' => '请选择收货仓库。',
            'reference.required' => '请填写供方送货单号。',
            'lines.required' => '请至少填写一行到货物料。',
            'lines.*.quantity.required' => '请填写到货数量。',
            'lines.*.quantity.min' => '到货数量必须大于 0。',
        ];
    }
}
