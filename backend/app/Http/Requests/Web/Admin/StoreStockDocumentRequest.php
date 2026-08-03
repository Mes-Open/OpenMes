<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\MaterialLot;
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
     * Cross-field checks the per-field rules cannot express:
     *
     *  - a line must name the kind of item its document type moves (a material
     *    release with a product line would post nothing and look like a success),
     *  - and must NOT name the other kind, so a line can't be read two ways,
     *  - a lot must belong to the line's own material — otherwise a release could
     *    draw down an unrelated lot's quantity, which no later check would catch.
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

            $lines = (array) $this->input('lines', []);

            // One query for every lot referenced by the payload: lot id => material id.
            $lotOwners = MaterialLot::whereIn('id', array_filter(array_column($lines, 'material_lot_id')))
                ->pluck('material_id', 'id');

            foreach ($lines as $index => $line) {
                $field = $expectsMaterial ? 'material_id' : 'product_type_id';
                $forbidden = $expectsMaterial ? 'product_type_id' : 'material_id';

                if (empty($line[$field])) {
                    $validator->errors()->add(
                        "lines.{$index}.{$field}",
                        $expectsMaterial
                            ? __('Pick a material for this line.')
                            : __('Pick a product for this line.'),
                    );
                }

                if (! empty($line[$forbidden])) {
                    $validator->errors()->add(
                        "lines.{$index}.{$forbidden}",
                        $expectsMaterial
                            ? __('This document moves materials, not products.')
                            : __('This document moves products, not materials.'),
                    );
                }

                $lotId = $line['material_lot_id'] ?? null;

                if (! $expectsMaterial && ! empty($lotId)) {
                    $validator->errors()->add(
                        "lines.{$index}.material_lot_id",
                        __('A product line cannot carry a material lot.'),
                    );

                    continue;
                }

                if (! empty($lotId) && isset($lotOwners[$lotId])
                    && (int) $lotOwners[$lotId] !== (int) ($line['material_id'] ?? 0)) {
                    $validator->errors()->add(
                        "lines.{$index}.material_lot_id",
                        __('That lot belongs to a different material.'),
                    );
                }
            }
        });
    }
}
