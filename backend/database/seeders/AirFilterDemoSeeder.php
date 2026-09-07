<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BomItem;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo data for an air-filter manufacturing plant. Mirrors the tablet
 * "Schedule & dispatch" design fixture set so screenshots and demos line up:
 *
 *  - Lines L-01 .. L-04
 *  - Product types HEPA-13 Std/Slim, Pre-filter G4, Carbon X2, HVAC cassette
 *  - 7-step HEPA-13 process template
 *  - 6 work orders (WO-186-001..005 due today/tomorrow + WO-185-088 done)
 *  - A running batch on WO-186-001 with steps 1-2 DONE, step 3 IN_PROGRESS
 *  - Operator-reported issues, one per lifecycle state (open → closed)
 *  - A two-level BOM: the HEPA-13 assembly consumes a manufactured pleat pack
 *    (sub-assembly) plus purchased parts; the pleat pack has its own routing
 *    and BOM, so exploding the top level reaches the raw media
 *
 * Run with: `php artisan db:seed --class=AirFilterDemoSeeder`
 *
 * The seeder is upsert-safe (uses `updateOrCreate` / `updateOrInsert`) so it
 * can be re-run on top of itself without producing duplicates.
 */
class AirFilterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lines = $this->seedLines();
        $workstations = $this->seedWorkstations($lines);
        $productTypes = $this->seedProductTypes();
        $templates = $this->seedProcessTemplates($productTypes, $workstations);
        $users = $this->seedUsers($lines);
        $materials = $this->seedMaterials($templates['PLEATPACK13']);
        $this->seedBom($templates['HEPA13_STD'], $templates['PLEATPACK13'], $materials);
        $workOrders = $this->seedWorkOrders($lines, $productTypes, $templates);
        $this->seedActiveBatch($workOrders['WO-186-001'], $templates['HEPA13_STD'], $users['operator-mk']);
        $this->seedIssues($workOrders, $users);
    }

    /**
     * The items the two BOMs consume: purchased parts plus the one manufactured
     * material (the pleat pack) that links the levels together.
     *
     * @return array<string, Material>
     */
    private function seedMaterials(ProcessTemplate $pleatPackTemplate): array
    {
        // raw_material / semi_finished / packaging / auxiliary. Idempotent, and
        // the demo must not depend on that seeder having been run separately.
        $this->call(MaterialTypesSeeder::class);

        $typeIds = MaterialType::pluck('id', 'code');

        $defs = [
            // Purchased.
            ['code' => 'MEDIA-H13',   'name' => 'HEPA-13 filter media',      'type' => 'raw_material', 'unit_of_measure' => 'm2',  'stock_quantity' => 4200,  'unit_price' => 6.40,  'supplier_name' => 'Filtrair Media BV'],
            ['code' => 'FRAME-AL-13', 'name' => 'Aluminium frame profile',   'type' => 'raw_material', 'unit_of_measure' => 'pcs', 'stock_quantity' => 1800,  'unit_price' => 11.20, 'supplier_name' => 'AluFab Sp. z o.o.'],
            ['code' => 'GASKET-PU-13', 'name' => 'PU gasket seal',           'type' => 'raw_material', 'unit_of_measure' => 'm',   'stock_quantity' => 2600,  'unit_price' => 1.85,  'supplier_name' => 'SealTech GmbH'],
            ['code' => 'ADH-2K-A',    'name' => 'Two-part adhesive (A)',     'type' => 'auxiliary',    'unit_of_measure' => 'kg',  'stock_quantity' => 180,   'unit_price' => 24.50, 'supplier_name' => 'ChemBond'],
            ['code' => 'HOTMELT-01',  'name' => 'Hot-melt edge sealant',     'type' => 'auxiliary',    'unit_of_measure' => 'kg',  'stock_quantity' => 95,    'unit_price' => 18.90, 'supplier_name' => 'ChemBond'],
            ['code' => 'CARTON-10',   'name' => 'Carton, 10 filters',        'type' => 'packaging',    'unit_of_measure' => 'pcs', 'stock_quantity' => 640,   'unit_price' => 3.10,  'supplier_name' => 'PackLine'],
        ];

        $materials = [];
        foreach ($defs as $def) {
            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $typeIds[$def['type']] ?? null,
                    'unit_of_measure' => $def['unit_of_measure'],
                    'tracking_type' => 'batch',
                    'is_manufactured' => false,
                    'stock_quantity' => $def['stock_quantity'],
                    'unit_price' => $def['unit_price'],
                    'supplier_name' => $def['supplier_name'],
                ]
            );
        }

        // The sub-assembly. `is_manufactured` + the producing template is what
        // lets a BOM line for it be exploded into the level below.
        $materials['PLEATPACK13'] = Material::updateOrCreate(
            ['code' => 'PLEATPACK13'],
            [
                'name' => 'Pleat pack HEPA-13',
                'material_type_id' => $typeIds['semi_finished'] ?? null,
                'unit_of_measure' => 'pcs',
                'tracking_type' => 'batch',
                'is_manufactured' => true,
                'producing_process_template_id' => $pleatPackTemplate->id,
                'stock_quantity' => 260,
            ]
        );

        return $materials;
    }

    /**
     * Two BOM levels: the HEPA-13 assembly consumes the pleat pack plus the
     * purchased parts, and the pleat pack's own template consumes media and
     * sealant. Exploding the top level therefore reaches the raw media.
     *
     * Lines are pinned to the step that actually consumes them, so the operator's
     * kit list matches the routing.
     *
     * @param  array<string, Material>  $materials
     */
    private function seedBom(ProcessTemplate $assembly, ProcessTemplate $pleatPack, array $materials): void
    {
        $defs = [
            // HEPA-13 assembly.
            [$assembly, 2, 'FRAME-AL-13',  1,    0,   'start'],
            [$assembly, 3, 'PLEATPACK13',  1,    2,   'start'],
            [$assembly, 4, 'GASKET-PU-13', 1.6,  3,   'during'],
            [$assembly, 4, 'ADH-2K-A',     0.08, 5,   'during'],
            // 10 filters to a carton.
            [$assembly, 6, 'CARTON-10',    0.1,  0,   'end'],

            // Pleat pack — the level below.
            [$pleatPack, 1, 'MEDIA-H13',   2.4,  5,   'start'],
            [$pleatPack, 2, 'HOTMELT-01',  0.03, 2,   'during'],
        ];

        $sortOrder = [];

        foreach ($defs as [$template, $stepNumber, $code, $qty, $scrap, $consumedAt]) {
            $material = $materials[$code] ?? null;
            if (! $material) {
                continue;
            }

            $stepId = DB::table('template_steps')
                ->where('process_template_id', $template->id)
                ->where('step_number', $stepNumber)
                ->value('id');

            $key = $template->id;
            $sortOrder[$key] = ($sortOrder[$key] ?? 0) + 1;

            BomItem::updateOrCreate(
                ['process_template_id' => $template->id, 'material_id' => $material->id],
                [
                    'template_step_id' => $stepId,
                    'quantity_per_unit' => $qty,
                    'scrap_percentage' => $scrap,
                    'consumed_at' => $consumedAt,
                    'sort_order' => $sortOrder[$key],
                ]
            );
        }
    }

    /**
     * Issues as the shop floor actually files them: an operator picks a type on
     * the work order and writes a line about what stopped them. One per status so
     * the queue, the supervisor board and the history all have something to show.
     *
     * Keyed on (work order, title) so a re-run updates in place — the seeder is
     * upsert-safe like the rest of this file.
     *
     * @param  array<string, WorkOrder>  $workOrders
     * @param  array<string, User>  $users
     */
    private function seedIssues(array $workOrders, array $users): void
    {
        $types = IssueType::pluck('id', 'code');

        // Without the issue-type reference data there is nothing to attach to;
        // IssueTypesSeeder owns those rows.
        if ($types->isEmpty()) {
            return;
        }

        $supervisor = $users['supervisor'];
        $mk = $users['operator-mk'];
        $an = $users['operator-an'];

        $defs = [
            // Blocking, still waiting on someone — what the supervisor board is for.
            [
                'wo' => 'WO-186-001',
                'type' => 'MATERIAL_DEFECT',
                'title' => 'Pleat pack delaminating on infeed',
                'description' => 'Every third pack from the current pallet separates at the glue line. Set the pallet aside and stopped feeding it.',
                'status' => Issue::STATUS_OPEN,
                'reported_by' => $mk,
                'reported_ago' => 35,
            ],
            // Non-blocking question — production keeps running.
            [
                'wo' => 'WO-186-002',
                'type' => 'OPERATOR_ASSISTANCE',
                'title' => 'Need a second pair of hands for the frame change',
                'description' => 'Slim frames need two people to load safely. Asking for support before starting the run.',
                'status' => Issue::STATUS_OPEN,
                'reported_by' => $an,
                'reported_ago' => 20,
            ],
            // Picked up by the supervisor — this is why WO-186-004 sits paused.
            [
                'wo' => 'WO-186-004',
                'type' => 'TOOL_FAILURE',
                'title' => 'Carbon press holding pressure only to 4 bar',
                'description' => 'Press will not reach the 6 bar set point. Line paused until maintenance looks at it.',
                'status' => Issue::STATUS_ACKNOWLEDGED,
                'reported_by' => $an,
                'reported_ago' => 95,
                'acknowledged_ago' => 70,
                'assigned_to' => $supervisor,
            ],
            // Fixed, kept open one more shift for verification.
            [
                'wo' => 'WO-186-001',
                'type' => 'MEASUREMENT_ERROR',
                'title' => 'Depth gauge reading 0.4 mm high',
                'description' => 'Gauge disagreed with the reference block on the first check of the shift.',
                'status' => Issue::STATUS_RESOLVED,
                'reported_by' => $mk,
                'reported_ago' => 300,
                'acknowledged_ago' => 280,
                'resolved_ago' => 240,
                'assigned_to' => $supervisor,
                'resolution_notes' => 'Gauge re-zeroed against the reference block and re-checked on ten parts.',
            ],
            // Closed out on a finished order — the history view needs one.
            [
                'wo' => 'WO-185-088',
                'type' => 'QUALITY_ISSUE',
                'title' => 'Two filters with visible frame scratches',
                'description' => 'Scratches on the short edge, most likely from the transport rack.',
                'status' => Issue::STATUS_CLOSED,
                'reported_by' => $mk,
                'reported_ago' => 1_500,
                'acknowledged_ago' => 1_480,
                'resolved_ago' => 1_400,
                'closed_ago' => 1_380,
                'assigned_to' => $supervisor,
                'resolution_notes' => 'Both units reworked and passed the visual check. Rack padding replaced.',
            ],
        ];

        foreach ($defs as $def) {
            $workOrder = $workOrders[$def['wo']] ?? null;
            $typeId = $types[$def['type']] ?? null;

            if (! $workOrder || ! $typeId) {
                continue;
            }

            Issue::updateOrCreate(
                ['work_order_id' => $workOrder->id, 'title' => $def['title']],
                [
                    'issue_type_id' => $typeId,
                    'description' => $def['description'],
                    'status' => $def['status'],
                    'source' => Issue::SOURCE_IN_PROCESS,
                    'reported_by_id' => $def['reported_by']->id,
                    'assigned_to_id' => isset($def['assigned_to']) ? $def['assigned_to']->id : null,
                    'reported_at' => now()->subMinutes($def['reported_ago']),
                    'acknowledged_at' => isset($def['acknowledged_ago']) ? now()->subMinutes($def['acknowledged_ago']) : null,
                    'resolved_at' => isset($def['resolved_ago']) ? now()->subMinutes($def['resolved_ago']) : null,
                    'closed_at' => isset($def['closed_ago']) ? now()->subMinutes($def['closed_ago']) : null,
                    'resolution_notes' => $def['resolution_notes'] ?? null,
                ]
            );
        }
    }

    private function seedLines(): array
    {
        $defs = [
            ['code' => 'L-01', 'name' => 'Air Filter', 'description' => 'HEPA pleat assembly line'],
            ['code' => 'L-02', 'name' => 'Housing', 'description' => 'Frame and housing fabrication'],
            ['code' => 'L-03', 'name' => 'Sub-assy', 'description' => 'Sub-assembly and integration'],
            ['code' => 'L-04', 'name' => 'Pack & Ship', 'description' => 'Final packing and dispatch'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $line = Line::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $line;
        }

        return $result;
    }

    private function seedWorkstations(array $lines): array
    {
        $defs = [
            // L-01 — process steps shown in the design's right detail panel.
            ['line' => 'L-01', 'code' => 'WS-MK-01',  'name' => 'Material Kit Picking',  'workstation_type' => 'picking'],
            ['line' => 'L-01', 'code' => 'WS-FR-01',  'name' => 'Frame Stamping Press',   'workstation_type' => 'press'],
            ['line' => 'L-01', 'code' => 'WS-PA-01',  'name' => 'Pleat Assembly Table',   'workstation_type' => 'assembly'],
            ['line' => 'L-01', 'code' => 'WS-AB-01',  'name' => 'Adhesive Bond Booth',    'workstation_type' => 'bonding'],
            ['line' => 'L-01', 'code' => 'WS-QC-01',  'name' => 'QC Visual Bench',        'workstation_type' => 'qc'],
            ['line' => 'L-01', 'code' => 'WS-PK-01',  'name' => 'Packaging Line 10/box',  'workstation_type' => 'packing'],
            ['line' => 'L-01', 'code' => 'WS-PH-01',  'name' => 'Pallet Handoff',         'workstation_type' => 'shipping'],
            // L-02 — housing line workstations.
            ['line' => 'L-02', 'code' => 'WS-HM-01',  'name' => 'Housing Mould Press',    'workstation_type' => 'press'],
            ['line' => 'L-02', 'code' => 'WS-HQC-01', 'name' => 'Housing QC',             'workstation_type' => 'qc'],
            // L-03 — sub-assembly.
            ['line' => 'L-03', 'code' => 'WS-SA-01',  'name' => 'Sub-assembly Bench A',   'workstation_type' => 'assembly'],
            ['line' => 'L-03', 'code' => 'WS-SA-02',  'name' => 'Sub-assembly Bench B',   'workstation_type' => 'assembly'],
            // L-04 — pack & ship.
            ['line' => 'L-04', 'code' => 'WS-PK-04',  'name' => 'Pack Station',           'workstation_type' => 'packing'],
            ['line' => 'L-04', 'code' => 'WS-SHIP-1', 'name' => 'Pallet Wrap & Label',    'workstation_type' => 'shipping'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $ws = Workstation::updateOrCreate(
                ['code' => $def['code']],
                [
                    'line_id' => $lines[$def['line']]->id,
                    'name' => $def['name'],
                    'workstation_type' => $def['workstation_type'],
                    'is_active' => true,
                ]
            );
            $result[$def['code']] = $ws;
        }

        return $result;
    }

    private function seedProductTypes(): array
    {
        $defs = [
            ['code' => 'HEPA13_STD',  'name' => 'HEPA-13 Standard',  'description' => 'Standard HEPA-13 pleated air filter',     'unit_of_measure' => 'pcs'],
            ['code' => 'HEPA13_SLIM', 'name' => 'HEPA-13 Slim',      'description' => 'Slim-profile HEPA-13 filter for low-clearance housings', 'unit_of_measure' => 'pcs'],
            ['code' => 'PREFILTER',   'name' => 'Pre-filter G4',     'description' => 'Coarse pre-filter (G4 grade), upstream of HEPA',         'unit_of_measure' => 'pcs'],
            ['code' => 'CARBON',      'name' => 'Carbon X2',         'description' => 'Activated-carbon odour and VOC filter',                  'unit_of_measure' => 'pcs'],
            ['code' => 'HVAC',        'name' => 'HVAC cassette',     'description' => 'HVAC cassette filter, multi-stage media stack',          'unit_of_measure' => 'pcs'],
            // Semi-finished: produced on its own template, then consumed by the
            // HEPA-13 assembly. It needs a product type because that is what a
            // process template is written against.
            ['code' => 'PLEATPACK13', 'name' => 'Pleat pack HEPA-13', 'description' => 'Folded and edge-sealed HEPA-13 media pack, ready to frame', 'unit_of_measure' => 'pcs'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    /**
     * A routing for every demo product, not just the HEPA-13. Without one a
     * product type shows "0 templates", its work orders carry no steps for the
     * operator to work through, and there is nowhere to hang a BOM.
     *
     * @param  array<string, ProductType>  $pt
     * @param  array<string, Workstation>  $ws
     * @return array<string, ProcessTemplate> keyed by product code
     */
    private function seedProcessTemplates(array $pt, array $ws): array
    {
        $defs = [
            'HEPA13_STD' => ['HEPA-13 Standard — assembly v1', [
                [1, 'Material kit pickup',  'Pull the BOM kit (pleat sheet, frame blank, adhesive, gasket) from staging.', 5, 'WS-MK-01'],
                [2, 'Frame stamping',       'Stamp the aluminium frame blank on the housing press; check perpendicularity.', 4, 'WS-FR-01'],
                [3, 'Pleat assembly',       'Pleat the filter media and slot into frame. Maintain pitch ±0.5 mm.', 8, 'WS-PA-01'],
                [4, 'Adhesive bonding',     'Apply two-part adhesive bead around perimeter; cure 6 min at 60 °C.', 10, 'WS-AB-01'],
                [5, 'QC visual inspection', 'Inspect for pleat collapse, adhesive squeeze-out, gasket fit. Photograph defects.', 3, 'WS-QC-01'],
                [6, 'Packaging (10/box)',   'Pack 10 filters per carton with desiccant; apply lot label.', 4, 'WS-PK-01'],
                [7, 'Pallet handoff',       'Stack cartons on pallet, wrap, hand off to dispatch with batch sheet.', 3, 'WS-PH-01'],
            ]],
            // Shares the HEPA line but skips the separate stamping pass — the slim
            // frame arrives pre-formed.
            'HEPA13_SLIM' => ['HEPA-13 Slim — assembly v1', [
                [1, 'Material kit pickup',  'Pull the slim-frame kit from staging; check the frame depth marking.', 5, 'WS-MK-01'],
                [2, 'Pleat assembly',       'Pleat to the slim pitch and seat into the pre-formed frame.', 9, 'WS-PA-01'],
                [3, 'Adhesive bonding',     'Bead the perimeter and cure. Slim frames need the lower 55 °C profile.', 10, 'WS-AB-01'],
                [4, 'QC visual inspection', 'Check pleat pitch, seal continuity and that the depth is within tolerance.', 3, 'WS-QC-01'],
                [5, 'Packaging (10/box)',   'Pack 10 per carton; slim cartons take the same lot label.', 4, 'WS-PK-01'],
            ]],
            // Coarse filter on the housing line — moulded, not pleated.
            'PREFILTER' => ['Pre-filter G4 — production v1', [
                [1, 'Housing mould',        'Mould the G4 housing frame; check for short shots before releasing.', 6, 'WS-HM-01'],
                [2, 'Media insert',         'Cut the G4 media to size and press into the housing.', 5, 'WS-HM-01'],
                [3, 'Housing QC',           'Verify the media sits flush and the frame has no flash.', 3, 'WS-HQC-01'],
                [4, 'Pack & label',         'Bag, box and label for stock replenishment.', 4, 'WS-PK-04'],
            ]],
            'CARBON' => ['Carbon X2 — production v1', [
                [1, 'Housing mould',        'Mould the Carbon X2 housing; verify the fill port is clear.', 6, 'WS-HM-01'],
                [2, 'Carbon fill',          'Fill with activated carbon to the weight target, then settle on the vibrator.', 8, 'WS-HM-01'],
                [3, 'Seal & press',         'Press the retaining mesh and seal the fill port.', 5, 'WS-HM-01'],
                [4, 'Housing QC',           'Weigh-check the fill and confirm no carbon migration past the mesh.', 4, 'WS-HQC-01'],
                [5, 'Pack & label',         'Box, label and stage for dispatch.', 4, 'WS-PK-04'],
            ]],
            'HVAC' => ['HVAC cassette — assembly v1', [
                [1, 'Cassette frame prep',  'Square up the cassette frame and fit the corner brackets.', 6, 'WS-SA-01'],
                [2, 'Stage 1 media',        'Load the coarse pre-filter stage into the first channel.', 5, 'WS-SA-01'],
                [3, 'Stage 2 media',        'Load the fine media stage; check the gasket seats on both sides.', 6, 'WS-SA-02'],
                [4, 'Cassette closing',     'Close the cassette and secure the retaining clips.', 4, 'WS-SA-02'],
                [5, 'Leak check',           'Pressure-test the cassette seal; log the reading on the batch sheet.', 5, 'WS-SA-02'],
                [6, 'Pack & label',         'Sleeve, box and label the cassette.', 4, 'WS-PK-04'],
            ]],
            // The sub-assembly's own routing — what makes PLEATPACK13 explodable.
            'PLEATPACK13' => ['Pleat pack HEPA-13 — production v1', [
                [1, 'Media pleating',       'Feed the media roll and fold to the HEPA-13 pitch. Check pleat height on the first five packs.', 6, 'WS-PA-01'],
                [2, 'Edge sealing',         'Run a hot-melt bead down both open edges and press until set.', 4, 'WS-AB-01'],
            ]],
        ];

        $templates = [];

        foreach ($defs as $productCode => [$name, $steps]) {
            $productType = $pt[$productCode] ?? null;
            if (! $productType) {
                continue;
            }

            $template = ProcessTemplate::updateOrCreate(
                ['product_type_id' => $productType->id, 'version' => 1],
                ['name' => $name, 'is_active' => true]
            );

            foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $wsCode]) {
                DB::table('template_steps')->updateOrInsert(
                    ['process_template_id' => $template->id, 'step_number' => $stepNo],
                    [
                        'name' => $stepName,
                        'instruction' => $instruction,
                        'estimated_duration_minutes' => $duration,
                        'workstation_id' => $ws[$wsCode]?->id,
                        'created_at' => now(),
                    ]
                );
            }

            $templates[$productCode] = $template;
        }

        return $templates;
    }

    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole = Role::where('name', 'Operator')->first();

        $supervisor = User::updateOrCreate(
            ['username' => 'peter.wilson'],
            [
                'name' => 'Peter Wilson',
                'email' => 'peter.wilson@airfilter.local',
                'password' => Hash::make('Supervisor1!'),
                'account_type' => 'user',
                'force_password_change' => false,
            ]
        );
        if ($supervisorRole && ! $supervisor->hasRole('Supervisor')) {
            $supervisor->assignRole($supervisorRole);
        }
        $supervisor->lines()->syncWithoutDetaching(array_map(fn ($l) => $l->id, $lines));

        $operators = [
            'operator-mk' => [
                'username' => 'm.kowalski',
                'name' => 'M. Kowalski',
                'email' => 'm.kowalski@airfilter.local',
                'lines' => ['L-01'],
            ],
            'operator-an' => [
                'username' => 'a.nowak',
                'name' => 'A. Nowak',
                'email' => 'a.nowak@airfilter.local',
                'lines' => ['L-02'],
            ],
        ];

        $result = ['supervisor' => $supervisor];
        foreach ($operators as $key => $def) {
            $user = User::updateOrCreate(
                ['username' => $def['username']],
                [
                    'name' => $def['name'],
                    'email' => $def['email'],
                    'password' => Hash::make('Operator1!'),
                    'account_type' => 'user',
                    'force_password_change' => false,
                ]
            );
            if ($operatorRole && ! $user->hasRole('Operator')) {
                $user->assignRole($operatorRole);
            }
            $lineIds = array_map(fn ($code) => $lines[$code]->id, $def['lines']);
            $user->lines()->syncWithoutDetaching($lineIds);
            $result[$key] = $user;
        }

        return $result;
    }

    /**
     * @param  array<string, ProcessTemplate>  $templates  keyed by product code
     */
    private function seedWorkOrders(array $lines, array $pt, array $templates): array
    {
        $today = now()->copy();
        // We pin due times so a screenshot taken at any clock-hour still sees
        // "Today" entries scheduled for later in the same day.
        $at = fn (int $h, int $m) => $today->copy()->setTime($h, $m);

        $defs = [
            [
                'order_no' => 'WO-186-001',
                'line' => 'L-01',
                'product' => 'HEPA13_STD',
                'planned_qty' => 250,
                'produced_qty' => 108,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 4,
                'due_date' => $at(14, 30),
                'description' => 'Standard HEPA-13, B2B order — Filtex distribution.',
            ],
            [
                'order_no' => 'WO-186-002',
                'line' => 'L-01',
                'product' => 'HEPA13_SLIM',
                'planned_qty' => 120,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => $at(17, 0),
                'description' => 'HEPA-13 Slim for HVAC retrofit.',
            ],
            [
                'order_no' => 'WO-186-003',
                'line' => 'L-02',
                'product' => 'PREFILTER',
                'planned_qty' => 400,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 2,
                'due_date' => $at(15, 45),
                'description' => 'G4 pre-filters — bulk stock replenishment.',
            ],
            [
                'order_no' => 'WO-186-004',
                'line' => 'L-02',
                'product' => 'CARBON',
                'planned_qty' => 180,
                'produced_qty' => 112,
                'status' => WorkOrder::STATUS_PAUSED,
                'priority' => 3,
                'due_date' => $at(16, 0),
                'description' => 'Carbon X2 — paused pending raw-material delivery.',
            ],
            [
                'order_no' => 'WO-186-005',
                'line' => 'L-03',
                'product' => 'HVAC',
                'planned_qty' => 60,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 1,
                'due_date' => $today->copy()->addDay()->setTime(10, 0),
                'description' => 'HVAC cassettes — light run, scheduled for tomorrow.',
            ],
            [
                'order_no' => 'WO-185-088',
                'line' => 'L-01',
                'product' => 'HEPA13_STD',
                'planned_qty' => 500,
                'produced_qty' => 500,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 2,
                'due_date' => $today->copy()->subDay()->setTime(14, 0),
                'description' => 'HEPA-13 Standard — completed previous shift.',
                'completed_at' => $today->copy()->subHours(20),
            ],
        ];

        $result = [];
        foreach ($defs as $def) {
            $payload = [
                'line_id' => $lines[$def['line']]->id,
                'product_type_id' => $pt[$def['product']]->id,
                'planned_qty' => $def['planned_qty'],
                'produced_qty' => $def['produced_qty'],
                'status' => $def['status'],
                'priority' => $def['priority'],
                'due_date' => $def['due_date'],
                'description' => $def['description'],
            ];
            if (! empty($def['completed_at'])) {
                $payload['completed_at'] = $def['completed_at'];
            }
            // Snapshot the product's own routing the way the app does — a
            // hand-rolled header carries no steps and no BOM, which leaves the
            // operator with nothing to work through and no kit list.
            $template = $templates[$def['product']] ?? null;
            if ($template) {
                $payload['process_snapshot'] = $template
                    ->fresh(['steps.workstation', 'bomItems.material'])
                    ->toSnapshot();
            }

            $wo = WorkOrder::updateOrCreate(['order_no' => $def['order_no']], $payload);
            $result[$def['order_no']] = $wo;
        }

        return $result;
    }

    /**
     * Seed a single running batch on WO-186-001 with three steps already
     * touched: 1 + 2 DONE, 3 IN_PROGRESS, 4-7 PENDING. Matches the design's
     * right-rail process panel exactly.
     */
    private function seedActiveBatch(WorkOrder $wo, ProcessTemplate $template, User $operator): void
    {
        $startedAt = now()->copy()->subHours(6)->setTime(6, 42);

        $batch = Batch::updateOrCreate(
            ['work_order_id' => $wo->id, 'batch_number' => 2],
            [
                'target_qty' => 90,
                'produced_qty' => 108,
                'status' => Batch::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'lot_number' => 'LOT-2026-'.str_pad((string) $wo->id, 4, '0', STR_PAD_LEFT),
                // varchar(10): trigger code, not a timestamp. Values: on_start / on_release.
                'lot_assigned_at' => 'on_start',
                'scrap_qty' => 3,
            ]
        );

        $templateSteps = DB::table('template_steps')
            ->where('process_template_id', $template->id)
            ->orderBy('step_number')
            ->get();

        foreach ($templateSteps as $tStep) {
            $status = match ((int) $tStep->step_number) {
                1, 2 => BatchStep::STATUS_DONE,
                3 => BatchStep::STATUS_IN_PROGRESS,
                default => BatchStep::STATUS_PENDING,
            };

            $startedStepAt = match ((int) $tStep->step_number) {
                1 => $startedAt,
                2 => $startedAt->copy()->addMinutes(8),
                3 => $startedAt->copy()->addMinutes(20),
                default => null,
            };
            $completedStepAt = match ((int) $tStep->step_number) {
                1 => $startedAt->copy()->addMinutes(7),
                2 => $startedAt->copy()->addMinutes(15),
                default => null,
            };
            $duration = $completedStepAt && $startedStepAt
                ? $completedStepAt->diffInMinutes($startedStepAt)
                : null;

            BatchStep::updateOrCreate(
                ['batch_id' => $batch->id, 'step_number' => $tStep->step_number],
                [
                    'name' => $tStep->name,
                    'instruction' => $tStep->instruction,
                    'workstation_id' => $tStep->workstation_id,
                    'status' => $status,
                    'started_at' => $startedStepAt,
                    'completed_at' => $completedStepAt,
                    'duration_minutes' => $duration,
                    'started_by_id' => $startedStepAt ? $operator->id : null,
                    'completed_by_id' => $completedStepAt ? $operator->id : null,
                ]
            );
        }
    }
}
