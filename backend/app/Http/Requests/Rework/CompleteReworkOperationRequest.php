<?php

namespace App\Http\Requests\Rework;

use App\Models\ReworkOperation;
use App\Rules\WholePieces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * End a rework (#400): how many pieces came back good and how many were
 * scrapped. Anyone who may see the work order may complete it.
 */
class CompleteReworkOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rework = $this->route('rework');

        return $rework instanceof ReworkOperation
            && (bool) $this->user()?->can('view', $rework->workOrder);
    }

    public function rules(): array
    {
        $rework = $this->route('rework');
        $productType = $rework instanceof ReworkOperation ? $rework->workOrder?->productType : null;

        return [
            'accepted_qty' => ['required', 'numeric', 'min:0', 'max:9999999', new WholePieces($productType)],
            'scrapped_qty' => ['required', 'numeric', 'min:0', 'max:9999999', new WholePieces($productType)],
            'scrap_reason_id' => ['nullable', 'bail', 'integer', Rule::exists('scrap_reasons', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
