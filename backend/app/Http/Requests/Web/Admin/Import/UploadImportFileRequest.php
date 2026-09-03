<?php

namespace App\Http\Requests\Web\Admin\Import;

use App\Http\Requests\Web\Admin\Import\Concerns\SharesImportRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImportFileRequest extends FormRequest
{
    use SharesImportRules;

    /** Upload ceiling in kilobytes (32 MB) — the old importers' limit, kept. */
    public const MAX_KB = 32768;

    public function authorize(): bool
    {
        // Route middleware (tab matrix / section role) is the gate.
        return true;
    }

    public function rules(): array
    {
        $importer = $this->importer();

        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:'.self::MAX_KB],
            'mapping_id' => [
                'nullable',
                'integer',
                Rule::exists('csv_import_mappings', 'id')
                    ->where('entity', $importer?->key() ?? '')
                    ->whereNull('deleted_at'),
            ],
            ...$this->fileOptionRules(),
            ...$this->entityOptionRules(),
        ];
    }
}
