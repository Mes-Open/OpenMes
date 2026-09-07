<?php

namespace App\Http\Requests\Web\Admin\Import;

use App\Http\Requests\Web\Admin\Import\Concerns\SharesImportRules;
use Illuminate\Foundation\Http\FormRequest;

class ProcessImportRequest extends FormRequest
{
    use SharesImportRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The upload the session remembers; the file path never leaves the server.
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{32}$/'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['nullable', 'string', 'regex:/^(_ignore|[a-z][a-z0-9_]*|custom:[A-Za-z0-9_]{1,50})$/'],
            'save_mapping_name' => ['nullable', 'string', 'max:100'],
            // Validate-only run: everything happens, nothing is kept.
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    public function isDryRun(): bool
    {
        return (bool) $this->validated('dry_run', false);
    }

    /** @return array<string, string> header => target, `null` normalised to ignore */
    public function mapping(): array
    {
        return array_map(fn ($v) => (string) ($v ?? '_ignore'), (array) $this->validated('mapping', []));
    }
}
