<?php

namespace App\Import\Contracts;

/**
 * One entity the unified importer (Admin → Import) can load from a file.
 *
 * An importer describes itself — fields, what identifies a row, the options its
 * run takes, the warnings the screen shows, a sample file — and turns canonical
 * rows into writes. The file reading, column mapping, queueing and progress
 * reporting are shared; an importer only knows its own rows, exactly the way the
 * ERP JSON services it wraps do.
 */
interface EntityImporter
{
    /** Registry key, also the value stored in csv_imports.entity — e.g. `product_types`. */
    public function key(): string;

    /** URL slug — e.g. `product-types`. */
    public function slug(): string;

    public function label(): string;

    /** One line under the entity name on the import screen. */
    public function description(): string;

    /**
     * Importable fields, in display order.
     *
     * @return array<string, array{label: string, required: bool, type: string, description?: string, aliases?: list<string>}>
     *                                                                                                                         type: text | number | integer | bool | date
     */
    public function fields(): array;

    /**
     * Any-of groups of fields that identify a row, on top of the required ones:
     * `[['code'], ['external_code']]` means at least one of them must be mapped.
     * Empty when the required fields already identify the row.
     *
     * @return list<list<string>>
     */
    public function identifierGroups(): array;

    /** Whether `custom:<key>` mappings are accepted (stored on the row's extra_data). */
    public function allowsCustomFields(): bool;

    /**
     * Run options the screen renders and the Form Requests validate.
     *
     * @return list<array<string, mixed>> items: {key, type: select|line|number|text|switch, label, default?, choices?, min?, max?, maxLength?, pattern?, help?, visibleWhen?}
     */
    public function options(): array;

    /**
     * Laravel rules for the options, keyed `options.<key>`.
     *
     * @return array<string, mixed>
     */
    public function optionRules(): array;

    /** @return list<string> notes shown above the file picker */
    public function warnings(): array;

    /** @return array{headers: list<string>, rows: list<list<string>>} */
    public function sample(): array;

    /** Rows per import() call; null = the whole file in one call. */
    public function chunkSize(): ?int;

    /**
     * @param  list<array<string, mixed>>  $rows  canonical rows (field => typed value; custom keys under `custom`)
     * @param  array<string, mixed>  $options  validated run options
     * @return array{imported: int, updated: int, skipped: int, errors: list<array{row: int, field: string|null, message: string}>}
     *                                                                                                                              `row` is the 1-based index into $rows
     */
    public function import(array $rows, array $options): array;
}
