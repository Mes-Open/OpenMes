<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;

class ImportExampleController extends Controller
{
    public function download(string $type)
    {
        $examples = [
            'materials' => [
                'filename' => 'materials_example.csv',
                'headers' => ['code', 'name', 'description', 'material_type', 'unit_of_measure', 'stock_quantity', 'min_stock_level', 'supplier_name'],
                'rows' => [
                    ['MAT-STEEL-01', 'Steel Sheet 2mm', 'Cold rolled steel sheet', 'RAW_MATERIAL', 'pcs', '100', '20', 'Steel Corp'],
                    ['MAT-PAINT-BL', 'Blue Paint RAL 5015', 'Industrial paint', 'CONSUMABLE', 'litre', '50', '10', 'Paint Pro'],
                ],
            ],
            'product-types' => [
                'filename' => 'product_types_example.csv',
                'headers' => ['code', 'name', 'description', 'unit_of_measure'],
                'rows' => [
                    ['WIDGET-A', 'Widget Type A', 'Standard widget with coating', 'pcs'],
                    ['BRACKET-S', 'Steel Bracket Small', 'L-shaped mounting bracket', 'pcs'],
                ],
            ],
            'lines' => [
                'filename' => 'production_lines_example.csv',
                'headers' => ['code', 'name', 'description'],
                'rows' => [
                    ['CNC-1', 'CNC Machining', 'CNC milling and turning center'],
                    ['ASSEMBLY', 'Assembly Line', 'Manual assembly workstations'],
                ],
            ],
        ];

        if (!isset($examples[$type])) {
            abort(404);
        }

        $example = $examples[$type];
        $csv = implode(',', $example['headers']) . "\n";
        foreach ($example['rows'] as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $example['filename'] . '"');
    }
}
