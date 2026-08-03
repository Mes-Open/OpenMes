<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\SharesMasterDataRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an ERP product master-data payload (#212).
 *
 * Shape and types only: whether a row belongs in OpenMES at all is decided by
 * ProductImportService (category filter) and reported per row, so a filtered-out
 * item is a "skipped" row, not a 422.
 *
 * The envelope and the fields it shares with the material import live in
 * SharesMasterDataRules.
 *
 * Authorization is handled upstream by the auth.apikey + scope middleware.
 */
class ImportProductsRequest extends FormRequest
{
    use SharesMasterDataRules;

    /** Upper bound per request, paired with the erp-import rate limit. */
    public const MAX_ROWS = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->envelopeRules(),
            'products' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            ...$this->commonRowRules('products.*'),
        ];
    }
}
