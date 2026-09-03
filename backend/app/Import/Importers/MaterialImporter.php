<?php

namespace App\Import\Importers;

use App\Import\AbstractEntityImporter;
use App\Models\MaterialType;
use App\Services\Erp\MaterialImportService;

class MaterialImporter extends AbstractEntityImporter
{
    public function __construct(private MaterialImportService $service) {}

    public function key(): string
    {
        return 'materials';
    }

    public function label(): string
    {
        return __('Materials');
    }

    public function description(): string
    {
        return __('Raw materials and components. Rows are matched by external code and system, then EAN, then code.');
    }

    public function fields(): array
    {
        return [
            'code' => ['label' => __('Code'), 'required' => false, 'type' => 'text', 'description' => __('Internal code (max 50). Generated from the external code or name when empty.'), 'aliases' => ['material code', 'material_code', 'sku', 'item code', 'kod', 'kod materiału', 'indeks']],
            'name' => ['label' => __('Name'), 'required' => true, 'type' => 'text', 'aliases' => ['material name', 'material', 'nazwa', 'nazwa materiału']],
            'description' => ['label' => __('Description'), 'required' => false, 'type' => 'text', 'aliases' => ['desc', 'opis']],
            'external_code' => ['label' => __('External code'), 'required' => false, 'type' => 'text', 'description' => __('The source system\'s symbol, e.g. a Subiekt index.'), 'aliases' => ['erp code', 'external id', 'symbol', 'kod zewnętrzny']],
            'ean' => ['label' => __('EAN / barcode'), 'required' => false, 'type' => 'text', 'aliases' => ['barcode', 'kod kreskowy', 'ean13']],
            'material_type' => ['label' => __('Material type'), 'required' => false, 'type' => 'text', 'description' => __('Code or name; created when unknown.'), 'aliases' => ['material_type_code', 'material type', 'type', 'category', 'group', 'typ materiału', 'rodzaj materiału', 'rodzaj', 'typ', 'grupa', 'grupa materiałowa', 'kategoria']],
            'unit_of_measure' => ['label' => __('Unit of measure'), 'required' => false, 'type' => 'text', 'description' => __('Defaults to pcs.'), 'aliases' => ['unit', 'uom', 'jm', 'jednostka']],
            'unit_price' => ['label' => __('Unit price'), 'required' => false, 'type' => 'number', 'aliases' => ['price', 'cena', 'cena jednostkowa']],
            'price_currency' => ['label' => __('Price currency'), 'required' => false, 'type' => 'text', 'aliases' => ['currency', 'waluta']],
            'min_stock_level' => ['label' => __('Minimum stock level'), 'required' => false, 'type' => 'number', 'aliases' => ['min stock', 'minimum', 'stan minimalny']],
            'stock_quantity' => ['label' => __('Stock quantity'), 'required' => false, 'type' => 'number', 'description' => __('Sets the material\'s global quantity; per-warehouse balances come from warehouse stock.'), 'aliases' => ['stock', 'quantity', 'qty', 'stan', 'ilość', 'stan magazynowy']],
            'supplier_name' => ['label' => __('Supplier name'), 'required' => false, 'type' => 'text', 'aliases' => ['supplier', 'dostawca']],
            'supplier_code' => ['label' => __('Supplier code'), 'required' => false, 'type' => 'text', 'aliases' => ['kod dostawcy']],
            'tracking_type' => ['label' => __('Tracking type'), 'required' => false, 'type' => 'text', 'description' => __('none, batch or serial.'), 'aliases' => ['tracking', 'śledzenie']],
            'default_scrap_percentage' => ['label' => __('Default scrap %'), 'required' => false, 'type' => 'number', 'aliases' => ['scrap', 'scrap %', 'odpad']],
            'is_active' => ['label' => __('Active'), 'required' => false, 'type' => 'bool', 'description' => __('yes/no; left unchanged when empty.'), 'aliases' => ['active', 'aktywny']],
        ];
    }

    public function identifierGroups(): array
    {
        return [['code'], ['external_code']];
    }

    public function warnings(): array
    {
        return [
            __('Map either Code or External code — a row with neither cannot be matched.'),
            __('Stock quantity here sets the global figure only. With warehouses enabled, load balances through warehouse stock instead.'),
        ];
    }

    public function options(): array
    {
        $types = MaterialType::orderBy('name')->get(['code', 'name'])
            ->map(fn ($t) => ['value' => $t->code, 'label' => $t->name])
            ->values()
            ->all();

        return [
            $this->strategyOption([
                ['value' => 'update_or_create', 'label' => __('Insert or update'), 'description' => __('Existing materials are updated, new ones created.')],
                ['value' => 'skip_existing', 'label' => __('Insert only'), 'description' => __('Materials that already exist are skipped untouched.')],
                ['value' => 'update_only', 'label' => __('Update only'), 'description' => __('Only existing materials are updated; unknown ones are skipped.')],
                ['value' => 'error_on_duplicate', 'label' => __('Error on duplicates'), 'description' => __('A material that already exists is reported as a failed row.')],
            ]),
            ['key' => 'external_system', 'type' => 'text', 'label' => __('External system'), 'maxLength' => 50, 'pattern' => '^[a-z0-9_]*$', 'help' => __('Lower-case identifier of the source, e.g. "subiekt". Rows are matched by external code within it.')],
            ['key' => 'default_material_type', 'type' => 'select', 'label' => __('Default material type'), 'nullable' => true, 'choices' => $types, 'help' => __('Used for rows that name no material type.')],
            ['key' => 'only_categories', 'type' => 'text', 'label' => __('Only material types'), 'help' => __('Comma-separated. Rows of any other type are skipped.')],
        ];
    }

    public function optionRules(): array
    {
        return [
            'options.strategy' => ['nullable', 'in:'.implode(',', MaterialImportService::STRATEGIES)],
            'options.external_system' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]*$/'],
            'options.default_material_type' => ['nullable', 'string', 'max:50', 'exists:material_types,code'],
            'options.only_categories' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function sample(): array
    {
        return [
            'headers' => ['code', 'name', 'description', 'material_type', 'unit_of_measure', 'stock_quantity', 'min_stock_level', 'supplier_name', 'tracking_type'],
            'rows' => [
                ['MAT-STEEL-01', 'Steel Sheet 2mm', 'Cold rolled steel sheet', 'RAW_MATERIAL', 'pcs', '100', '20', 'Steel Corp', 'batch'],
                ['MAT-PAINT-BL', 'Blue Paint RAL 5015', 'Industrial paint', 'CONSUMABLE', 'litre', '50', '10', 'Paint Pro', 'none'],
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
            [
                'match' => 'cascade',
                'generate_code' => true,
                'require_name' => true,
                'default_material_type' => $options['default_material_type'] ?? null,
            ],
        );
    }
}
