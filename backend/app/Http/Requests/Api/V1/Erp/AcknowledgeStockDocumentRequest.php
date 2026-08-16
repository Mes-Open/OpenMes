<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an ERP acknowledgement of a stock document (#212): the ERP's own
 * document number for the release / receipt it just booked. Optional — an ERP
 * that has no number to give can still mark the document as taken.
 */
class AcknowledgeStockDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'erp_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
