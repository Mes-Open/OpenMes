<?php

namespace Database\Seeders;

use App\Enums\Tier;
use App\Models\Area;
use App\Models\BomItem;
use App\Models\Crew;
use App\Models\Customer;
use App\Models\InspectionPlan;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\OeeRecord;
use App\Models\PersonnelClass;
use App\Models\ProcessSegment;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Skill;
use App\Models\Tool;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderPlacement;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Demo data for a precision machining shop — sawing, CNC turning and milling,
 * heat treatment, grinding and dimensional inspection.
 *
 * The third example company, and deliberately unlike the other two: parts are
 * measured in tens per hour rather than hundreds, cycle times are long, and the
 * value is added by removing metal rather than by decorating a blank. That is
 * the point — a prospect from this trade should recognise their own shop.
 *
 * What it covers, and why each matters on a screen:
 *
 *  - Six lines following the real route a part takes, seventeen stations
 *  - Seven finished parts plus four sub-assemblies, each with its own routing
 *  - A three-level BOM: drive shaft → rough-turned shaft → sawn billet → bar.
 *    The billet feeds the pinion too, so netting has to sum two parents before
 *    deciding how many to cut
 *  - Orders across five weeks, blocked ones included, so the planner board is
 *    busy now and still has work a month out
 *  - Customers with spread tiers and payment scores, so priority scoring ranks
 *  - Tooling that genuinely wears — inserts, end mills, wheels, probes — with
 *    maintenance schedules pointing at it
 *  - Problems the floor reports, two of them blocking their order
 *
 * Run with: `php artisan db:seed --class=MachineShopDemoSeeder`
 *
 * Upsert-safe throughout, so it can be re-run on top of itself.
 */
class MachineShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIssueTypes();
        $lines = $this->seedLines();
        $workstations = $this->seedWorkstations($lines);
        $productTypes = $this->seedProductTypes();
        $templates = $this->seedProcessTemplates($productTypes, $workstations);
        $users = $this->seedUsers($lines);
        $customers = $this->seedCustomers();
        $this->seedWorkOrders($productTypes, $lines);
        $this->assignCustomers($customers);
        $this->seedMultiLinePlacements($lines);
        $this->seedShifts($lines);
        $materials = $this->seedMaterials($templates);
        $this->seedBom($templates, $materials);
        $this->seedMaterialLots($materials);
        $this->seedIssues($lines, $users);
        $this->seedISA95Hierarchy($lines);
        $this->seedSkillsAndPersonnelClasses();
        $this->seedCrews($lines, $workstations);
        $this->seedProcessSegments();
        $tools = $this->seedTools();
        $this->seedMaintenanceSchedulesAndEvents($lines, $workstations, $tools);
        $this->seedInspectionPlans($materials);
        $this->seedOeeRecords($lines);
    }

    // ── Issue types ──────────────────────────────────────────────────────────

    private function seedIssueTypes(): void
    {
        $types = [
            ['code' => 'TOOL_BREAKAGE',     'name' => 'Tool Breakage',                 'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'OUT_OF_TOLERANCE',  'name' => 'Dimension Out of Tolerance',    'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SPINDLE_ALARM',     'name' => 'Spindle Alarm',                 'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'HARDNESS_FAIL',     'name' => 'Hardness Out of Specification', 'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PROGRAM_ERROR',     'name' => 'Program / Offset Error',        'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'MATERIAL_CERT',     'name' => 'Material Certificate Missing',  'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SURFACE_FINISH',    'name' => 'Surface Finish Defect',         'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'COOLANT_QUALITY',   'name' => 'Coolant Contamination',         'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'CHIP_JAM',          'name' => 'Chip Evacuation Jam',           'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'FIXTURE_ISSUE',     'name' => 'Fixture / Clamping Issue',      'severity' => 'MEDIUM',   'is_blocking' => false],
        ];

        foreach ($types as $type) {
            DB::table('issue_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, ['is_active' => true])
            );
        }
    }

    // ── Lines ────────────────────────────────────────────────────────────────

    /** @return array<string, Line> */
    private function seedLines(): array
    {
        $defs = [
            ['code' => 'SAW',   'name' => 'Sawing & Billet Prep', 'description' => 'Bar stock cut to length, deburred and staged for machining'],
            ['code' => 'TURN',  'name' => 'CNC Turning',          'description' => 'CNC lathes with bar feed — shafts, bushings, flanges'],
            ['code' => 'MILL',  'name' => 'CNC Milling',          'description' => '3- and 5-axis machining centres — housings, manifolds, plates'],
            ['code' => 'HEAT',  'name' => 'Heat Treatment',       'description' => 'Hardening, quenching and tempering of machined blanks'],
            ['code' => 'GRIND', 'name' => 'Grinding & Finishing', 'description' => 'Cylindrical and surface grinding to final tolerance'],
            ['code' => 'QC',    'name' => 'Inspection & Packing', 'description' => 'CMM measurement, documentation and corrosion-protected packing'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $result[$def['code']] = Line::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
        }

        return $result;
    }

    // ── Workstations ─────────────────────────────────────────────────────────

    /** @return array<string, Workstation> */
    private function seedWorkstations(array $lines): array
    {
        $defs = [
            ['line' => 'SAW',   'code' => 'SAW-01',    'name' => 'Bandsaw #1 (Bomar)',              'workstation_type' => 'saw'],
            ['line' => 'SAW',   'code' => 'SAW-02',    'name' => 'Bandsaw #2 (Kasto)',              'workstation_type' => 'saw'],
            ['line' => 'SAW',   'code' => 'DEBUR-01',  'name' => 'Deburring & Staging Bench',       'workstation_type' => 'deburr'],

            ['line' => 'TURN',  'code' => 'LATHE-01',  'name' => 'CNC Lathe #1 (Doosan Puma)',      'workstation_type' => 'lathe'],
            ['line' => 'TURN',  'code' => 'LATHE-02',  'name' => 'CNC Lathe #2 (Okuma Genos)',      'workstation_type' => 'lathe'],
            ['line' => 'TURN',  'code' => 'LATHE-03',  'name' => 'CNC Lathe #3 (Haas ST-20)',       'workstation_type' => 'lathe'],
            ['line' => 'TURN',  'code' => 'BARFEED-01', 'name' => 'Bar Feeder #1',                  'workstation_type' => 'lathe'],

            ['line' => 'MILL',  'code' => 'MILL-01',   'name' => 'VMC #1 (Haas VF-3)',              'workstation_type' => 'mill'],
            ['line' => 'MILL',  'code' => 'MILL-02',   'name' => 'VMC #2 (DMG Mori)',               'workstation_type' => 'mill'],
            ['line' => 'MILL',  'code' => 'MILL-03',   'name' => '5-axis Centre (Hermle C22)',      'workstation_type' => 'mill'],
            ['line' => 'MILL',  'code' => 'MILL-04',   'name' => 'VMC #4 (Haas VF-2)',              'workstation_type' => 'mill'],

            ['line' => 'HEAT',  'code' => 'FURN-01',   'name' => 'Hardening Furnace',               'workstation_type' => 'furnace'],
            ['line' => 'HEAT',  'code' => 'QUENCH-01', 'name' => 'Quench Tank',                     'workstation_type' => 'furnace'],
            ['line' => 'HEAT',  'code' => 'TEMPER-01', 'name' => 'Tempering Oven',                  'workstation_type' => 'furnace'],

            ['line' => 'GRIND', 'code' => 'GRIND-CYL-01', 'name' => 'Cylindrical Grinder (Studer)', 'workstation_type' => 'grinder'],
            ['line' => 'GRIND', 'code' => 'GRIND-SUR-01', 'name' => 'Surface Grinder (Okamoto)',    'workstation_type' => 'grinder'],

            ['line' => 'QC',    'code' => 'CMM-01',    'name' => 'CMM (Zeiss Contura)',             'workstation_type' => 'cmm'],
            ['line' => 'QC',    'code' => 'PACK-01',   'name' => 'Packing & Dispatch Bench',        'workstation_type' => 'packing'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $result[$def['code']] = Workstation::updateOrCreate(
                ['code' => $def['code']],
                [
                    'line_id' => $lines[$def['line']]->id,
                    'name' => $def['name'],
                    'workstation_type' => $def['workstation_type'],
                    'is_active' => true,
                ]
            );
        }

        return $result;
    }

    // ── Product types ────────────────────────────────────────────────────────

    /** @return array<string, ProductType> */
    private function seedProductTypes(): array
    {
        $defs = [
            ['code' => 'SHAFT40',    'name' => 'Drive Shaft Ø40',       'description' => 'Hardened and ground drive shaft, Ø40 h6, keyway to DIN 6885',           'unit_of_measure' => 'pcs'],
            ['code' => 'FLANGE150',  'name' => 'Pipe Flange DN150',     'description' => 'Weld-neck flange DN150 PN16, machined face and bolt circle',            'unit_of_measure' => 'pcs'],
            ['code' => 'HOUSING',    'name' => 'Gearbox Housing',       'description' => 'Cast-iron gearbox housing, bores line-machined on the 5-axis',          'unit_of_measure' => 'pcs'],
            ['code' => 'PINION18',   'name' => 'Pinion Z18',            'description' => 'Spur pinion, 18 teeth, module 3, case-hardened and ground',             'unit_of_measure' => 'pcs'],
            ['code' => 'BUSHING',    'name' => 'Bronze Bushing',        'description' => 'CuSn12 plain bearing bush, turned and grooved for lubrication',         'unit_of_measure' => 'pcs'],
            ['code' => 'MANIFOLD',   'name' => 'Hydraulic Manifold',    'description' => 'Aluminium manifold block, cross-drilled, ports to ISO 6149',            'unit_of_measure' => 'pcs'],
            ['code' => 'BASEPLATE',  'name' => 'Machine Base Plate',    'description' => 'S355 base plate, flame-cut then milled flat and drilled',               'unit_of_measure' => 'pcs'],
            // Sub-assemblies. Each needs a product type because that is what a
            // routing is written against, and the routing is what lets a BOM
            // line for it explode into the level below.
            ['code' => 'SA_BILLET',  'name' => 'Sawn Billet Ø45',       'description' => 'Bar stock cut to length, ends faced and deburred, ready for turning',   'unit_of_measure' => 'pcs'],
            ['code' => 'SA_ROUGH',   'name' => 'Rough-turned Shaft',    'description' => 'Billet turned to pre-grind dimensions with grinding stock left on',     'unit_of_measure' => 'pcs'],
            ['code' => 'SA_HARD',    'name' => 'Hardened Shaft Blank',  'description' => 'Rough-turned shaft hardened and tempered to 58-62 HRC',                 'unit_of_measure' => 'pcs'],
            ['code' => 'SA_FLBLANK', 'name' => 'Flame-cut Flange Blank', 'description' => 'Plate blank cut to outside diameter with machining allowance',         'unit_of_measure' => 'pcs'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $result[$def['code']] = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
        }

        return $result;
    }

    // ── Process templates ────────────────────────────────────────────────────

    /** @return array<string, ProcessTemplate> */
    private function seedProcessTemplates(array $pt, array $ws): array
    {
        $t = [];

        $t['SHAFT40'] = $this->createTemplate($pt['SHAFT40'], 'Drive Shaft Ø40 — machining v2', [
            [1, 'Drawing & revision check', 'Confirm the drawing revision against the order. A superseded revision is the most expensive mistake on this part.', 8, null],
            [2, 'Blank check',              'Check the hardened blank for quench cracks and verify the hardness certificate is attached.', 10, $ws['DEBUR-01'] ?? null],
            [3, 'Finish turning',           'Turn to pre-grind size. Leave 0.3 mm on the bearing diameters for grinding.', 35, $ws['LATHE-01'] ?? null],
            [4, 'Keyway milling',           'Mill the keyway to DIN 6885 A. Check width with a slip gauge before running the batch.', 20, $ws['MILL-01'] ?? null],
            [5, 'Cylindrical grinding',     'Grind the bearing seats to Ø40 h6. Dress the wheel between every five parts.', 30, $ws['GRIND-CYL-01'] ?? null],
            [6, 'CMM inspection',           'Measure runout, diameters and keyway position. First and last part of every batch, full report.', 18, $ws['CMM-01'] ?? null],
            [7, 'Preserve & pack',          'Oil, VCI bag and box with the measurement report.', 10, $ws['PACK-01'] ?? null],
        ]);

        $t['FLANGE150'] = $this->createTemplate($pt['FLANGE150'], 'Pipe Flange DN150 — machining v1', [
            [1, 'Blank check',       'Check the cut blank for slag and confirm plate certification.', 8, $ws['DEBUR-01'] ?? null],
            [2, 'Face & bore',       'Face both sides and bore the centre to drawing. Watch for interrupted cut on the flame-cut edge.', 28, $ws['LATHE-02'] ?? null],
            [3, 'Bolt circle',       'Drill the bolt circle on the 4th-axis fixture. Deburr both sides before it leaves the machine.', 22, $ws['MILL-02'] ?? null],
            [4, 'Sealing face',      'Machine the raised face and turn the serration to the specified finish.', 15, $ws['LATHE-02'] ?? null],
            [5, 'Inspection',        'Check bolt circle position, bore and face flatness.', 12, $ws['CMM-01'] ?? null],
            [6, 'Preserve & pack',   'Wire-brush, oil and stack on the export pallet.', 8, $ws['PACK-01'] ?? null],
        ]);

        $t['HOUSING'] = $this->createTemplate($pt['HOUSING'], 'Gearbox Housing — 5-axis v1', [
            [1, 'Casting inspection', 'Check the casting for porosity and confirm there is stock on every machined face.', 15, $ws['DEBUR-01'] ?? null],
            [2, 'Datum faces',        'Machine the datum face and two locating holes — everything else references these.', 30, $ws['MILL-02'] ?? null],
            [3, 'Bore machining',     'Line-bore the shaft bores in one setup. Let the casting stabilise before the finishing pass.', 55, $ws['MILL-03'] ?? null],
            [4, 'Drill & tap',        'Drill and tap the cover pattern. Run a thread gauge on the first part.', 25, $ws['MILL-03'] ?? null],
            [5, 'Wash & deburr',      'Wash out the swarf and deburr every edge — chips left in a housing end up in the gearbox.', 18, $ws['DEBUR-01'] ?? null],
            [6, 'CMM inspection',     'Measure bore alignment and centre distance against the drawing.', 25, $ws['CMM-01'] ?? null],
            [7, 'Preserve & pack',    'Protect the bores, crate and label.', 12, $ws['PACK-01'] ?? null],
        ]);

        $t['PINION18'] = $this->createTemplate($pt['PINION18'], 'Pinion Z18 — machining v1', [
            [1, 'Billet check',      'Verify the billet length and material grade before it goes on the machine.', 6, $ws['DEBUR-01'] ?? null],
            [2, 'Turn blank',        'Turn the gear blank, leaving grinding stock on the bore and faces.', 25, $ws['LATHE-03'] ?? null],
            [3, 'Tooth cutting',     'Cut the teeth, module 3, Z18. Check span measurement on the first part.', 40, $ws['MILL-04'] ?? null],
            [4, 'Case hardening',    'Case harden and temper. Record furnace chart with the batch.', 45, $ws['FURN-01'] ?? null],
            [5, 'Bore grinding',     'Grind the bore back to size after heat treatment — it will have moved.', 22, $ws['GRIND-CYL-01'] ?? null],
            [6, 'Inspection',        'Check tooth profile, bore and hardness. Hardness on every batch, no exceptions.', 20, $ws['CMM-01'] ?? null],
            [7, 'Preserve & pack',   'Oil, wrap and box.', 8, $ws['PACK-01'] ?? null],
        ]);

        $t['BUSHING'] = $this->createTemplate($pt['BUSHING'], 'Bronze Bushing — turning v1', [
            [1, 'Bar setup',       'Load bronze bar in the feeder. Bronze cuts fast — reduce feed on the first part and watch the finish.', 12, $ws['BARFEED-01'] ?? null],
            [2, 'Turn & bore',     'Turn the outside diameter and bore to size in one setup.', 20, $ws['LATHE-03'] ?? null],
            [3, 'Lubrication grooves', 'Cut the internal grooves and cross-drill the oil hole.', 14, $ws['LATHE-03'] ?? null],
            [4, 'Deburr & inspect', 'Deburr the bore ends and check wall thickness.', 10, $ws['DEBUR-01'] ?? null],
            [5, 'Pack',            'Bag in fifties with a count label.', 6, $ws['PACK-01'] ?? null],
        ]);

        $t['MANIFOLD'] = $this->createTemplate($pt['MANIFOLD'], 'Hydraulic Manifold — milling v1', [
            [1, 'Saw & square',   'Square the aluminium block on all six faces.', 20, $ws['MILL-04'] ?? null],
            [2, 'Cross drilling', 'Drill the cross-bores in sequence. Deburr each intersection as you go — a burr inside a manifold fails the flow test.', 45, $ws['MILL-01'] ?? null],
            [3, 'Port machining', 'Machine the ISO 6149 ports and tap to depth.', 30, $ws['MILL-01'] ?? null],
            [4, 'Flush & test',   'Flush the passages and pressure-test to 250 bar. Log the reading.', 25, $ws['CMM-01'] ?? null],
            [5, 'Pack',           'Plug the ports, bag and box.', 10, $ws['PACK-01'] ?? null],
        ]);

        $t['BASEPLATE'] = $this->createTemplate($pt['BASEPLATE'], 'Machine Base Plate — milling v1', [
            [1, 'Blank check',   'Check the flame-cut blank for straightness; a bowed plate will not clamp flat.', 10, $ws['DEBUR-01'] ?? null],
            [2, 'Face both sides', 'Mill both faces flat, taking light cuts so the plate does not spring after unclamping.', 35, $ws['MILL-02'] ?? null],
            [3, 'Drill pattern', 'Drill and counterbore the mounting pattern.', 25, $ws['MILL-04'] ?? null],
            [4, 'Deburr',        'Break all edges and remove the burr under the plate.', 12, $ws['DEBUR-01'] ?? null],
            [5, 'Inspection',    'Check flatness and hole positions.', 15, $ws['CMM-01'] ?? null],
            [6, 'Pack',          'Strap to a pallet with edge protection.', 10, $ws['PACK-01'] ?? null],
        ]);

        // ── Sub-assembly routings ────────────────────────────────────────────

        $t['SA_BILLET'] = $this->createTemplate($pt['SA_BILLET'], 'Sawn Billet Ø45 — cutting v1', [
            [1, 'Saw to length', 'Cut to length plus facing allowance. Check the first and every tenth piece with a rule.', 6, $ws['SAW-01'] ?? null],
            [2, 'Face & deburr', 'Face both ends and break the edge so the chuck grips square.', 5, $ws['DEBUR-01'] ?? null],
        ]);

        $t['SA_ROUGH'] = $this->createTemplate($pt['SA_ROUGH'], 'Rough-turned Shaft — turning v1', [
            [1, 'Rough turn', 'Turn down to within 2 mm of finish size. Heavy cuts here, finishing comes later.', 22, $ws['LATHE-01'] ?? null],
            [2, 'Centre drill', 'Centre-drill both ends for grinding between centres.', 8, $ws['LATHE-01'] ?? null],
        ]);

        $t['SA_HARD'] = $this->createTemplate($pt['SA_HARD'], 'Hardened Shaft Blank — heat treatment v1', [
            [1, 'Furnace load',  'Load the basket and set the cycle for 42CrMo4. Record the charge number.', 20, $ws['FURN-01'] ?? null],
            [2, 'Quench',        'Quench in oil. Watch the bath temperature — a hot bath gives soft spots.', 15, $ws['QUENCH-01'] ?? null],
            [3, 'Temper',        'Temper to 58-62 HRC and let it cool in still air.', 30, $ws['TEMPER-01'] ?? null],
            [4, 'Hardness test', 'Take three hardness readings per batch and file the chart.', 10, $ws['CMM-01'] ?? null],
        ]);

        $t['SA_FLBLANK'] = $this->createTemplate($pt['SA_FLBLANK'], 'Flame-cut Flange Blank — cutting v1', [
            [1, 'Cut blank',  'Cut to outside diameter with machining allowance all round.', 12, $ws['SAW-02'] ?? null],
            [2, 'Grind slag', 'Grind the cut edge clean — slag wrecks carbide on the first turning pass.', 8, $ws['DEBUR-01'] ?? null],
        ]);

        return $t;
    }

    /** @param array<int, array{0:int,1:string,2:string,3:int,4:Workstation|null}> $steps */
    private function createTemplate(ProductType $productType, string $name, array $steps): ProcessTemplate
    {
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'version' => 1],
            ['name' => $name, 'is_active' => true]
        );

        foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $workstation]) {
            // Match live rows only. template_steps is soft-deletable and
            // updateOrInsert() runs without the model's scope, so leaving
            // deleted_at out would let a re-run resurrect a step the user had
            // deleted rather than inserting a fresh one.
            DB::table('template_steps')->updateOrInsert(
                ['process_template_id' => $template->id, 'step_number' => $stepNo, 'deleted_at' => null],
                [
                    'name' => $stepName,
                    'instruction' => $instruction,
                    'estimated_duration_minutes' => $duration,
                    'workstation_id' => $workstation?->id,
                    'created_at' => now(),
                ]
            );
        }

        return $template;
    }

    // ── Users ────────────────────────────────────────────────────────────────

    /** @return array<int, User> */
    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole = Role::where('name', 'Operator')->first();

        $users = [];

        $supervisor = User::updateOrCreate(
            ['username' => 'robert.nowak'],
            [
                'name' => 'Robert Nowak',
                'email' => 'robert.nowak@precisionparts.local',
                'password' => Hash::make('Supervisor1!'),
                'account_type' => 'user',
                'force_password_change' => false,
            ]
        );
        if ($supervisorRole && ! $supervisor->hasRole('Supervisor')) {
            $supervisor->assignRole($supervisorRole);
        }
        $supervisor->lines()->syncWithoutDetaching(array_map(fn ($l) => $l->id, $lines));
        $users[] = $supervisor;

        $operatorDefs = [
            ['username' => 'tomasz.lis',    'name' => 'Tomasz Lis',    'email' => 'tomasz.lis@precisionparts.local',    'lines' => ['TURN', 'SAW']],
            ['username' => 'marta.kowal',   'name' => 'Marta Kowal',   'email' => 'marta.kowal@precisionparts.local',   'lines' => ['MILL']],
            ['username' => 'piotr.zieba',   'name' => 'Piotr Zięba',   'email' => 'piotr.zieba@precisionparts.local',   'lines' => ['HEAT', 'GRIND']],
            ['username' => 'ewa.baran',     'name' => 'Ewa Baran',     'email' => 'ewa.baran@precisionparts.local',     'lines' => ['QC']],
        ];

        foreach ($operatorDefs as $def) {
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
            $user->lines()->syncWithoutDetaching(collect($def['lines'])->map(fn ($c) => $lines[$c]->id)->all());
            $users[] = $user;
        }

        return $users;
    }

    // ── Customers ────────────────────────────────────────────────────────────

    /** @return array<int, Customer> */
    private function seedCustomers(): array
    {
        $defs = [
            ['code' => 'CUST-HYDROMAX', 'name' => 'HydroMax Systems',        'tier' => Tier::Vip,    'payment_score' => 95, 'notes' => 'Hydraulics OEM. Manifolds and shafts against a rolling frame contract; drawings always current.'],
            ['code' => 'CUST-GEARTEC',  'name' => 'GearTec Antriebstechnik', 'tier' => Tier::Gold,   'payment_score' => 89, 'notes' => 'Gearbox builder. Pinions and housings, full dimensional report required with every batch.'],
            ['code' => 'CUST-VALVCO',   'name' => 'ValvCo Energy',           'tier' => Tier::Gold,   'payment_score' => 74, 'notes' => 'Flanges and valve bodies for energy work. Material certificates are non-negotiable.'],
            ['code' => 'CUST-AUTOTIER', 'name' => 'AutoTier Components',     'tier' => Tier::Silver, 'payment_score' => 66, 'notes' => 'Automotive tier-2. High volume, tight tolerances, unforgiving on delivery dates.'],
            ['code' => 'CUST-AGRIMECH', 'name' => 'AgriMech Maszyny',        'tier' => Tier::Silver, 'payment_score' => 57, 'notes' => 'Agricultural machinery. Seasonal peaks before spring; flexible on lead time otherwise.'],
            ['code' => 'CUST-STALBUD',  'name' => 'Stalbud Konstrukcje',     'tier' => Tier::Bronze, 'payment_score' => 43, 'notes' => 'Local steel fabricator. Base plates and brackets, price sensitive, pays late.'],
        ];

        $customers = [];
        foreach ($defs as $def) {
            $customers[] = Customer::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'tier' => $def['tier'],
                    'payment_score' => $def['payment_score'],
                    'notes' => $def['notes'],
                    'is_active' => true,
                ]
            );
        }

        return $customers;
    }

    /**
     * Put a customer behind every order, by position rather than at random so
     * the same order keeps the same customer across re-seeds.
     *
     * @param  array<int, Customer>  $customers
     */
    private function assignCustomers(array $customers): void
    {
        if ($customers === []) {
            return;
        }

        foreach (WorkOrder::orderBy('order_no')->get()->values() as $i => $order) {
            $customer = $customers[$i % count($customers)];

            $order->forceFill([
                'customer_id' => $customer->id,
                'customer_order_no' => sprintf('PO-%s-%04d', now()->year, 3000 + $i),
            ])->saveQuietly();
        }
    }

    // ── Work orders ──────────────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        // Fixed seed: the generated half of this board picks lines, products,
        // quantities and statuses at random, and without pinning the sequence a
        // re-run reshuffles them — which also changes how many multi-line
        // segments come out, since an order's line decides whether it hands off.
        mt_srand(20260202);

        $orders = [
            [
                'order_no' => 'WO-MS-0001',
                'line_id' => $lines['TURN']->id,
                'product_type_id' => $pt['SHAFT40']->id,
                'planned_qty' => 120,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 4,
                'due_date' => now()->addDays(2)->setTime(14, 0),
                'planned_start_at' => now()->setTime(6, 0),
                'planned_end_at' => now()->addDay()->setTime(14, 0),
                'description' => 'Drive shafts — HydroMax frame contract, batch 3 of 8.',
            ],
            [
                'order_no' => 'WO-MS-0002',
                'line_id' => $lines['MILL']->id,
                'product_type_id' => $pt['HOUSING']->id,
                'planned_qty' => 24,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 5,
                'due_date' => now()->addDay()->setTime(14, 0),
                'planned_start_at' => now()->setTime(14, 0),
                'planned_end_at' => now()->addDay()->setTime(22, 0),
                'description' => 'Gearbox housings — GearTec, line-bored on the 5-axis.',
            ],
            [
                'order_no' => 'WO-MS-0003',
                'line_id' => $lines['TURN']->id,
                'product_type_id' => $pt['FLANGE150']->id,
                'planned_qty' => 80,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => now()->addDays(4)->setTime(14, 0),
                'planned_start_at' => now()->addDays(2)->setTime(6, 0),
                'planned_end_at' => now()->addDays(3)->setTime(14, 0),
                'description' => 'DN150 flanges — ValvCo, certificates to ship with the parts.',
            ],
            [
                'order_no' => 'WO-MS-0004',
                'line_id' => $lines['MILL']->id,
                'product_type_id' => $pt['MANIFOLD']->id,
                'planned_qty' => 40,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 4,
                'due_date' => now()->addDays(6)->setTime(14, 0),
                'planned_start_at' => now()->addDays(3)->setTime(6, 0),
                'planned_end_at' => now()->addDays(5)->setTime(14, 0),
                'description' => 'Hydraulic manifolds — pressure test report required.',
            ],
            [
                'order_no' => 'WO-MS-0005',
                'line_id' => $lines['TURN']->id,
                'product_type_id' => $pt['BUSHING']->id,
                'planned_qty' => 500,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(8)->setTime(14, 0),
                'planned_start_at' => now()->addDays(5)->setTime(14, 0),
                'planned_end_at' => now()->addDays(7)->setTime(22, 0),
                'description' => 'Bronze bushings — AgriMech stock order, bar-fed run.',
            ],
            [
                'order_no' => 'WO-MS-0006',
                'line_id' => $lines['MILL']->id,
                'product_type_id' => $pt['PINION18']->id,
                'planned_qty' => 60,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 4,
                'due_date' => now()->addDays(10)->setTime(14, 0),
                'planned_start_at' => now()->addDays(7)->setTime(6, 0),
                'planned_end_at' => now()->addDays(9)->setTime(14, 0),
                'description' => 'Pinions Z18 — case hardened, hardness chart with the batch.',
            ],
            [
                'order_no' => 'WO-MS-0007',
                'line_id' => $lines['MILL']->id,
                'product_type_id' => $pt['BASEPLATE']->id,
                'planned_qty' => 35,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 1,
                'due_date' => now()->addDays(12)->setTime(14, 0),
                'planned_start_at' => now()->addDays(9)->setTime(6, 0),
                'planned_end_at' => now()->addDays(11)->setTime(14, 0),
                'description' => 'Base plates — Stalbud, milled flat both sides.',
            ],
            [
                'order_no' => 'WO-MS-0008',
                'line_id' => $lines['TURN']->id,
                'product_type_id' => $pt['SHAFT40']->id,
                'planned_qty' => 90,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 3,
                'due_date' => now()->subDays(3)->setTime(14, 0),
                'planned_start_at' => now()->subDays(5)->setTime(6, 0),
                'planned_end_at' => now()->subDays(3)->setTime(14, 0),
                'completed_at' => now()->subDays(3)->setTime(13, 30),
                'description' => 'Drive shafts — previous batch, shipped complete.',
            ],
        ];

        foreach ($orders as $orderData) {
            WorkOrder::updateOrCreate(
                ['order_no' => $orderData['order_no']],
                array_merge($orderData, [
                    'produced_qty' => match ($orderData['status']) {
                        WorkOrder::STATUS_DONE => $orderData['planned_qty'],
                        WorkOrder::STATUS_IN_PROGRESS => intdiv($orderData['planned_qty'], 3),
                        default => 0,
                    },
                ])
            );
        }

        // Fill the board: the current week busy, the neighbours tapering, four
        // weeks out still showing work. Machining runs long, so quantities are
        // tens rather than hundreds and a shift holds fewer orders than a print
        // shop's would.
        $allLines = array_values($lines);
        $allPt = array_values(array_intersect_key($pt, array_flip([
            'SHAFT40', 'FLANGE150', 'HOUSING', 'PINION18', 'BUSHING', 'MANIFOLD', 'BASEPLATE',
        ])));

        $descriptions = [
            'Repeat order — same drawing revision as last run',
            'Frame contract call-off',
            'Prototype batch — first article report required',
            'Rework of a rejected delivery',
            'Spares order — machine down at the customer',
            'Stock build ahead of the seasonal peak',
            'New part number — quoting off this batch',
            'Small batch, tight tolerance — run on the 5-axis',
            'Material supplied by the customer',
            'Split delivery — half now, half next month',
        ];

        // Representative start hour per shift column. The planner derives a
        // block's column from planned_start_at's time.
        $shifts = [['h' => 8, 'night' => false], ['h' => 16, 'night' => false], ['h' => 23, 'night' => true]];
        // Only the machining lines run nights — heat treatment and inspection
        // are day work here.
        $nightLines = ['TURN', 'MILL'];

        $weekFill = [-1 => 0.35, 0 => 0.80, 1 => 0.55, 2 => 0.42, 3 => 0.30, 4 => 0.20];

        $weekStart = now()->startOfWeek();
        $n = 100;

        foreach ($weekFill as $weekOffset => $fill) {
            foreach ($allLines as $line) {
                for ($d = 0; $d < 7; $d++) {
                    foreach ($shifts as $shift) {
                        if ($shift['night'] && (! in_array($line->code, $nightLines, true) || $d > 3)) {
                            continue;
                        }
                        if ($d >= 5 && $weekOffset > 0) {
                            continue; // weekends only run in the busy near term
                        }
                        if (mt_rand(1, 100) > (int) round($fill * 100)) {
                            continue;
                        }

                        $n++;
                        $start = $weekStart->copy()->addWeeks($weekOffset)->addDays($d)->setTime($shift['h'], 0);
                        $end = $start->copy()->addHours(8);
                        $qty = mt_rand(2, 12) * 10;

                        $status = match (true) {
                            $end->lt(now()) => WorkOrder::STATUS_DONE,
                            $start->lt(now()) && $end->gt(now()) => WorkOrder::STATUS_IN_PROGRESS,
                            default => [
                                WorkOrder::STATUS_PENDING,
                                WorkOrder::STATUS_ACCEPTED,
                                WorkOrder::STATUS_PENDING,
                                WorkOrder::STATUS_PENDING,
                            ][mt_rand(0, 3)],
                        };

                        WorkOrder::updateOrCreate(
                            ['order_no' => sprintf('WO-MS-%04d', $n)],
                            [
                                'line_id' => $line->id,
                                'product_type_id' => $allPt[array_rand($allPt)]->id,
                                'planned_qty' => $qty,
                                'produced_qty' => match ($status) {
                                    WorkOrder::STATUS_DONE => $qty,
                                    WorkOrder::STATUS_IN_PROGRESS => intdiv($qty, 2),
                                    default => 0,
                                },
                                'status' => $status,
                                'priority' => mt_rand(1, 5),
                                'due_date' => $end->copy()->addDays(mt_rand(0, 2)),
                                'planned_start_at' => $start,
                                'planned_end_at' => $end,
                                'description' => $descriptions[array_rand($descriptions)],
                                'completed_at' => $status === WorkOrder::STATUS_DONE ? $end : null,
                            ]
                        );
                    }
                }
            }
        }
    }

    // ── Multi-line orders ────────────────────────────────────────────────────

    /**
     * Orders that occupy more than one line.
     *
     * A machined part genuinely moves down the shop — sawn, turned, hardened,
     * ground, measured — so an order sits on several lines before it ships. The
     * planner draws this as a badge on the primary block plus a connector to
     * the extra segment.
     *
     * @param  array<string, Line>  $lines
     */
    private function seedMultiLinePlacements(array $lines): void
    {
        // from line => [[to line, shifts later], …], following the route metal
        // actually takes through the shop.
        $handoffs = [
            'SAW' => [['TURN', 1]],
            'TURN' => [['HEAT', 1], ['GRIND', 2]],
            'MILL' => [['QC', 2]],
            'HEAT' => [['GRIND', 1]],
            'GRIND' => [['QC', 1]],
        ];

        $lineById = [];
        foreach ($lines as $code => $line) {
            $lineById[$line->id] = $code;
        }

        $orders = WorkOrder::whereNotNull('planned_start_at')->orderBy('order_no')->get();

        // Wipe every candidate's segments before laying any down: the generator
        // assigns lines at random, so a re-run can both move orders and pick a
        // different subset, stranding the previous run's segments.
        WorkOrderPlacement::whereIn('work_order_id', $orders->pluck('id'))->delete();

        $n = 0;

        foreach ($orders as $order) {
            $fromCode = $lineById[$order->line_id] ?? null;
            if (! $fromCode || ! isset($handoffs[$fromCode])) {
                continue;
            }

            if ($n++ % 4 !== 0) {
                continue;
            }

            $start = $order->planned_start_at->copy();

            foreach ($handoffs[$fromCode] as [$toCode, $shiftsLater]) {
                $target = $lines[$toCode] ?? null;
                if (! $target || $target->id === $order->line_id) {
                    continue;
                }

                // shift_number is 1..3 within a day; roll into the next day
                // rather than emitting a fourth shift no column matches.
                $shift = (int) ceil($start->hour / 8) + $shiftsLater;
                $dayOffset = intdiv($shift - 1, 3);
                $shift = (($shift - 1) % 3) + 1;

                WorkOrderPlacement::updateOrCreate(
                    ['work_order_id' => $order->id, 'line_id' => $target->id],
                    [
                        'due_date' => $start->copy()->addDays($dayOffset)->startOfDay(),
                        'shift_number' => $shift,
                    ]
                );
            }
        }
    }

    // ── Shifts ───────────────────────────────────────────────────────────────

    private function seedShifts(array $lines): void
    {
        $weekdays = [1, 2, 3, 4, 5];
        $monToThu = [1, 2, 3, 4];

        $defs = [
            [
                'name' => 'Morning Shift', 'code' => 'SM',
                'start_time' => '06:00', 'end_time' => '14:00',
                'days_of_week' => $weekdays, 'line_codes' => ['SAW', 'TURN', 'MILL', 'HEAT', 'GRIND', 'QC'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Afternoon Shift', 'code' => 'SA',
                'start_time' => '14:00', 'end_time' => '22:00',
                'days_of_week' => $weekdays, 'line_codes' => ['SAW', 'TURN', 'MILL', 'HEAT', 'GRIND', 'QC'],
                'sort_order' => 2,
            ],
            [
                // Lights-out running on the CNC lines only — the rest is day work.
                'name' => 'Night Shift', 'code' => 'SN',
                'start_time' => '22:00', 'end_time' => '06:00',
                'days_of_week' => $monToThu, 'line_codes' => ['TURN', 'MILL'],
                'sort_order' => 3,
            ],
        ];

        foreach ($defs as $def) {
            foreach ($def['line_codes'] as $lineCode) {
                Shift::updateOrCreate(
                    ['code' => $def['code'].'-'.substr($lineCode, 0, 4)],
                    [
                        'name' => $def['name'],
                        'start_time' => $def['start_time'],
                        'end_time' => $def['end_time'],
                        'days_of_week' => $def['days_of_week'],
                        'line_id' => $lines[$lineCode]->id,
                        'is_active' => true,
                        'sort_order' => $def['sort_order'],
                    ]
                );
            }
        }
    }

    // ── Materials ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, ProcessTemplate>  $templates
     * @return array<string, Material>
     */
    private function seedMaterials(array $templates): array
    {
        $this->call(MaterialTypesSeeder::class);

        $typeIds = MaterialType::pluck('id', 'code');

        $defs = [
            // Bar and plate stock.
            ['code' => 'MAT-BAR-42CRMO4', 'name' => 'Alloy steel bar Ø45 (42CrMo4)', 'type' => 'raw_material', 'unit' => 'm',   'stock' => 340,  'price' => 38.50, 'supplier' => 'Stalprofil'],
            ['code' => 'MAT-BAR-C45',     'name' => 'Carbon steel bar Ø50 (C45)',    'type' => 'raw_material', 'unit' => 'm',   'stock' => 260,  'price' => 24.80, 'supplier' => 'Stalprofil'],
            ['code' => 'MAT-PLATE-S355',  'name' => 'Steel plate S355, 20 mm',       'type' => 'raw_material', 'unit' => 'm2',  'stock' => 95,   'price' => 210.00, 'supplier' => 'ThyssenKrupp'],
            ['code' => 'MAT-BAR-AL6082',  'name' => 'Aluminium bar 6082 T6, 80 mm',  'type' => 'raw_material', 'unit' => 'm',   'stock' => 120,  'price' => 46.00, 'supplier' => 'AluFab Sp. z o.o.'],
            ['code' => 'MAT-BRONZE',      'name' => 'Bronze bar CuSn12, Ø60',        'type' => 'raw_material', 'unit' => 'm',   'stock' => 48,   'price' => 168.00, 'supplier' => 'MetalCentrum'],
            ['code' => 'MAT-CAST-HOUS',   'name' => 'Gearbox housing casting',       'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 70,   'price' => 145.00, 'supplier' => 'Odlewnia Śląsk'],

            // Consumables — the things that actually run out mid-shift.
            ['code' => 'MAT-INSERT-CNMG', 'name' => 'Turning insert CNMG 120408',    'type' => 'auxiliary',    'unit' => 'pcs', 'stock' => 420,  'price' => 18.20, 'supplier' => 'Sandvik'],
            ['code' => 'MAT-ENDMILL-12',  'name' => 'Carbide end mill Ø12, 4-flute', 'type' => 'auxiliary',    'unit' => 'pcs', 'stock' => 65,   'price' => 96.00, 'supplier' => 'Sandvik'],
            ['code' => 'MAT-COOLANT',     'name' => 'Cutting fluid concentrate',     'type' => 'auxiliary',    'unit' => 'litre', 'stock' => 180, 'price' => 22.40, 'supplier' => 'Blaser'],
            ['code' => 'MAT-QUENCH-OIL',  'name' => 'Quenching oil',                 'type' => 'auxiliary',    'unit' => 'litre', 'stock' => 640, 'price' => 14.90, 'supplier' => 'Petrofer'],

            // Packaging.
            ['code' => 'MAT-VCI-BAG',     'name' => 'VCI corrosion-inhibiting bag',  'type' => 'packaging',    'unit' => 'pcs', 'stock' => 1500, 'price' => 1.30, 'supplier' => 'PackLine'],
            ['code' => 'MAT-PALLET',      'name' => 'Export pallet, heat-treated',   'type' => 'packaging',    'unit' => 'pcs', 'stock' => 85,   'price' => 42.00, 'supplier' => 'PackLine'],
        ];

        $materials = [];
        foreach ($defs as $def) {
            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $typeIds[$def['type']] ?? null,
                    'unit_of_measure' => $def['unit'],
                    'tracking_type' => 'batch',
                    'is_manufactured' => false,
                    'stock_quantity' => $def['stock'],
                    'unit_price' => $def['price'],
                    'supplier_name' => $def['supplier'],
                    'is_active' => true,
                ]
            );
        }

        // The sub-assemblies. `is_manufactured` plus the routing that produces
        // them is what lets a BOM line for one explode into the level below.
        //
        // Stock is uneven on purpose: the sawn billet is held short, so a shaft
        // order has to be netted three levels down before the shortage appears.
        $subAssemblies = [
            ['code' => 'SA-BILLET',  'template' => 'SA_BILLET',  'name' => 'Sawn Billet Ø45',        'stock' => 55],
            ['code' => 'SA-ROUGH',   'template' => 'SA_ROUGH',   'name' => 'Rough-turned Shaft',     'stock' => 90],
            ['code' => 'SA-HARD',    'template' => 'SA_HARD',    'name' => 'Hardened Shaft Blank',   'stock' => 40],
            ['code' => 'SA-FLBLANK', 'template' => 'SA_FLBLANK', 'name' => 'Flame-cut Flange Blank', 'stock' => 220],
        ];

        $semiFinished = $typeIds['semi_finished'] ?? null;

        foreach ($subAssemblies as $def) {
            $template = $templates[$def['template']] ?? null;
            if (! $template) {
                continue;
            }

            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $semiFinished,
                    'unit_of_measure' => 'pcs',
                    'tracking_type' => 'batch',
                    'is_manufactured' => true,
                    'producing_process_template_id' => $template->id,
                    'stock_quantity' => $def['stock'],
                    'is_active' => true,
                ]
            );
        }

        return $materials;
    }

    // ── Bill of materials ────────────────────────────────────────────────────

    /**
     * What each part is made from, and what the sub-assemblies are made from in
     * turn.
     *
     * The drive shaft runs three manufactured levels deep — hardened blank,
     * then rough-turned shaft, then sawn billet — so a shortage can surface
     * well below the first manufactured line. The billet feeds the pinion too,
     * so netting has to sum both parents' demand before deciding how many bars
     * to cut.
     *
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<string, Material>  $materials
     */
    private function seedBom(array $templates, array $materials): void
    {
        // [product => [[step number, material code, qty per unit, scrap %, consumed at], …]]
        $defs = [
            'SHAFT40' => [
                [2, 'SA-HARD',         1,     1, 'start'],
                [5, 'MAT-COOLANT',     0.04,  0, 'during'],
                [7, 'MAT-VCI-BAG',     1,     0, 'end'],
            ],
            'PINION18' => [
                // The billet's other parent — netting has to add this to the
                // shaft's demand before deciding how much bar to cut.
                [2, 'SA-BILLET',       1,     2, 'start'],
                [3, 'MAT-ENDMILL-12',  0.05,  0, 'during'],
                [4, 'MAT-QUENCH-OIL',  0.15,  0, 'during'],
                [7, 'MAT-VCI-BAG',     1,     0, 'end'],
            ],
            'FLANGE150' => [
                [1, 'SA-FLBLANK',      1,     2, 'start'],
                [2, 'MAT-INSERT-CNMG', 0.08,  0, 'during'],
                [6, 'MAT-PALLET',      0.04,  0, 'end'],
            ],
            'HOUSING' => [
                [1, 'MAT-CAST-HOUS',   1,     3, 'start'],
                [3, 'MAT-ENDMILL-12',  0.12,  0, 'during'],
                [3, 'MAT-COOLANT',     0.08,  0, 'during'],
                [7, 'MAT-PALLET',      0.1,   0, 'end'],
            ],
            'BUSHING' => [
                [1, 'MAT-BRONZE',      0.075, 4, 'start'],
                [2, 'MAT-INSERT-CNMG', 0.03,  0, 'during'],
                [5, 'MAT-VCI-BAG',     0.02,  0, 'end'],
            ],
            'MANIFOLD' => [
                [1, 'MAT-BAR-AL6082',  0.14,  3, 'start'],
                [2, 'MAT-ENDMILL-12',  0.09,  0, 'during'],
                [5, 'MAT-VCI-BAG',     1,     0, 'end'],
            ],
            'BASEPLATE' => [
                [1, 'MAT-PLATE-S355',  0.18,  5, 'start'],
                [3, 'MAT-ENDMILL-12',  0.04,  0, 'during'],
                [6, 'MAT-PALLET',      0.05,  0, 'end'],
            ],

            // ── Sub-assemblies, deepest last ─────────────────────────────────
            'SA_HARD' => [
                [1, 'SA-ROUGH',        1,     1, 'start'],
                [2, 'MAT-QUENCH-OIL',  0.2,   0, 'during'],
            ],
            'SA_ROUGH' => [
                [1, 'SA-BILLET',       1,     2, 'start'],
                [1, 'MAT-INSERT-CNMG', 0.06,  0, 'during'],
            ],
            'SA_BILLET' => [
                [1, 'MAT-BAR-42CRMO4', 0.135, 6, 'start'],
            ],
            'SA_FLBLANK' => [
                [1, 'MAT-PLATE-S355',  0.05,  8, 'start'],
            ],
        ];

        foreach ($defs as $productCode => $lines) {
            $template = $templates[$productCode] ?? null;
            if (! $template) {
                continue;
            }

            $sortOrder = 0;

            foreach ($lines as [$stepNumber, $code, $qty, $scrap, $consumedAt]) {
                $material = $materials[$code] ?? null;
                if (! $material) {
                    continue;
                }

                // bom_items points at the step row, not its number.
                $stepId = DB::table('template_steps')
                    ->where('process_template_id', $template->id)
                    ->where('step_number', $stepNumber)
                    ->whereNull('deleted_at')
                    ->value('id');

                BomItem::updateOrCreate(
                    ['process_template_id' => $template->id, 'material_id' => $material->id],
                    [
                        'template_step_id' => $stepId,
                        'quantity_per_unit' => $qty,
                        'scrap_percentage' => $scrap,
                        'consumed_at' => $consumedAt,
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }
    }

    // ── Material lots ────────────────────────────────────────────────────────

    private function seedMaterialLots(array $materials): void
    {
        $lots = [
            ['lot' => 'HEAT-42CRMO4-8841', 'material' => 'MAT-BAR-42CRMO4', 'qty' => 120, 'unit' => 'm',     'supplier_lot' => 'SP-42CRMO4-8841'],
            ['lot' => 'HEAT-C45-7712',     'material' => 'MAT-BAR-C45',     'qty' => 90,  'unit' => 'm',     'supplier_lot' => 'SP-C45-7712'],
            ['lot' => 'HEAT-S355-4420',    'material' => 'MAT-PLATE-S355',  'qty' => 40,  'unit' => 'm2',    'supplier_lot' => 'TK-S355-4420'],
            ['lot' => 'HEAT-AL6082-1180',  'material' => 'MAT-BAR-AL6082',  'qty' => 60,  'unit' => 'm',     'supplier_lot' => 'AF-6082-1180'],
            ['lot' => 'HEAT-CUSN12-0304',  'material' => 'MAT-BRONZE',      'qty' => 24,  'unit' => 'm',     'supplier_lot' => 'MC-CUSN12-0304'],
            ['lot' => 'LOT-CNMG-2026-01',  'material' => 'MAT-INSERT-CNMG', 'qty' => 200, 'unit' => 'pcs',   'supplier_lot' => 'SV-CNMG-260114'],
            ['lot' => 'LOT-COOL-2026-01',  'material' => 'MAT-COOLANT',     'qty' => 100, 'unit' => 'litre', 'supplier_lot' => 'BL-CF-260120'],
        ];

        foreach ($lots as $def) {
            $material = $materials[$def['material']] ?? null;
            if (! $material) {
                continue;
            }

            MaterialLot::updateOrCreate(
                ['lot_number' => $def['lot']],
                [
                    'material_id' => $material->id,
                    'quantity_received' => $def['qty'],
                    'quantity_available' => $def['qty'],
                    'unit_of_measure' => $def['unit'],
                    'received_at' => now()->subDays(mt_rand(5, 30)),
                    'status' => 'available',
                    'supplier_lot_no' => $def['supplier_lot'],
                ]
            );
        }
    }

    // ── Reported issues ──────────────────────────────────────────────────────

    /**
     * Problems the floor reported.
     *
     * Placed so they are visible rather than merely present: the shift monitor
     * only draws an issue whose work order is on that station's line and whose
     * reported_at falls inside the shift being viewed, and the planner has no
     * notion of issues at all — a problem reaches the board only as a blocked
     * order, which is what a blocking issue type does on the shop floor.
     *
     * @param  array<string, Line>  $lines
     * @param  array<int, User>  $users
     */
    private function seedIssues(array $lines, array $users): void
    {
        $types = IssueType::pluck('id', 'code');

        // Prefer operators, but do not depend on roles existing: this seeder can
        // run before RolesAndPermissionsSeeder, and filtering on a role nobody
        // holds yet would silently report nothing at all.
        $reporters = collect($users)->filter(fn (User $u) => $u->hasRole('Operator'))->values();
        if ($reporters->isEmpty()) {
            $reporters = collect($users)->values();
        }

        if ($types->isEmpty() || $reporters->isEmpty()) {
            return;
        }

        $onLine = function (string $lineCode) use ($lines): ?WorkOrder {
            $line = $lines[$lineCode] ?? null;

            return $line
                ? WorkOrder::where('line_id', $line->id)->orderBy('order_no')->first()
                : null;
        };

        // [line, type, title, description, status, hours ago | null = right now]
        $defs = [
            ['TURN', 'TOOL_BREAKAGE', 'Insert shattered mid-cut on the roughing pass',
                'Third insert this shift. Feed and speed are per the sheet, so I suspect a hard spot in the bar. Stopped and set the bar aside.',
                Issue::STATUS_OPEN, null],
            ['MILL', 'CHIP_JAM', 'Chips packing in the pocket on the housing',
                'Through-spindle coolant is not clearing the deep pocket. Pecking it for now, which is costing about four minutes a part.',
                Issue::STATUS_OPEN, null],

            ['GRIND', 'OUT_OF_TOLERANCE', 'Bearing seat grinding 8 microns oversize',
                'Wheel has glazed. Dressed it and re-measured — parts already ground are being quarantined for inspection.',
                Issue::STATUS_ACKNOWLEDGED, 5],
            ['MILL', 'SURFACE_FINISH', 'Chatter marks on the manifold face',
                'Long reach tool ringing at the programmed speed. Dropped the RPM and increased feed; finish is acceptable now.',
                Issue::STATUS_RESOLVED, 19],
            ['TURN', 'COOLANT_QUALITY', 'Coolant smells off on lathe 2',
                'Concentration tested low and the tramp oil skimmer was full. Emptied it and topped up to 8%.',
                Issue::STATUS_RESOLVED, 27],

            ['HEAT', 'HARDNESS_FAIL', 'Pinion batch came out at 54 HRC',
                'Below the 58 HRC minimum. Furnace chart shows the soak ended early. Batch re-treated and re-tested.',
                Issue::STATUS_CLOSED, 3 * 24 + 5],
            ['MILL', 'PROGRAM_ERROR', 'Wrong work offset on the second operation',
                'G55 called instead of G56 after a fixture change. Caught on the first part; two blanks scrapped.',
                Issue::STATUS_CLOSED, 5 * 24 + 8],
            ['SAW', 'MATERIAL_CERT', 'Bar delivered without a mill certificate',
                'ValvCo work needs certification. Bar quarantined until the supplier sent the paperwork through.',
                Issue::STATUS_CLOSED, 8 * 24 + 4],
            ['TURN', 'SPINDLE_ALARM', 'Spindle overload alarm on lathe 1',
                'Tripped twice during a heavy cut. Service found a worn drive belt and replaced it.',
                Issue::STATUS_CLOSED, 11 * 24 + 6],
            ['QC', 'FIXTURE_ISSUE', 'CMM fixture not repeating between parts',
                'Locating pin had worn oval, so measurements drifted. Pin replaced and the fixture re-qualified.',
                Issue::STATUS_CLOSED, 13 * 24 + 3],
        ];

        foreach ($defs as $i => [$lineCode, $typeCode, $title, $description, $status, $hoursAgo]) {
            $workOrder = $onLine($lineCode);
            $typeId = $types[$typeCode] ?? null;

            if (! $workOrder || ! $typeId) {
                continue;
            }

            // `now()` rather than a computed offset: whichever shift the monitor
            // is showing, its window contains the present moment by definition.
            $reportedAt = $hoursAgo === null ? now() : now()->subHours((int) round($hoursAgo));
            $reporter = $reporters[$i % $reporters->count()];

            Issue::updateOrCreate(
                ['work_order_id' => $workOrder->id, 'title' => $title],
                [
                    'issue_type_id' => $typeId,
                    'description' => $description,
                    'status' => $status,
                    'reported_by_id' => $reporter->id,
                    'reported_at' => $reportedAt,
                    'acknowledged_at' => in_array($status, [Issue::STATUS_ACKNOWLEDGED, Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(15) : null,
                    'resolved_at' => in_array($status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(85) : null,
                    'closed_at' => $status === Issue::STATUS_CLOSED
                        ? $reportedAt->copy()->addMinutes(180) : null,
                ]
            );

            // A blocking type stops the order on the shop floor, and a stopped
            // order is the only way a problem reaches the planner board.
            if (IssueType::find($typeId)?->is_blocking
                && in_array($status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED], true)) {
                $workOrder->forceFill(['status' => WorkOrder::STATUS_BLOCKED])->saveQuietly();
            }
        }
    }

    // ── ISA-95 hierarchy ─────────────────────────────────────────────────────

    private function seedISA95Hierarchy(array $lines): Site
    {
        $site = Site::updateOrCreate(
            ['code' => 'PP-HQ'],
            [
                'name' => 'Precision Parts — Works I',
                'description' => 'Machining works: sawing, CNC turning and milling, heat treatment, grinding and inspection',
                'address' => 'ul. Hutnicza 8',
                'city' => 'Katowice',
                'country' => 'PL',
                'timezone' => 'Europe/Warsaw',
                'is_active' => true,
            ]
        );

        $areaDefs = [
            ['code' => 'HALL-CNC',  'name' => 'Machining Hall',    'description' => 'Sawing, turning and milling lines'],
            ['code' => 'HALL-HEAT', 'name' => 'Heat Treatment Bay', 'description' => 'Furnaces, quench tank and tempering oven'],
            ['code' => 'QC-ROOM',   'name' => 'Metrology Room',    'description' => 'Temperature-controlled room for CMM measurement'],
            ['code' => 'WH-STEEL',  'name' => 'Steel Store',       'description' => 'Bar and plate stock, racked by grade'],
        ];

        $areas = [];
        foreach ($areaDefs as $def) {
            $areas[$def['code']] = Area::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'site_id' => $site->id,
                    'description' => $def['description'],
                    'is_active' => true,
                ]
            );
        }

        if (Schema::hasColumn('lines', 'area_id')) {
            $map = [
                'SAW' => 'HALL-CNC',
                'TURN' => 'HALL-CNC',
                'MILL' => 'HALL-CNC',
                'HEAT' => 'HALL-HEAT',
                'GRIND' => 'HALL-CNC',
                'QC' => 'QC-ROOM',
            ];

            foreach ($map as $lineCode => $areaCode) {
                if (isset($lines[$lineCode], $areas[$areaCode])) {
                    $lines[$lineCode]->update(['area_id' => $areas[$areaCode]->id]);
                }
            }
        }

        return $site;
    }

    // ── Skills & personnel classes ───────────────────────────────────────────

    private function seedSkillsAndPersonnelClasses(): void
    {
        $skillDefs = [
            ['code' => 'CNC_TURNING',   'name' => 'CNC Turning',        'description' => 'Lathe setup, tool offsets, bar feed operation and in-process gauging'],
            ['code' => 'CNC_MILLING',   'name' => 'CNC Milling',        'description' => '3- and 5-axis setup, fixturing, work offsets and probing cycles'],
            ['code' => 'CAM_PROGRAM',   'name' => 'CAM Programming',    'description' => 'Toolpath generation, post-processing and proving out at the machine'],
            ['code' => 'HEAT_TREAT',    'name' => 'Heat Treatment',     'description' => 'Furnace cycles, quenching, tempering and hardness verification'],
            ['code' => 'GRINDING',      'name' => 'Precision Grinding', 'description' => 'Cylindrical and surface grinding, wheel dressing, micron-level sizing'],
            ['code' => 'METROLOGY',     'name' => 'Metrology',          'description' => 'CMM programming, GD&T interpretation and first-article reporting'],
        ];

        $skills = [];
        foreach ($skillDefs as $def) {
            $skills[$def['code']] = Skill::updateOrCreate(
                ['code' => $def['code']],
                ['name' => $def['name'], 'description' => $def['description']]
            );
        }

        $classDefs = [
            [
                'code' => 'CNC_MACHINIST',
                'name' => 'CNC Machinist',
                'description' => 'Sets and runs turning and milling centres',
                'required_skill_ids' => [$skills['CNC_TURNING']->id, $skills['CNC_MILLING']->id],
            ],
            [
                'code' => 'CNC_PROGRAMMER',
                'name' => 'CNC Programmer',
                'description' => 'Writes and proves out toolpaths for new parts',
                'required_skill_ids' => [$skills['CAM_PROGRAM']->id, $skills['CNC_MILLING']->id],
            ],
            [
                'code' => 'HEAT_OPERATOR',
                'name' => 'Heat Treatment Operator',
                'description' => 'Runs furnace cycles and verifies hardness',
                'required_skill_ids' => [$skills['HEAT_TREAT']->id],
            ],
            [
                'code' => 'GRINDER_OP',
                'name' => 'Grinder Operator',
                'description' => 'Finishes parts to final tolerance',
                'required_skill_ids' => [$skills['GRINDING']->id],
            ],
            [
                'code' => 'QC_METROLOGIST',
                'name' => 'Metrologist',
                'description' => 'Dimensional inspection and first-article reporting',
                'required_skill_ids' => [$skills['METROLOGY']->id],
            ],
        ];

        foreach ($classDefs as $def) {
            PersonnelClass::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'required_skill_ids' => $def['required_skill_ids'],
                    'is_active' => true,
                ]
            );
        }
    }

    // ── Crews ────────────────────────────────────────────────────────────────

    private function seedCrews(array $lines, array $workstations): void
    {
        $defs = [
            ['code' => 'CREW-TURN',  'name' => 'Turning Crew',    'lines' => ['TURN', 'SAW'],    'workstations' => ['LATHE-01', 'LATHE-02', 'SAW-01']],
            ['code' => 'CREW-MILL',  'name' => 'Milling Crew',    'lines' => ['MILL'],           'workstations' => ['MILL-01', 'MILL-02', 'MILL-03']],
            ['code' => 'CREW-FINISH', 'name' => 'Finishing Crew', 'lines' => ['HEAT', 'GRIND'],  'workstations' => ['FURN-01', 'GRIND-CYL-01']],
            ['code' => 'CREW-QC',    'name' => 'Inspection Crew', 'lines' => ['QC'],             'workstations' => ['CMM-01', 'PACK-01']],
        ];

        foreach ($defs as $def) {
            $crew = Crew::updateOrCreate(
                ['code' => $def['code']],
                ['name' => $def['name'], 'is_active' => true]
            );

            $crew->lines()->sync(collect($def['lines'])->map(fn ($code) => $lines[$code]->id)->all());

            foreach ($def['workstations'] as $i => $wsCode) {
                if (! isset($workstations[$wsCode])) {
                    continue;
                }

                Worker::updateOrCreate(
                    ['code' => $def['code'].'-W'.($i + 1)],
                    [
                        'name' => $def['name'].' Operator '.($i + 1),
                        'crew_id' => $crew->id,
                        'workstation_id' => $workstations[$wsCode]->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    // ── Process segments ─────────────────────────────────────────────────────

    private function seedProcessSegments(): void
    {
        $defs = [
            ['code' => 'SEG-SAW',     'name' => 'Sawing',            'description' => 'Cut bar stock to length with facing allowance',        'segment_type' => 'production', 'duration' => 6,  'operators' => 1, 'instruction' => 'Set the stop, check the first cut with a rule, then run the batch. Watch blade wander on large sections.'],
            ['code' => 'SEG-TURN',    'name' => 'CNC Turning',       'description' => 'Turning operations on CNC lathe',                      'segment_type' => 'production', 'duration' => 25, 'operators' => 1, 'instruction' => 'Load the program, set tool offsets, cut one part and gauge it before releasing the batch.'],
            ['code' => 'SEG-MILL',    'name' => 'CNC Milling',       'description' => 'Milling operations on machining centre',               'segment_type' => 'production', 'duration' => 35, 'operators' => 1, 'instruction' => 'Set the fixture, prove the work offset with a probe cycle, then run. Deburr as you unload.'],
            ['code' => 'SEG-HEAT',    'name' => 'Heat Treatment',    'description' => 'Hardening, quenching and tempering cycle',             'segment_type' => 'production', 'duration' => 60, 'operators' => 1, 'instruction' => 'Load the basket, run the cycle for the grade, record the furnace chart against the batch.'],
            ['code' => 'SEG-GRIND',   'name' => 'Precision Grinding', 'description' => 'Grinding to final tolerance',                         'segment_type' => 'production', 'duration' => 28, 'operators' => 1, 'instruction' => 'Dress the wheel, take spark-out passes and gauge every part. Stop if size starts drifting.'],
            ['code' => 'SEG-CMM',     'name' => 'CMM Inspection',    'description' => 'Dimensional inspection on the coordinate measuring machine', 'segment_type' => 'inspection', 'duration' => 18, 'operators' => 1, 'instruction' => 'Let the part reach room temperature, run the programme, file the report with the batch.'],
            ['code' => 'SEG-PACK',    'name' => 'Preserve & Pack',   'description' => 'Corrosion protection and packing for dispatch',        'segment_type' => 'production', 'duration' => 10, 'operators' => 1, 'instruction' => 'Oil or VCI-bag per the routing, box or palletise, attach the paperwork.'],
        ];

        foreach ($defs as $def) {
            ProcessSegment::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'segment_type' => $def['segment_type'],
                    'estimated_duration_minutes' => $def['duration'],
                    'required_operators' => $def['operators'],
                    'standard_instruction' => $def['instruction'],
                    'is_active' => true,
                ]
            );
        }
    }

    // ── Tools & equipment ────────────────────────────────────────────────────

    /**
     * The kit that wears out and has to be serviced.
     *
     * In a machine shop this is most of the running cost — inserts, wheels,
     * spindles and probes all degrade, and each has a symptom that tells you it
     * is due. Statuses cover all four the model knows and service dates
     * straddle today, so the page shows kit that is overdue, due soon and fine.
     *
     * @return array<string, Tool>
     */
    private function seedTools(): array
    {
        $defs = [
            ['code' => 'TL-CHUCK-01',   'name' => 'Lathe chuck & soft jaws (Ø250)',  'status' => Tool::STATUS_IN_USE,      'dueInDays' => 6,    'description' => 'Bore the soft jaws for each job. Check grip force — a slipping part marks the diameter and scraps it.'],
            ['code' => 'TL-TURRET-01',  'name' => 'Turret & tool holders',           'status' => Tool::STATUS_IN_USE,      'dueInDays' => 24,   'description' => 'Check repeatability after a crash. A turret that indexes off by hundredths ruins every second operation.'],
            ['code' => 'TL-BORING',     'name' => 'Boring bar set (anti-vibration)', 'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 38,   'description' => 'Carbide-shanked bars for deep bores. Inspect the damping insert if chatter appears at normal overhang.'],
            ['code' => 'TL-SPINDLE-M1', 'name' => 'VMC #1 spindle',                  'status' => Tool::STATUS_IN_USE,      'dueInDays' => 11,   'description' => 'Run the warm-up cycle every morning. Rising idle vibration means the bearings are on their way out.'],
            ['code' => 'TL-ATC-M1',     'name' => 'Automatic tool changer (VMC #1)', 'status' => Tool::STATUS_IN_USE,      'dueInDays' => 19,   'description' => 'Grease the carousel monthly. A sticky changer drops tools, and a dropped tool is a broken spindle taper.'],
            ['code' => 'TL-PROBE-M3',   'name' => 'Renishaw spindle probe',          'status' => Tool::STATUS_IN_USE,      'dueInDays' => 9,    'description' => 'Calibrate against the ring gauge weekly. An uncalibrated probe sets a confident, wrong work offset.'],
            ['code' => 'TL-COOLANT-01', 'name' => 'Coolant pump & filtration',       'status' => Tool::STATUS_MAINTENANCE, 'dueInDays' => -3,   'description' => 'Filter clogged and the skimmer is full. Stripped down — coolant concentration cannot be held until it is back.'],
            ['code' => 'TL-BLADE-SAW',  'name' => 'Bandsaw blade (bi-metal)',        'status' => Tool::STATUS_IN_USE,      'dueInDays' => 2,    'description' => 'Replace on a wandering cut or a squared-off tooth. A dull blade work-hardens the cut face.'],
            ['code' => 'TL-FURN-TC',    'name' => 'Furnace thermocouples',           'status' => Tool::STATUS_IN_USE,      'dueInDays' => 16,   'description' => 'Calibrate quarterly against the reference. Drift here means an entire batch is under-hardened.'],
            ['code' => 'TL-QUENCH',     'name' => 'Quench tank agitator & cooler',   'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 29,   'description' => 'A hot or still bath gives soft spots. Check circulation before every hardening load.'],
            ['code' => 'TL-WHEEL-CYL',  'name' => 'Grinding wheel & dresser',        'status' => Tool::STATUS_IN_USE,      'dueInDays' => 4,    'description' => 'Dress frequently. A glazed wheel burns the surface and grinds oversize before anyone notices.'],
            ['code' => 'TL-WHEEL-SUR',  'name' => 'Surface grinder wheel set',       'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 33,   'description' => 'Ring-test every wheel before mounting. Balance after dressing.'],
            ['code' => 'TL-CMM-PROBE',  'name' => 'CMM probe & calibration sphere',  'status' => Tool::STATUS_IN_USE,      'dueInDays' => 13,   'description' => 'Qualify the stylus at the start of each shift and after any stylus change.'],
            ['code' => 'TL-GAUGES',     'name' => 'Gauge block & ring gauge set',    'status' => Tool::STATUS_IN_USE,      'dueInDays' => 45,   'description' => 'Externally calibrated yearly. Keep them out of the machine bay — thermal soak matters.'],
            ['code' => 'TL-VICE-HYD',   'name' => 'Hydraulic vice & fixtures',       'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 52,   'description' => 'Check jaw parallelism and clean the bed. A chip under a part is the usual cause of a flatness reject.'],
            ['code' => 'TL-LATHE-OLD',  'name' => 'Manual lathe tooling (legacy)',   'status' => Tool::STATUS_RETIRED,     'dueInDays' => null, 'description' => 'Kept from the manual lathe sold last year. No longer fits anything on the floor.'],
        ];

        $tools = [];

        foreach ($defs as $def) {
            $tools[$def['code']] = Tool::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => $def['status'],
                    'next_service_at' => $def['dueInDays'] === null
                        ? null
                        : now()->addDays($def['dueInDays'])->toDateString(),
                ]
            );
        }

        return $tools;
    }

    // ── Maintenance schedules & events ───────────────────────────────────────

    /** @param array<string, Tool> $tools */
    private function seedMaintenanceSchedulesAndEvents(array $lines, array $workstations, array $tools = []): void
    {
        $schedules = [];

        $schedules['spindle'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Weekly Spindle Check — VMC #1'],
            [
                'tool_id' => $tools['TL-SPINDLE-M1']?->id,
                'description' => 'Warm-up cycle, vibration reading and draw-bar force check on the milling spindle',
                'line_id' => $lines['MILL']->id,
                'workstation_id' => $workstations['MILL-01']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '06:00',
                'next_due_at' => now()->next('Monday')->setTime(6, 0),
                'is_active' => true,
            ]
        );

        $schedules['coolant'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Bi-weekly Coolant Service'],
            [
                'tool_id' => $tools['TL-COOLANT-01']?->id,
                'description' => 'Check concentration and pH, skim tramp oil, change the filter',
                'line_id' => $lines['TURN']->id,
                'workstation_id' => $workstations['LATHE-01']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 2,
                'preferred_time' => '06:30',
                'next_due_at' => now()->addWeeks(2)->setTime(6, 30),
                'is_active' => true,
            ]
        );

        $schedules['furnace'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Quarterly Furnace Calibration'],
            [
                'tool_id' => $tools['TL-FURN-TC']?->id,
                'description' => 'Calibrate thermocouples against the reference instrument and log the certificate',
                'line_id' => $lines['HEAT']->id,
                'workstation_id' => $workstations['FURN-01']->id,
                'event_type' => 'inspection',
                'frequency' => 'quarterly',
                'interval_value' => 1,
                'preferred_time' => '07:00',
                'next_due_at' => now()->addDays(30)->setTime(7, 0),
                'is_active' => true,
            ]
        );

        $schedules['cmm'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Daily CMM Probe Qualification'],
            [
                'tool_id' => $tools['TL-CMM-PROBE']?->id,
                'description' => 'Qualify the stylus against the calibration sphere before the first measurement',
                'line_id' => $lines['QC']->id,
                'workstation_id' => $workstations['CMM-01']->id,
                'event_type' => 'inspection',
                'frequency' => 'daily',
                'interval_value' => 1,
                'preferred_time' => '06:15',
                'next_due_at' => now()->addDay()->setTime(6, 15),
                'is_active' => true,
            ]
        );

        $events = [
            [
                'title' => 'Spindle Check — VMC #1 (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['MILL']->id,
                'workstation_id' => $workstations['MILL-01']->id,
                'schedule_id' => $schedules['spindle']->id,
                'scheduled_at' => now()->subWeek()->setTime(6, 0),
                'scheduled_end_at' => now()->subWeek()->setTime(7, 0),
                'description' => 'Vibration within limits, draw-bar force checked. No action needed.',
            ],
            [
                'title' => 'Grinding Wheel Change (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['GRIND']->id,
                'workstation_id' => $workstations['GRIND-CYL-01']->id,
                'schedule_id' => null,
                'scheduled_at' => now()->subDays(2)->setTime(14, 0),
                'scheduled_end_at' => now()->subDays(2)->setTime(15, 30),
                'description' => 'Wheel replaced and balanced after glazing was reported on the shaft batch.',
            ],
            [
                'title' => 'Coolant Service — Lathe 1 (in progress)',
                'event_type' => 'corrective',
                'status' => 'in_progress',
                'line_id' => $lines['TURN']->id,
                'workstation_id' => $workstations['LATHE-01']->id,
                'schedule_id' => $schedules['coolant']->id,
                'scheduled_at' => now()->subHours(2),
                'scheduled_end_at' => now()->addHours(2),
                'description' => 'Filtration stripped down after the contamination report. Pump off until it is back together.',
            ],
            [
                'title' => 'CMM Probe Qualification (due today)',
                'event_type' => 'inspection',
                'status' => 'pending',
                'line_id' => $lines['QC']->id,
                'workstation_id' => $workstations['CMM-01']->id,
                'schedule_id' => $schedules['cmm']->id,
                'scheduled_at' => now()->setTime(6, 15),
                'scheduled_end_at' => now()->setTime(6, 45),
                'description' => 'Daily stylus qualification before the first measurement.',
            ],
            [
                'title' => 'Furnace Calibration (scheduled)',
                'event_type' => 'inspection',
                'status' => 'pending',
                'line_id' => $lines['HEAT']->id,
                'workstation_id' => $workstations['FURN-01']->id,
                'schedule_id' => $schedules['furnace']->id,
                'scheduled_at' => now()->addDays(30)->setTime(7, 0),
                'scheduled_end_at' => now()->addDays(30)->setTime(11, 0),
                'description' => 'Quarterly thermocouple calibration with the external instrument.',
            ],
        ];

        foreach ($events as $event) {
            MaintenanceEvent::updateOrCreate(['title' => $event['title']], $event);
        }
    }

    // ── Inspection plans ─────────────────────────────────────────────────────

    private function seedInspectionPlans(array $materials): void
    {
        $plans = [
            [
                'name' => 'Bar Stock Incoming Inspection',
                'material' => 'MAT-BAR-42CRMO4',
                'description' => 'Goods-in check for alloy bar: certificate, dimensions and surface condition',
                'criteria' => ['mill_certificate', 'diameter_tolerance', 'straightness', 'surface_defects'],
            ],
            [
                'name' => 'Plate Incoming Inspection',
                'material' => 'MAT-PLATE-S355',
                'description' => 'Goods-in check for structural plate: thickness, flatness and certification',
                'criteria' => ['mill_certificate', 'thickness', 'flatness'],
            ],
            [
                'name' => 'Casting Incoming Inspection',
                'material' => 'MAT-CAST-HOUS',
                'description' => 'Goods-in check for housing castings: porosity and machining allowance',
                'criteria' => ['visual_porosity', 'machining_allowance', 'hardness_spot_check'],
            ],
        ];

        foreach ($plans as $def) {
            $material = $materials[$def['material']] ?? null;
            if (! $material) {
                continue;
            }

            InspectionPlan::updateOrCreate(
                ['name' => $def['name']],
                [
                    'description' => $def['description'],
                    'material_id' => $material->id,
                    'criteria' => $def['criteria'],
                    'is_active' => true,
                ]
            );
        }
    }

    // ── OEE records ──────────────────────────────────────────────────────────

    private function seedOeeRecords(array $lines): void
    {
        foreach ($lines as $code => $line) {
            if ($code === 'QC') {
                continue; // inspection is not a production line
            }

            for ($day = -14; $day <= 0; $day++) {
                $date = now()->addDays($day)->format('Y-m-d');

                $planned = mt_rand(420, 480);
                $downtime = mt_rand(20, 90);   // setups are long in machining
                $operating = $planned - $downtime;
                // Tens of parts a shift, not hundreds — this is metal removal.
                $totalProduced = mt_rand(15, 90);
                $scrap = mt_rand(0, (int) max(1, $totalProduced * 0.05));
                $good = $totalProduced - $scrap;

                $availability = round($operating / max($planned, 1) * 100, 1);
                $performance = round(mt_rand(65, 95), 1);
                $quality = $totalProduced > 0 ? round($good / $totalProduced * 100, 1) : 100;
                $oee = round($availability * $performance * $quality / 10000, 1);

                OeeRecord::updateOrCreate(
                    ['line_id' => $line->id, 'record_date' => $date],
                    [
                        'planned_minutes' => $planned,
                        'operating_minutes' => $operating,
                        'downtime_minutes' => $downtime,
                        'total_produced' => $totalProduced,
                        'good_produced' => $good,
                        'scrap_qty' => $scrap,
                        'availability_pct' => $availability,
                        'performance_pct' => $performance,
                        'quality_pct' => $quality,
                        'oee_pct' => $oee,
                    ]
                );
            }
        }
    }
}
