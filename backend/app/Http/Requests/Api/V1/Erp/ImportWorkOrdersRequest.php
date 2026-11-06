<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Rules\ErpExtraData;
use App\Support\CurrencyRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the shape and types of an ERP bulk work-order import payload.
 *
 * Deliberately does NOT validate that line_code / product_type_code resolve to
 * real records: those are checked per row by WorkOrderImportService so one bad
 * reference produces a structured per-row error instead of failing the whole
 * batch with a 422. This request only rejects malformed payloads outright.
 *
 * The source fields (external_system, external_ref, customer_name,
 * price_currency, extra_data) are optional; an absent or null field leaves the
 * stored value untouched on update. price_currency must be a code from
 * CurrencyRegistry, and extra_data is limited by ErpExtraData.
 *
 * Authorization is handled upstream by the auth.apikey + scope middleware.
 */
class ImportWorkOrdersRequest extends FormRequest
{
    /** Upper bound on orders per request (paired with the erp-import rate limit). */
    public const MAX_ORDERS = 1000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['nullable', Rule::in(['update_or_create', 'skip_existing', 'error_on_duplicate'])],
            'orders' => ['required', 'array', 'min:1', 'max:'.self::MAX_ORDERS],
            'orders.*.order_no' => ['required', 'string', 'max:100'],
            'orders.*.line_code' => ['required', 'string', 'max:100'],
            'orders.*.product_type_code' => ['required', 'string', 'max:100'],
            'orders.*.planned_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'orders.*.customer_order_no' => ['nullable', 'string', 'max:100'],
            'orders.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'orders.*.priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'orders.*.due_date' => ['nullable', 'date'],
            'orders.*.description' => ['nullable', 'string', 'max:2000'],
            'orders.*.external_system' => ['nullable', 'string', 'max:50'],
            'orders.*.external_ref' => ['nullable', 'string', 'max:100'],
            'orders.*.customer_name' => ['nullable', 'string', 'max:255'],
            'orders.*.price_currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyRegistry::codes())],
            'orders.*.extra_data' => ['nullable', 'array', new ErpExtraData],
        ];
    }

    /** Accept "eur" / " EUR " the same way the currency setting does. */
    protected function prepareForValidation(): void
    {
        $orders = $this->input('orders');
        if (! is_array($orders)) {
            return; // the `array` rule reports it
        }

        foreach ($orders as $i => $order) {
            if (is_array($order) && isset($order['price_currency']) && is_string($order['price_currency'])) {
                $orders[$i]['price_currency'] = CurrencyRegistry::normalise($order['price_currency']);
            }
        }

        $this->merge(['orders' => $orders]);
    }

    public function strategy(): string
    {
        return $this->input('strategy', 'update_or_create');
    }
}
