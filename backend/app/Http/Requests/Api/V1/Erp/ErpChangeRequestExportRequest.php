<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the change-request feed. An ERP polls `?since=<last sync>` to learn
 * which of its requests were approved, rejected, applied or cancelled.
 * Authorization is handled by the auth.apikey + scope middleware.
 */
class ErpChangeRequestExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(ChangeRequestStatus::values())],
            'type' => ['nullable', Rule::in(ChangeRequestType::values())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->input('limit', 100);
    }
}
