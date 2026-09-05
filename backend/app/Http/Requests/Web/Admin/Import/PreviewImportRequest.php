<?php

namespace App\Http\Requests\Web\Admin\Import;

use App\Http\Requests\Web\Admin\Import\Concerns\SharesImportRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-reading an upload the session already holds, with different parse
 * settings. Same file-option rules as the upload step — the preview must not
 * accept a delimiter or encoding the real run would reject.
 */
class PreviewImportRequest extends FormRequest
{
    use SharesImportRules;

    public function authorize(): bool
    {
        // Route middleware (tab matrix / section role) is the gate.
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->fileOptionRules(),
            // The mapping is optional: the screen sends what the user has set so
            // far so the preview can mark cells the import would reject.
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'string', 'regex:/^(_ignore|[a-z][a-z0-9_]*|custom:[A-Za-z0-9_]{1,50})$/'],
            // The run options follow the same rules the upload step applies —
            // the preview persists them, so it must not accept a weaker set.
            ...$this->entityOptionRules(),
            'mapping_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array{delimiter: string, encoding: string} */
    public function fileOptions(): array
    {
        return [
            'delimiter' => (string) ($this->validated('delimiter') ?: 'auto'),
            'encoding' => (string) ($this->validated('encoding') ?: 'utf-8'),
        ];
    }

    /** @return array<string, string> header => target, `null` normalised to ignore */
    public function mapping(): array
    {
        return array_map(fn ($v) => (string) ($v ?? '_ignore'), (array) $this->validated('mapping', []));
    }
}
