<?php

namespace App\Import;

use App\Import\Contracts\EntityImporter;
use App\Services\Import\RowMapper;

abstract class AbstractEntityImporter implements EntityImporter
{
    public const DEFAULT_CHUNK = 200;

    public function slug(): string
    {
        return str_replace('_', '-', $this->key());
    }

    public function identifierGroups(): array
    {
        return [];
    }

    public function allowsCustomFields(): bool
    {
        return false;
    }

    public function warnings(): array
    {
        return [];
    }

    public function chunkSize(): ?int
    {
        return self::DEFAULT_CHUNK;
    }

    /** @return list<string> */
    public function requiredFields(): array
    {
        return array_keys(array_filter($this->fields(), fn ($f) => ! empty($f['required'])));
    }

    /**
     * Fields the given mapping leaves unmapped but the importer needs: every
     * required field, plus one whole identifier group.
     *
     * @param  array<string, string>  $mapping  header => target
     * @return list<string> field keys (an identifier group is reported as "a | b")
     */
    public function missingIdentifiers(array $mapping): array
    {
        $mapped = array_values(array_filter(
            array_map('strval', $mapping),
            fn ($t) => $t !== '' && $t !== RowMapper::IGNORE
        ));

        $missing = array_values(array_diff($this->requiredFields(), $mapped));

        $groups = $this->identifierGroups();

        if ($groups !== []) {
            $satisfied = false;

            foreach ($groups as $group) {
                if (array_diff($group, $mapped) === []) {
                    $satisfied = true;
                    break;
                }
            }

            if (! $satisfied) {
                $missing[] = implode(' | ', array_map(fn ($g) => implode('+', $g), $groups));
            }
        }

        return $missing;
    }

    /**
     * Everything the screen needs to render this entity.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $fields = [];

        foreach ($this->fields() as $key => $field) {
            $fields[] = [
                'key' => $key,
                'label' => $field['label'],
                'required' => (bool) ($field['required'] ?? false),
                'type' => $field['type'] ?? 'text',
                'description' => $field['description'] ?? null,
                'aliases' => array_values(array_unique(array_merge(
                    [$key, str_replace('_', ' ', $key)],
                    $field['aliases'] ?? [],
                ))),
            ];
        }

        return [
            'key' => $this->key(),
            'slug' => $this->slug(),
            'label' => $this->label(),
            'description' => $this->description(),
            'fields' => $fields,
            'identifierGroups' => $this->identifierGroups(),
            'allowsCustomFields' => $this->allowsCustomFields(),
            'options' => $this->options(),
            'warnings' => $this->warnings(),
        ];
    }

    /**
     * @param  list<array{value: string, label: string, description?: string}>  $choices
     * @return array<string, mixed>
     */
    protected function strategyOption(array $choices, string $default = 'update_or_create'): array
    {
        return [
            'key' => 'strategy',
            'type' => 'select',
            'label' => __('Import strategy'),
            'default' => $default,
            'choices' => $choices,
        ];
    }

    /** @return list<array{value: string, label: string, description: string}> */
    protected function erpStrategyChoices(): array
    {
        return [
            ['value' => 'update_or_create', 'label' => __('Insert or update'), 'description' => __('Existing records are updated, new ones created. Recommended for ERP sync.')],
            ['value' => 'skip_existing', 'label' => __('Insert only'), 'description' => __('Records that already exist are skipped untouched.')],
            ['value' => 'error_on_duplicate', 'label' => __('Error on duplicates'), 'description' => __('A record that already exists is reported as a failed row.')],
        ];
    }

    /**
     * Comma-separated text option → list, trimmed and de-blanked.
     *
     * @return list<string>
     */
    protected function listOption(array $options, string $key): array
    {
        $raw = $options[$key] ?? '';

        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $raw))));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
