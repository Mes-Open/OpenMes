<?php

namespace App\Import\Importers;

use App\Import\AbstractEntityImporter;
use App\Services\Erp\ProductImportService;

class ProductTypeImporter extends AbstractEntityImporter
{
    public function __construct(private ProductImportService $service) {}

    public function key(): string
    {
        return 'product_types';
    }

    public function label(): string
    {
        return __('Product types');
    }

    public function description(): string
    {
        return __('Finished products, identified by code. Existing codes are updated, new ones created.');
    }

    public function fields(): array
    {
        return [
            'code' => ['label' => __('Code'), 'required' => true, 'type' => 'text', 'description' => __('Unique product code (max 50 characters).'), 'aliases' => ['product code', 'product_code', 'sku', 'item code', 'symbol', 'kod', 'kod produktu', 'indeks']],
            'name' => ['label' => __('Name'), 'required' => false, 'type' => 'text', 'description' => __('Defaults to the code when empty.'), 'aliases' => ['product name', 'product', 'nazwa', 'nazwa produktu']],
            'description' => ['label' => __('Description'), 'required' => false, 'type' => 'text', 'aliases' => ['desc', 'opis']],
            'category' => ['label' => __('Category'), 'required' => false, 'type' => 'text', 'description' => __('ERP classification; used by the "only categories" filter.'), 'aliases' => ['classification', 'group', 'grupa', 'kategoria']],
            'unit_of_measure' => ['label' => __('Unit of measure'), 'required' => false, 'type' => 'text', 'description' => __('Defaults to pcs.'), 'aliases' => ['unit', 'uom', 'jm', 'jednostka']],
            'external_code' => ['label' => __('External code'), 'required' => false, 'type' => 'text', 'description' => __('The code the source system uses; defaults to the code.'), 'aliases' => ['erp code', 'external id', 'kod zewnętrzny']],
            'external_system' => ['label' => __('External system'), 'required' => false, 'type' => 'text', 'aliases' => ['source system', 'system']],
            'is_active' => ['label' => __('Active'), 'required' => false, 'type' => 'bool', 'description' => __('yes/no; left unchanged when empty.'), 'aliases' => ['active', 'aktywny']],
        ];
    }

    public function options(): array
    {
        return [
            $this->strategyOption($this->erpStrategyChoices()),
            ['key' => 'external_system', 'type' => 'text', 'label' => __('External system'), 'maxLength' => 50, 'help' => __('Stamped on every row that does not name one, e.g. "pantheon".')],
            ['key' => 'only_categories', 'type' => 'text', 'label' => __('Only categories'), 'help' => __('Comma-separated. Rows in any other category are skipped, so a full ERP item dump can be sent as is.')],
        ];
    }

    public function optionRules(): array
    {
        return [
            'options.strategy' => ['nullable', 'in:update_or_create,skip_existing,error_on_duplicate'],
            'options.external_system' => ['nullable', 'string', 'max:50'],
            'options.only_categories' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function sample(): array
    {
        return [
            'headers' => ['code', 'name', 'description', 'category', 'unit_of_measure', 'is_active'],
            'rows' => [
                ['WIDGET-A', 'Widget Type A', 'Standard widget with coating', 'FG', 'pcs', 'yes'],
                ['BRACKET-S', 'Steel Bracket Small', 'L-shaped mounting bracket', 'FG', 'pcs', 'yes'],
            ],
        ];
    }

    public function import(array $rows, array $options): array
    {
        $system = trim((string) ($options['external_system'] ?? ''));

        return $this->service->import(
            $rows,
            $options['strategy'] ?? 'update_or_create',
            $this->listOption($options, 'only_categories'),
            $system !== '' ? $system : null,
        );
    }
}
