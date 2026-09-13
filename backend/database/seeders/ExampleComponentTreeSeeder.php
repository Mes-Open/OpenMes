<?php

namespace Database\Seeders;

use App\Import\Importers\BomImporter;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Repeatable EX masters for inspecting a three-level component tree. */
class ExampleComponentTreeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $line = Line::firstOrCreate(['code' => 'EX-LINE'], ['name' => 'EX — produkcja przykładowa', 'is_active' => true]);
            $definitions = [
                'EX-SOFA' => ['EX — Sofa dwuosobowa', 'Montaż sofy'],
                'EX-FRAME' => ['EX — Stelaż', 'Montaż stelaża'],
                'EX-SEAT' => ['EX — Siedzisko', 'Montaż siedziska'],
                'EX-SIDE' => ['EX — Bok stelaża', 'Montaż boku'],
                'EX-BEAM' => ['EX — Belka drewniana', 'Cięcie drewna'],
                'EX-FOAM' => ['EX — Wkład piankowy', 'Cięcie pianki'],
                'EX-COVER' => ['EX — Pokrowiec', 'Szycie pokrowca'],
                'EX-PANEL' => ['EX — Panel tkaniny', 'Krojenie tkaniny'],
            ];
            foreach ($definitions as $code => [$name, $operation]) {
                $product = ProductType::firstOrCreate(['code' => $code], ['name' => $name, 'unit_of_measure' => 'pcs', 'is_active' => true]);
                $template = ProcessTemplate::firstOrCreate(['product_type_id' => $product->id, 'version' => 1], ['name' => $operation, 'is_active' => true]);
                $station = Workstation::firstOrCreate(['code' => $code.'-ST'], ['name' => 'EX — '.$operation, 'line_id' => $line->id, 'is_active' => true]);
                TemplateStep::firstOrCreate(['process_template_id' => $template->id, 'step_number' => 1], ['name' => $operation, 'workstation_id' => $station->id, 'estimated_duration_minutes' => 5, 'requires_confirmation' => false]);
            }
            $rows = [];
            foreach ([
                ['EX-SOFA', 'EX-FRAME', 1],
                ['EX-SOFA', 'EX-SEAT', 2],
                ['EX-FRAME', 'EX-SIDE', 2],
                ['EX-SIDE', 'EX-BEAM', 3],
                ['EX-SEAT', 'EX-FOAM', 1],
                ['EX-SEAT', 'EX-COVER', 1],
                ['EX-COVER', 'EX-PANEL', 2],
            ] as [$parent, $child, $quantity]) {
                $rows[] = ['product_type_code' => $parent, 'component_kind' => 'product_type', 'component_code' => $child, 'quantity_per_unit' => $quantity];
            }
            $result = app(BomImporter::class)->import($rows, ['mode' => 'replace']);
            if ($result['errors']) {
                throw new \RuntimeException(json_encode($result['errors']));
            }
            $this->command?->info('EX-SOFA tree ready: 7 component occurrences across 3 levels.');
        });
    }
}
