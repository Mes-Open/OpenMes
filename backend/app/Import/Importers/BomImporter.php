<?php

namespace App\Import\Importers;

use App\Import\AbstractEntityImporter;
use App\Services\Erp\BomImportService;

/**
 * Recipes arrive flat — one file row per component — and BomImportService
 * wants one row per recipe with its component list, so this importer groups
 * rows by product (and template version) before handing them over, and maps
 * each recipe-level error back to the file row where that recipe starts.
 *
 * Counts are per recipe, not per component row: one product = one created or
 * updated recipe.
 */
class BomImporter extends AbstractEntityImporter
{
    public function __construct(private BomImportService $service) {}

    public function key(): string
    {
        return 'boms';
    }

    public function label(): string
    {
        return __('Bills of materials');
    }

    public function description(): string
    {
        return __('Recipes: one row per component, grouped by product. Attached to the product\'s process template.');
    }

    public function fields(): array
    {
        return [
            'product_type_code' => ['label' => __('Product type code'), 'required' => true, 'type' => 'text', 'description' => __('The finished product the recipe belongs to.'), 'aliases' => ['product', 'product code', 'product_code', 'parent', 'produkt', 'kod produktu', 'wyrób']],
            'material_code' => ['label' => __('Material code'), 'required' => true, 'type' => 'text', 'description' => __('An existing material code.'), 'aliases' => ['material', 'component', 'component_code', 'component code', 'materiał', 'składnik', 'kod materiału']],
            'quantity_per_unit' => ['label' => __('Quantity per unit'), 'required' => true, 'type' => 'number', 'description' => __('Consumption per one unit of the product.'), 'aliases' => ['quantity', 'qty', 'qty per unit', 'ilość', 'ilość na sztukę', 'norma']],
            'process_template_version' => ['label' => __('Process template version'), 'required' => false, 'type' => 'integer', 'description' => __('Defaults to the active template.'), 'aliases' => ['version', 'template version', 'wersja']],
            'scrap_percentage' => ['label' => __('Scrap %'), 'required' => false, 'type' => 'number', 'aliases' => ['scrap', 'odpad']],
            'notes' => ['label' => __('Notes'), 'required' => false, 'type' => 'text', 'aliases' => ['note', 'comment', 'uwagi']],
            'sort_order' => ['label' => __('Sort order'), 'required' => false, 'type' => 'integer', 'aliases' => ['position', 'order', 'lp', 'kolejność']],
        ];
    }

    public function warnings(): array
    {
        return [
            __('Every product needs a process template before its recipe can be imported.'),
            __('In "replace" mode the file becomes the complete recipe: components not listed are removed.'),
        ];
    }

    public function options(): array
    {
        return [
            [
                'key' => 'mode',
                'type' => 'select',
                'label' => __('Recipe mode'),
                'default' => 'replace',
                'choices' => [
                    ['value' => 'replace', 'label' => __('Replace'), 'description' => __('The imported components become the whole recipe.')],
                    ['value' => 'merge', 'label' => __('Merge'), 'description' => __('Imported components are added or updated; others are kept.')],
                ],
            ],
        ];
    }

    public function optionRules(): array
    {
        return [
            'options.mode' => ['nullable', 'in:replace,merge'],
        ];
    }

    public function chunkSize(): ?int
    {
        return null; // a recipe must not be split across chunks
    }

    public function sample(): array
    {
        return [
            'headers' => ['product_type_code', 'material_code', 'quantity_per_unit', 'scrap_percentage', 'sort_order'],
            'rows' => [
                ['WIDGET-A', 'MAT-STEEL-01', '2', '1.5', '1'],
                ['WIDGET-A', 'MAT-PAINT-BL', '0.25', '0', '2'],
                ['BRACKET-S', 'MAT-STEEL-01', '1', '0', '1'],
            ],
        ];
    }

    public function import(array $rows, array $options): array
    {
        $recipes = [];      // recipeKey => recipe row for the service
        $firstRow = [];     // recipeKey => 1-based index of the file row that opened it
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = $index + 1;
            $product = trim((string) ($row['product_type_code'] ?? ''));
            $material = trim((string) ($row['material_code'] ?? ''));

            if ($product === '') {
                $errors[] = ['row' => $rowNumber, 'field' => 'product_type_code', 'message' => __('Product code is required')];

                continue;
            }

            if ($material === '') {
                $errors[] = ['row' => $rowNumber, 'field' => 'material_code', 'message' => __('Material code is required')];

                continue;
            }

            $version = $row['process_template_version'] ?? null;
            $key = $product.'|'.($version ?? '');

            if (! isset($recipes[$key])) {
                $recipes[$key] = [
                    'product_type_code' => $product,
                    'process_template_version' => $version,
                    'components' => [],
                ];
                $firstRow[$key] = $rowNumber;
            }

            $component = [
                'material_code' => $material,
                'quantity_per_unit' => $row['quantity_per_unit'] ?? 0,
                'scrap_percentage' => $row['scrap_percentage'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ];

            if (isset($row['sort_order'])) {
                $component['sort_order'] = (int) $row['sort_order'];
            }

            $recipes[$key]['components'][] = $component;
        }

        $keys = array_keys($recipes);
        $result = $this->service->import(array_values($recipes), $options['mode'] ?? 'replace');

        // The service numbers errors by recipe; point them at the file row instead.
        foreach ($result['errors'] as $error) {
            $recipeKey = $keys[$error['row'] - 1] ?? null;
            $error['row'] = $recipeKey !== null ? $firstRow[$recipeKey] : $error['row'];
            $errors[] = $error;
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);
        $result['errors'] = $errors;

        return $result;
    }
}
