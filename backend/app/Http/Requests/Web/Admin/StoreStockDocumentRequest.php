<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\StockDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(StockDocument::TYPES)],
            // Omitted = the default warehouse for the document's kind.
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'lines.*.product_type_id' => ['nullable', 'integer', Rule::exists('product_types', 'id')->whereNull('deleted_at')],
            'lines.*.material_lot_id' => ['nullable', 'integer', Rule::exists('material_lots', 'id')->whereNull('deleted_at')],
            'lines.*.lot_number' => ['nullable', 'string', 'max:100'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A line must name the kind of item its document type moves — a material
     * release with a product line (or an empty line) would post nothing and look
     * like a silent success.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            if (! in_array($type, StockDocument::TYPES, true)) {
                return;
            }

            $expectsMaterial = in_array($type, [
                StockDocument::TYPE_MATERIAL_ISSUE,
                StockDocument::TYPE_MATERIAL_RECEIPT,
            ], true);

            foreach ((array) $this->input('lines', []) as $index => $line) {
                $field = $expectsMaterial ? 'material_id' : 'product_type_id';

                if (empty($line[$field])) {
                    $validator->errors()->add(
                        "lines.{$index}.{$field}",
                        $expectsMaterial
                            ? __('Pick a material for this line.')
                            : __('Pick a product for this line.'),
                    );
                }
            }
        });
    }
}
