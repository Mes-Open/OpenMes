<?php

namespace App\Http\Requests\Web\Admin\Import\Concerns;

use App\Import\Contracts\EntityImporter;
use App\Import\ImportRegistry;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Validation\Rule;

/**
 * What the upload and the process step of the unified importer share: the file
 * reading options and the entity's own run options, whose rules the importer
 * declares (EntityImporter::optionRules) so the Form Request stays the single
 * authority without knowing every entity.
 */
trait SharesImportRules
{
    protected function importer(): ?EntityImporter
    {
        $slug = (string) $this->route('entity');

        return $slug !== '' ? app(ImportRegistry::class)->fromSlug($slug) : null;
    }

    /** @return array<string, mixed> */
    protected function fileOptionRules(): array
    {
        return [
            'delimiter' => ['nullable', Rule::in(SpreadsheetReader::DELIMITERS)],
            'encoding' => ['nullable', Rule::in(SpreadsheetReader::ENCODINGS)],
        ];
    }

    /** @return array<string, mixed> */
    protected function entityOptionRules(): array
    {
        return [
            'options' => ['nullable', 'array'],
            ...($this->importer()?->optionRules() ?? []),
        ];
    }

    /** @return array<string, mixed> validated run options, blanks removed */
    public function runOptions(): array
    {
        return array_filter(
            (array) $this->validated('options', []),
            fn ($v) => $v !== null && $v !== '',
        );
    }
}
