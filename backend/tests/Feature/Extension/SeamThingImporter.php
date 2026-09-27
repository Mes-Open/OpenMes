<?php

namespace Tests\Feature\Extension;

use App\Import\AbstractEntityImporter;

/**
 * A test-only importer standing in for one a module contributes through the
 * `import.entities` filter. Its own file so the autoloader finds it whichever
 * test the registry instantiates it from.
 */
class SeamThingImporter extends AbstractEntityImporter
{
    public function key(): string
    {
        return 'seam_things';
    }

    public function label(): string
    {
        return 'Seam things';
    }

    public function description(): string
    {
        return 'A test-only entity.';
    }

    public function fields(): array
    {
        return ['name' => ['label' => 'Name', 'required' => true, 'type' => 'text']];
    }

    public function options(): array
    {
        return [];
    }

    public function optionRules(): array
    {
        return [];
    }

    public function sample(): array
    {
        return ['headers' => ['name'], 'rows' => [['a']]];
    }

    public function import(array $rows, array $options): array
    {
        return ['imported' => count($rows), 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
}
