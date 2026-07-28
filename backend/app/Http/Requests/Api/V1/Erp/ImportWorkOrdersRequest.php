<?php

namespace App\Http\Requests\Api\V1\Erp;

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
        ];
    }

    public function strategy(): string
    {
        return $this->input('strategy', 'update_or_create');
    }
}
