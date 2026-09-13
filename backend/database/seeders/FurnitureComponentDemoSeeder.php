<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Optional, repeatable sofa/component masters for a manual production trial. */
class FurnitureComponentDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $line = \App\Models\Line::firstOrCreate(['code' => 'COMP-DEMO'], ['name' => 'Component production demo', 'is_active' => true]);
            $products = [];
            foreach (['DEMO-SOFA' => ['Sofa 2 Seater — BOM demo', 'Assembly'], 'DEMO-FOAM-PART' => ['Cut foam cushion — BOM demo', 'Cutting']] as $code => [$name, $operation]) {
                $product = \App\Models\ProductType::firstOrCreate(['code' => $code], ['name' => $name, 'unit_of_measure' => 'pcs', 'is_active' => true]);
                $template = \App\Models\ProcessTemplate::firstOrCreate(['product_type_id' => $product->id, 'version' => 1], ['name' => $operation, 'is_active' => true]);
                $station = \App\Models\Workstation::firstOrCreate(['code' => $code.'-ST'], ['name' => $operation.' demo', 'line_id' => $line->id, 'is_active' => true]);
                \App\Models\TemplateStep::firstOrCreate(['process_template_id' => $template->id, 'step_number' => 1], ['name' => $operation, 'workstation_id' => $station->id, 'estimated_duration_minutes' => 5, 'requires_confirmation' => false]);
                $products[$code] = $product;
            }
            $result = app(\App\Import\Importers\BomImporter::class)->import([
                ['product_type_code' => 'DEMO-SOFA', 'component_kind' => 'product_type', 'component_code' => 'DEMO-FOAM-PART', 'quantity_per_unit' => 4, 'foam_grade' => 'VB 18/40', 'length_mm' => 1985, 'width_mm' => 100, 'thickness_mm' => 10],
            ], ['mode' => 'replace']);
            if ($result['errors']) {
                throw new RuntimeException(json_encode($result['errors']));
            }
            $this->command?->info('Furniture component demo ready: DEMO-SOFA, DEMO-FOAM-PART and COMP-DEMO.');
        });
    }
}
