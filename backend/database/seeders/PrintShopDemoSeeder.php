<?php

namespace Database\Seeders;

use App\Enums\Tier;
use App\Models\Area;
use App\Models\BomItem;
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
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPlacement;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Demo data for a print-on-demand / garment decoration company.
 * Seeds: lines, workstations, product types, process templates
 * with steps, operators, supervisors, and example work orders.
 */
class PrintShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIssueTypes();
        $lines = $this->seedLines();
        $workstations = $this->seedWorkstations($lines);
        $productTypes = $this->seedProductTypes();
        $templates = $this->seedProcessTemplates($productTypes, $workstations, $lines);
        $users = $this->seedUsers($lines);
        $customers = $this->seedCustomers();
        $this->seedWorkOrders($productTypes, $lines);
        $this->assignCustomers($customers);
        $this->seedIssues($lines, $users);
        $this->seedMultiLinePlacements($lines);
        $this->seedShifts($lines);
        $materials = $this->seedMaterials($templates);
        $this->seedBom($templates, $materials);
        $this->seedMaterialLots($materials);
        $site = $this->seedISA95Hierarchy($lines);
        $this->seedSkillsAndPersonnelClasses();
        $this->seedCrews($lines, $workstations);
        $this->seedProcessSegments();
        $this->seedMaintenanceSchedulesAndEvents($lines, $workstations);
        $this->seedInspectionPlans($materials);
        $this->seedOeeRecords($lines);
    }

    // ── Issue types ──────────────────────────────────────────────────────────

    private function seedIssueTypes(): void
    {
        $types = [
            ['code' => 'PRINT_COLOR_MISMATCH', 'name' => 'Print Color Mismatch',               'severity' => 'HIGH',     'is_blocking' => false],
            ['code' => 'PRINT_SMEAR',          'name' => 'Print Smear / Spill',                 'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SUBSTRATE_DAMAGE',     'name' => 'Substrate / Garment Damage',          'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PRINT_HEAD_FAILURE',   'name' => 'DTG Print Head Failure',              'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'THREAD_BREAK',         'name' => 'Embroidery Thread Break',             'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'SCREEN_CLOGGED',       'name' => 'Screen / Stencil Clogged',            'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'INK_SHORTAGE',         'name' => 'Ink / Toner Shortage',                'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'ARTWORK_ERROR',        'name' => 'Artwork File Error',                  'severity' => 'MEDIUM',   'is_blocking' => true],
            ['code' => 'PRESS_TEMP_ERROR',     'name' => 'Heat Press Temperature Error',        'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SIZE_MISMATCH',        'name' => 'Wrong Print Size / Position',         'severity' => 'MEDIUM',   'is_blocking' => false],
        ];

        foreach ($types as $type) {
            DB::table('issue_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, ['is_active' => true])
            );
        }
    }

    // ── Lines ────────────────────────────────────────────────────────────────

    private function seedLines(): array
    {
        $defs = [
            ['code' => 'DTG',      'name' => 'DTG Printing',       'description' => 'Direct-to-Garment digital printing line'],
            ['code' => 'SITO',     'name' => 'Screen Printing',     'description' => 'Screen printing — minimum run of 12 pcs'],
            ['code' => 'HAFT',     'name' => 'Embroidery',          'description' => 'Computerised machine embroidery — shirts, caps, hoodies'],
            ['code' => 'TRANSFER', 'name' => 'Heat Transfer',       'description' => 'Heat transfer printing — flex, foil, sublimation'],
            ['code' => 'PACKING',  'name' => 'Packing & Shipping',  'description' => 'Finished goods packing, labelling, and dispatch'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $line = Line::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $line;
        }

        return $result;
    }

    // ── Workstations ─────────────────────────────────────────────────────────

    private function seedWorkstations(array $lines): array
    {
        $defs = [
            // DTG line
            ['line' => 'DTG',      'code' => 'DTG-PRE-1',  'name' => 'Pretreat Station #1',            'workstation_type' => 'pretreat'],
            ['line' => 'DTG',      'code' => 'DTG-1',       'name' => 'DTG Printer #1 (Epson F2100)',   'workstation_type' => 'printer'],
            ['line' => 'DTG',      'code' => 'DTG-2',       'name' => 'DTG Printer #2 (Epson F2100)',   'workstation_type' => 'printer'],
            ['line' => 'DTG',      'code' => 'DTG-CURE-1',  'name' => 'Conveyor Curing Oven #1',        'workstation_type' => 'curing'],
            // Screen Printing line
            ['line' => 'SITO',     'code' => 'SITO-EXP-1', 'name' => 'Screen Exposure Unit #1',        'workstation_type' => 'exposure'],
            ['line' => 'SITO',     'code' => 'SITO-1',      'name' => 'Screen Printing Table #1',       'workstation_type' => 'press'],
            ['line' => 'SITO',     'code' => 'SITO-2',      'name' => 'Screen Printing Table #2',       'workstation_type' => 'press'],
            ['line' => 'SITO',     'code' => 'SITO-DRY-1',  'name' => 'Conveyor Dryer',                 'workstation_type' => 'dryer'],
            // Embroidery line
            ['line' => 'HAFT',     'code' => 'HAFT-1',      'name' => 'Embroidery Machine #1 (Barudan)', 'workstation_type' => 'embroidery'],
            ['line' => 'HAFT',     'code' => 'HAFT-2',      'name' => 'Embroidery Machine #2 (Barudan)', 'workstation_type' => 'embroidery'],
            ['line' => 'HAFT',     'code' => 'HAFT-3',      'name' => 'Embroidery Machine #3 (Tajima)', 'workstation_type' => 'embroidery'],
            // Heat Transfer line
            ['line' => 'TRANSFER', 'code' => 'TRANS-1',     'name' => 'Heat Press #1',                  'workstation_type' => 'heat_press'],
            ['line' => 'TRANSFER', 'code' => 'TRANS-2',     'name' => 'Heat Press #2',                  'workstation_type' => 'heat_press'],
            ['line' => 'TRANSFER', 'code' => 'TRANS-SUB-1', 'name' => 'Sublimation Oven #1',            'workstation_type' => 'sublimation'],
            // Packing
            ['line' => 'PACKING',  'code' => 'PAK-1',       'name' => 'Packing Station #1',             'workstation_type' => 'packing'],
            ['line' => 'PACKING',  'code' => 'PAK-2',       'name' => 'Packing Station #2',             'workstation_type' => 'packing'],
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

    // ── Product types ─────────────────────────────────────────────────────────

    private function seedProductTypes(): array
    {
        $defs = [
            ['code' => 'TSHIRT',     'name' => 'T-Shirt',              'description' => 'Short-sleeve t-shirt, 100% cotton',                   'unit_of_measure' => 'pcs'],
            ['code' => 'HOODIE',     'name' => 'Hoodie',               'description' => 'Pullover hoodie with kangaroo pocket',                 'unit_of_measure' => 'pcs'],
            ['code' => 'SWEATSHIRT', 'name' => 'Crewneck Sweatshirt',  'description' => 'Classic crewneck sweatshirt, cotton/polyester blend',  'unit_of_measure' => 'pcs'],
            ['code' => 'POLO',       'name' => 'Polo Shirt',           'description' => 'Polo shirt with collar, piqué fabric',                 'unit_of_measure' => 'pcs'],
            ['code' => 'CAP',        'name' => 'Baseball Cap',         'description' => 'Structured baseball / snapback cap',                   'unit_of_measure' => 'pcs'],
            ['code' => 'BEANIE',     'name' => 'Beanie Hat',           'description' => 'Knit beanie, embroidery or patch decoration',          'unit_of_measure' => 'pcs'],
            ['code' => 'TOTE',       'name' => 'Cotton Tote Bag',      'description' => 'Natural cotton tote bag',                              'unit_of_measure' => 'pcs'],
            ['code' => 'JACKET',     'name' => 'Softshell Jacket',     'description' => 'Softshell or windbreaker jacket with print',           'unit_of_measure' => 'pcs'],
            ['code' => 'MUG',        'name' => 'Sublimation Mug',      'description' => 'Ceramic mug for sublimation printing (330 ml)',        'unit_of_measure' => 'pcs'],
            ['code' => 'PILLOW',     'name' => 'Printed Pillow Cover', 'description' => 'Pillow cover with sublimation print',                  'unit_of_measure' => 'pcs'],
            // Sub-assemblies. Each is decorated in its own right before it
            // reaches a garment, so each needs a product type to hang a routing
            // on — that routing is what lets a BOM line for it explode further.
            ['code' => 'SA_PATCH',    'name' => 'Embroidered Patch',    'description' => 'Felt patch embroidered and cut, ready to apply to a cap or a decoration kit', 'unit_of_measure' => 'pcs'],
            ['code' => 'SA_DECOKIT',  'name' => 'Decoration Kit',       'description' => 'Patch plus contrast thread, kitted per hoodie so the embroidery station works from one pick', 'unit_of_measure' => 'pcs'],
            ['code' => 'SA_TRANSFER', 'name' => 'Printed Transfer Sheet', 'description' => 'Sublimation sheet printed and trimmed, waiting to be pressed onto a tote or a pillow cover', 'unit_of_measure' => 'pcs'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    // ── Process templates ─────────────────────────────────────────────────────

    /** @return array<string, ProcessTemplate> */
    private function seedProcessTemplates(array $pt, array $ws, array $lines): array
    {
        $t = [];

        $t['TSHIRT'] = $this->createTemplate($pt['TSHIRT'], 'T-Shirt — DTG Printing', [
            [1, 'Artwork verification',      'Check resolution (min 150 dpi), colour profile, no elements too close to edges.', 10, null],
            [2, 'Pre-wash and press',        'Pre-wash garment if label says "wash before print". Press flat with heat press.', 5, $ws['DTG-PRE-1'] ?? null],
            [3, 'Pretreating',               'Apply pretreat solution evenly over print area. Shake bottle well before use.', 10, $ws['DTG-PRE-1'] ?? null],
            [4, 'DTG printing',              'Place shirt on platen, centre artwork. Run print using correct colour profile.', 15, $ws['DTG-1'] ?? null],
            [5, 'Curing',                    'Pass through conveyor oven: 165 °C, approx. 90 sec. Check moisture level first.', 8, $ws['DTG-CURE-1'] ?? null],
            [6, 'Quality control',           'Check colour coverage, edge sharpness, no smearing. Reject any defects.', 5, null],
            [7, 'Packing',                   'Fold neatly, place in poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);

        $t['HOODIE'] = $this->createTemplate($pt['HOODIE'], 'Hoodie — Machine Embroidery', [
            [1, 'Embroidery file check',     'Open DST/PES file, verify thread colours, start/stop points and density.', 15, null],
            [2, 'Machine & thread setup',    'Thread machine per colour card. Mount correct stabiliser (tearaway / cutaway).', 10, $ws['HAFT-1'] ?? null],
            [3, 'Hooping',                   'Hoop the hoodie taut and flat — no wrinkles or puckers.', 8, $ws['HAFT-1'] ?? null],
            [4, 'Embroidery run',            'Start machine. Monitor first 10 stitches. Check thread tension every 5 min.', 25, $ws['HAFT-1'] ?? null],
            [5, 'Trim and finish',           'Remove excess stabiliser, trim jump stitches, steam if required.', 10, null],
            [6, 'Quality control',           'Check stitch density, no jumps, colour alignment against artwork.', 5, null],
            [7, 'Packing',                   'Fold hoodie, poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);

        $t['POLO'] = $this->createTemplate($pt['POLO'], 'Polo Shirt — Screen Printing', [
            [1, 'Screen preparation',        'Expose screen from film positive. Check open areas after washing out.', 20, $ws['SITO-EXP-1'] ?? null],
            [2, 'Registration setup',        'Mount screen on press. Set registration using rulers and tape.', 10, $ws['SITO-1'] ?? null],
            [3, 'Test print',                'Pull one test print. Check coverage, registration and colour. Sign off before production.', 10, $ws['SITO-1'] ?? null],
            [4, 'Production run',            'Print full batch. Top up ink every ~30 pcs. Spot-check every 10th piece.', 30, $ws['SITO-1'] ?? null],
            [5, 'Curing',                    'Pass through conveyor dryer: 160 °C / 60 sec. Tape-peel adhesion test.', 10, $ws['SITO-DRY-1'] ?? null],
            [6, 'Quality control',           'Sample-check every 20 pcs: coverage, sharpness, no ink haze.', 5, null],
            [7, 'Packing',                   'Fold polo shirts, pack in dozens (12 pcs). Apply batch labels.', 8, $ws['PAK-2'] ?? null],
        ]);

        $t['CAP'] = $this->createTemplate($pt['CAP'], 'Baseball Cap — Embroidery', [
            [1, 'Embroidery file check',     'Verify file is adapted for cap embroidery (flat area, max 80 mm width).', 10, null],
            [2, 'Cap frame setup',           'Mount cap frame on machine. Stretch cap brim flat in frame.', 8, $ws['HAFT-2'] ?? null],
            [3, 'Embroidery run',            'Start machine. Monitor carefully — curved surface needs stable hooping.', 20, $ws['HAFT-2'] ?? null],
            [4, 'Trim and finish',           'Remove stabiliser, trim threads, inspect back of brim.', 5, null],
            [5, 'Quality control',           'Check embroidery centring on brim, colour accuracy.', 5, null],
            [6, 'Packing',                   'Place cap in poly bag, attach order label.', 3, $ws['PAK-1'] ?? null],
        ]);

        $t['TOTE'] = $this->createTemplate($pt['TOTE'], 'Cotton Tote Bag — Heat Transfer', [
            [1, 'Print transfer film',       'Print transfer on plotter or transfer printer. Allow to dry fully.', 10, null],
            [2, 'Heat press setup',          'Set temperature: 160 °C, time 15 sec, medium pressure. Pre-heat 5 min.', 5, $ws['TRANS-1'] ?? null],
            [3, 'Position transfer',         'Lay bag flat on press platen, centre transfer. Use ruler or template.', 5, $ws['TRANS-1'] ?? null],
            [4, 'Press transfer',            'Apply cover sheet, close press. Hold 15 sec. Peel film cold or hot per manufacturer instructions.', 5, $ws['TRANS-1'] ?? null],
            [5, 'Quality control',           'Check transfer edges, no air bubbles, full coverage.', 3, null],
            [6, 'Packing',                   'Fold bag, place in poly bag with print facing out.', 3, $ws['PAK-2'] ?? null],
        ]);

        $t['MUG'] = $this->createTemplate($pt['MUG'], 'Sublimation Mug', [
            [1, 'Print sublimation transfer', 'Print artwork mirrored on sublimation paper. Trim with 5 mm margin.', 10, null],
            [2, 'Wrap mug',                  'Wrap mug with transfer paper, secure with heat-resistant tape. No wrinkles.', 5, $ws['TRANS-SUB-1'] ?? null],
            [3, 'Sublimation in oven',       'Place in sublimation oven: 200 °C / 4 min. Do not open early.', 5, $ws['TRANS-SUB-1'] ?? null],
            [4, 'Cool and unwrap',           'Remove mug, allow to cool 2 min. Peel paper.', 3, null],
            [5, 'Quality control',           'Check colour saturation, no white spots (insufficient pressure), sharpness.', 5, null],
            [6, 'Packing',                   'Place mug in box with protective padding to prevent breakage.', 3, $ws['PAK-1'] ?? null],
        ]);

        $t['SWEATSHIRT'] = $this->createTemplate($pt['SWEATSHIRT'], 'Crewneck Sweatshirt — DTG Printing', [
            [1, 'Artwork verification',      'Check resolution, colour profile, print dimensions (max A3).', 10, null],
            [2, 'Pretreating',               'Apply pretreat to sweatshirt. Heavier fabric — increase dose by 15%.', 12, $ws['DTG-PRE-1'] ?? null],
            [3, 'DTG printing',              'Load sweatshirt on platen, centre artwork. Use heavy-fabric print profile.', 18, $ws['DTG-2'] ?? null],
            [4, 'Curing',                    'Pass through oven: 165 °C, 100 sec (longer than t-shirt — thicker fabric).', 10, $ws['DTG-CURE-1'] ?? null],
            [5, 'Quality control',           'Check coverage, no smearing, no gaps in print.', 5, null],
            [6, 'Packing',                   'Fold, place in poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);

        // ── Sub-assembly routings ────────────────────────────────────────────
        // Short jobs of their own. Without a routing a manufactured material is
        // a dead end: the explosion sees the line and cannot descend past it.

        $t['SA_PATCH'] = $this->createTemplate($pt['SA_PATCH'], 'Embroidered Patch — production v1', [
            [1, 'Felt cutting',              'Cut patch blanks from the felt roll to the nominal diameter.', 4, $ws['HAFT-1'] ?? null],
            [2, 'Patch embroidery',          'Run the patch design; check the border stitch closes cleanly all the way round.', 12, $ws['HAFT-1'] ?? null],
            [3, 'Trim and heat-seal',        'Trim the excess backing and heat-seal the edge so it cannot fray.', 5, null],
        ]);

        $t['SA_DECOKIT'] = $this->createTemplate($pt['SA_DECOKIT'], 'Decoration Kit — kitting v1', [
            [1, 'Patch check',               'Check the patch against the colour card and reject any with a broken border.', 3, null],
            [2, 'Kitting',                   'Bag one patch with the contrast thread for the garment it belongs to.', 4, $ws['PAK-1'] ?? null],
        ]);

        $t['SA_TRANSFER'] = $this->createTemplate($pt['SA_TRANSFER'], 'Printed Transfer Sheet — production v1', [
            [1, 'Sheet printing',            'Print the artwork onto sublimation paper; check the ink is not banding.', 8, $ws['TRN-PRINT-1'] ?? $ws['DTG-1'] ?? null],
            [2, 'Trim to size',              'Trim the sheet to the press window and stack print-side up.', 4, null],
        ]);

        return $t;
    }

    private function createTemplate(ProductType $productType, string $name, array $steps): ProcessTemplate
    {
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'version' => 1],
            ['name' => $name, 'is_active' => true]
        );

        foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $workstation]) {
            // Match live rows only. template_steps is soft-deletable and
            // updateOrInsert() runs without the model's scope, so leaving
            // deleted_at out would let a re-run resurrect (and overwrite) a step
            // the user had deleted instead of inserting a fresh one.
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

    // ── Users ─────────────────────────────────────────────────────────────────

    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole = Role::where('name', 'Operator')->first();

        $users = [];

        $supervisor = User::updateOrCreate(
            ['username' => 'peter.wilson'],
            [
                'name' => 'Peter Wilson',
                'email' => 'peter.wilson@printshop.local',
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
            ['username' => 'anna.smith',    'name' => 'Anna Smith',    'email' => 'anna.smith@printshop.local',    'lines' => ['DTG', 'TRANSFER']],
            ['username' => 'mark.johnson',  'name' => 'Mark Johnson',  'email' => 'mark.johnson@printshop.local',  'lines' => ['SITO']],
            ['username' => 'julia.white',   'name' => 'Julia White',   'email' => 'julia.white@printshop.local',   'lines' => ['HAFT']],
            ['username' => 'tom.green',     'name' => 'Tom Green',     'email' => 'tom.green@printshop.local',     'lines' => ['PACKING', 'DTG']],
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
            $lineIds = array_map(fn ($code) => $lines[$code]->id, $def['lines']);
            $user->lines()->syncWithoutDetaching($lineIds);
            $users[] = $user;
        }

        return $users;
    }

    // ── Example work orders ───────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        // Fixed seed: the generated half of this board picks lines, products,
        // quantities and statuses at random, and without pinning the sequence a
        // re-run reshuffles them. That made the demo unreproducible between
        // seeds — and, because an order's line decides whether it hands off to
        // another one, it also changed how many multi-line segments came out.
        mt_srand(20260101);

        $orders = [
            [
                'order_no' => 'WO-2026-001',
                'line_id' => $lines['DTG']->id,
                'product_type_id' => $pt['TSHIRT']->id,
                'planned_qty' => 50,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 3,
                'due_date' => now()->addDays(2),
                'planned_start_at' => now()->setTime(8, 0),
                'planned_end_at' => now()->addDays(1)->setTime(14, 0),
                'description' => 'Corporate t-shirts — XYZ Ltd. logo, white base, DTG print, sizes M/L/XL',
            ],
            [
                'order_no' => 'WO-2026-002',
                'line_id' => $lines['HAFT']->id,
                'product_type_id' => $pt['CAP']->id,
                'planned_qty' => 30,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(5),
                'planned_start_at' => now()->addDays(1)->setTime(6, 0),
                'planned_end_at' => now()->addDays(2)->setTime(12, 0),
                'description' => 'Sports team caps — 3D embroidery logo, navy blue',
            ],
            [
                'order_no' => 'WO-2026-003',
                'line_id' => $lines['SITO']->id,
                'product_type_id' => $pt['POLO']->id,
                'planned_qty' => 100,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 4,
                'due_date' => now()->addDay(),
                'planned_start_at' => now()->setTime(6, 0),
                'planned_end_at' => now()->setTime(18, 0),
                'description' => 'Polo shirts screen print 2 colours — workwear for construction company',
            ],
            [
                'order_no' => 'WO-2026-004',
                'line_id' => $lines['TRANSFER']->id,
                'product_type_id' => $pt['TOTE']->id,
                'planned_qty' => 200,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 1,
                'due_date' => now()->addDays(7),
                'planned_start_at' => now()->addDays(2)->setTime(8, 0),
                'planned_end_at' => now()->addDays(4)->setTime(16, 0),
                'description' => 'Conference tote bags — flex transfer, single colour print',
            ],
            [
                'order_no' => 'WO-2026-005',
                'line_id' => $lines['DTG']->id,
                'product_type_id' => $pt['HOODIE']->id,
                'planned_qty' => 25,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 2,
                'due_date' => now()->subDay(),
                'planned_start_at' => now()->subDays(2)->setTime(8, 0),
                'planned_end_at' => now()->subDay()->setTime(14, 0),
                'description' => 'Artist hoodies — limited edition, full-colour DTG print',
                'completed_at' => now()->subHours(3),
            ],
            [
                'order_no' => 'WO-2026-006',
                'line_id' => $lines['TRANSFER']->id,
                'product_type_id' => $pt['MUG']->id,
                'planned_qty' => 48,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 3,
                'due_date' => now()->addDays(3),
                'planned_start_at' => now()->addDay()->setTime(10, 0),
                'planned_end_at' => now()->addDays(2)->setTime(16, 0),
                'description' => 'Sublimation mugs — personalised customer photos, gift order',
            ],
            [
                'order_no' => 'WO-2026-007',
                'line_id' => $lines['HAFT']->id,
                'product_type_id' => $pt['SWEATSHIRT']->id,
                'planned_qty' => 15,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(4),
                'planned_start_at' => now()->addDays(3)->setTime(6, 0),
                'planned_end_at' => now()->addDays(3)->setTime(18, 0),
                'description' => 'University crewneck sweatshirts — embroidered crest, black, sizes S–XXL',
            ],

            // Unassigned orders — created but not yet allocated to a production
            // line (line_id null). They sit in the backlog awaiting scheduling,
            // exercising the "no line assigned" path across the UI.
            [
                'order_no' => 'WO-2026-008',
                'line_id' => null,
                'product_type_id' => $pt['TSHIRT']->id,
                'planned_qty' => 75,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(6),
                'description' => 'Festival merch t-shirts — line not assigned yet, awaiting scheduling',
            ],
            [
                'order_no' => 'WO-2026-009',
                'line_id' => null,
                'product_type_id' => $pt['HOODIE']->id,
                'planned_qty' => 40,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 1,
                'due_date' => now()->addDays(10),
                'description' => 'Bulk hoodie order — unassigned, pending capacity planning',
            ],
            [
                'order_no' => 'WO-2026-010',
                'line_id' => null,
                'product_type_id' => $pt['TOTE']->id,
                'planned_qty' => 150,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 0,
                'due_date' => now()->addDays(14),
                'description' => 'Promo tote bags — no line assigned, backlog',
            ],
        ];

        foreach ($orders as $orderData) {
            WorkOrder::updateOrCreate(
                ['order_no' => $orderData['order_no']],
                array_merge($orderData, ['produced_qty' => 0])
            );
        }

        // Fill the planner densely for the CURRENT week — a busy shop should read
        // as an almost-full weekly board — then taper off over the neighbouring
        // weeks so paging forward/back still shows work (with a short DONE tail
        // behind "today"). Everything is anchored to now()->startOfWeek(), so the
        // visible week is always the packed one whenever the sample data loads.
        $allLines = array_values($lines);
        $allPt = array_values($pt);
        $descriptions = [
            'Rush order — client event next week',
            'Reprint batch — colour correction applied',
            'Seasonal collection — autumn/winter lineup',
            'Trade show giveaways — branded merch',
            'Employee uniforms — new logo rollout',
            'Charity event — custom artwork',
            'Wholesale order — repeat customer',
            'Sample run — new fabric test',
            'E-commerce fulfilment — multi-SKU',
            'Prototype — client approval pending',
        ];

        // Representative start hour per shift column (Morning 06–14, Afternoon
        // 14–22, Night 22–06). The planner derives a block's shift column from
        // planned_start_at's time, so a start inside a shift lands the block in
        // that column. Night runs on DTG/SITO, Mon–Thu only (see seedShifts).
        $shifts = [['h' => 8, 'night' => false], ['h' => 16, 'night' => false], ['h' => 23, 'night' => true]];
        $nightLines = ['DTG', 'SITO'];

        // week offset => fill probability. The current week (0) is packed; the
        // neighbours taper so the board stays believable as you page around.
        $weekFill = [-1 => 0.35, 0 => 0.85, 1 => 0.40, 2 => 0.25];

        $weekStart = now()->startOfWeek();
        $n = 100; // WO-2026-0100+, clear of the hand-authored WO-2026-001..010

        foreach ($weekFill as $weekOffset => $fill) {
            foreach ($allLines as $line) {
                for ($d = 0; $d < 7; $d++) {
                    foreach ($shifts as $shift) {
                        // Night is DTG/SITO, Mon–Thu only — keep the board honest.
                        if ($shift['night'] && (! in_array($line->code, $nightLines, true) || $d > 3)) {
                            continue;
                        }
                        if (mt_rand(1, 100) > (int) round($fill * 100)) {
                            continue;
                        }

                        $n++;
                        $start = $weekStart->copy()->addWeeks($weekOffset)->addDays($d)->setTime($shift['h'], 0);
                        $end = $start->copy()->addHours(mt_rand(2, 7));
                        $qty = mt_rand(10, 200);

                        // Last week's work is DONE (drops off the active board);
                        // the current week stays ACTIVE end-to-end so every day
                        // renders blocks (a packed board), even the days already
                        // behind "today" (long / carried-over jobs); future weeks
                        // are still-to-start. DONE orders aren't drawn as blocks,
                        // so keeping the visible week active is what fills the grid.
                        $status = match (true) {
                            $weekOffset < 0 => WorkOrder::STATUS_DONE,
                            $weekOffset > 0 => mt_rand(0, 1) ? WorkOrder::STATUS_PENDING : WorkOrder::STATUS_ACCEPTED,
                            default => [
                                WorkOrder::STATUS_IN_PROGRESS,
                                WorkOrder::STATUS_IN_PROGRESS,
                                WorkOrder::STATUS_ACCEPTED,
                                WorkOrder::STATUS_PENDING,
                            ][mt_rand(0, 3)],
                        };

                        WorkOrder::updateOrCreate(
                            ['order_no' => sprintf('WO-2026-%04d', $n)],
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

    // ── Shifts ───────────────────────────────────────────────────────────────

    private function seedShifts(array $lines): void
    {
        // ISO weekday integers (1=Mon .. 7=Sun) — the canonical days_of_week format.
        $weekdays = [1, 2, 3, 4, 5];
        $monToThu = [1, 2, 3, 4];

        $defs = [
            [
                'name' => 'Morning Shift', 'code' => 'SM',
                'start_time' => '06:00', 'end_time' => '14:00',
                'days_of_week' => $weekdays, 'line_codes' => ['DTG', 'SITO', 'HAFT', 'TRANSFER', 'PACKING'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Afternoon Shift', 'code' => 'SA',
                'start_time' => '14:00', 'end_time' => '22:00',
                'days_of_week' => $weekdays, 'line_codes' => ['DTG', 'SITO', 'HAFT', 'TRANSFER', 'PACKING'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Night Shift', 'code' => 'SN',
                'start_time' => '22:00', 'end_time' => '06:00',
                'days_of_week' => $monToThu, 'line_codes' => ['DTG', 'SITO'],
                'sort_order' => 3,
            ],
        ];

        foreach ($defs as $def) {
            foreach ($def['line_codes'] as $lineCode) {
                $shortLine = substr($lineCode, 0, 4);
                $uniqueCode = $def['code'].'-'.$shortLine;
                Shift::updateOrCreate(
                    ['code' => $uniqueCode],
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

    // ── Crews ────────────────────────────────────────────────────────────────

    private function seedCrews(array $lines, array $workstations): void
    {
        $defs = [
            ['code' => 'CREW-A', 'name' => 'Day Shift A', 'lines' => ['DTG', 'SITO'], 'workstations' => ['DTG-1', 'DTG-2', 'SITO-1']],
            ['code' => 'CREW-B', 'name' => 'Day Shift B', 'lines' => ['HAFT', 'TRANSFER'], 'workstations' => ['HAFT-1', 'HAFT-2', 'TRANS-1']],
            ['code' => 'CREW-PACK', 'name' => 'Packing Crew', 'lines' => ['PACKING'], 'workstations' => ['PAK-1', 'PAK-2']],
        ];

        foreach ($defs as $def) {
            $crew = \App\Models\Crew::updateOrCreate(
                ['code' => $def['code']],
                ['name' => $def['name'], 'is_active' => true]
            );

            // Explicit crew → line assignment (drives the capacity crew axis).
            $crew->lines()->sync(collect($def['lines'])->map(fn ($code) => $lines[$code]->id)->all());

            // A few operators per crew, stationed on the crew's lines.
            foreach ($def['workstations'] as $i => $wsCode) {
                \App\Models\Worker::updateOrCreate(
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

    // ── Materials & Material Types ───────────────────────────────────────────

    /** @param array<string, ProcessTemplate> $templates */
    private function seedMaterials(array $templates): array
    {
        $types = [
            ['code' => 'INK',            'name' => 'Ink'],
            ['code' => 'GARMENT',        'name' => 'Garment'],
            ['code' => 'THREAD',         'name' => 'Thread'],
            ['code' => 'TRANSFER_MEDIA', 'name' => 'Transfer Media'],
            ['code' => 'PACKAGING',      'name' => 'Packaging'],
        ];

        $typeModels = [];
        foreach ($types as $type) {
            $typeModels[$type['code']] = MaterialType::updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name']]
            );
        }

        $materialDefs = [
            ['code' => 'MAT-INK-DTG-W',    'name' => 'White DTG Ink',                    'description' => 'White ink for DTG printers, 1 litre bottle',         'type' => 'INK',            'unit' => 'litre',  'tracking' => 'lot',   'stock' => 25,   'min' => 5,   'supplier' => 'Epson'],
            ['code' => 'MAT-INK-DTG-CMYK', 'name' => 'CMYK DTG Ink Set',                 'description' => 'Full CMYK ink set for DTG printers',                 'type' => 'INK',            'unit' => 'set',    'tracking' => 'lot',   'stock' => 12,   'min' => 3,   'supplier' => 'Epson'],
            ['code' => 'MAT-INK-PLAST-BK', 'name' => 'Plastisol Black Ink',              'description' => 'Plastisol screen printing ink, black',               'type' => 'INK',            'unit' => 'kg',     'tracking' => 'lot',   'stock' => 40,   'min' => 10,  'supplier' => 'Union Ink'],
            ['code' => 'MAT-GAR-TSH-W',    'name' => 'Cotton T-Shirt Blank White',       'description' => '100% cotton t-shirt blank, white, assorted sizes',   'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 500,  'min' => 100, 'supplier' => 'Fruit of the Loom'],
            ['code' => 'MAT-GAR-HOOD-BK',  'name' => 'Cotton Hoodie Blank Black',        'description' => 'Cotton/poly blend hoodie blank, black',              'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 200,  'min' => 50,  'supplier' => 'Gildan'],
            ['code' => 'MAT-THR-POLY',     'name' => 'Polyester Embroidery Thread',       'description' => 'High-sheen polyester thread for machine embroidery', 'type' => 'THREAD',         'unit' => 'spool',  'tracking' => 'none',  'stock' => 80,   'min' => 20,  'supplier' => 'Madeira'],
            ['code' => 'MAT-TRN-SUB-A3',   'name' => 'Sublimation Transfer Paper A3',    'description' => 'A3 sublimation transfer paper, 100gsm',              'type' => 'TRANSFER_MEDIA', 'unit' => 'sheet',  'tracking' => 'batch', 'stock' => 1000, 'min' => 200, 'supplier' => 'Texprint'],
            ['code' => 'MAT-PKG-POLY30',   'name' => 'Poly Bag 30x40cm',                 'description' => 'Clear poly bag for garment packing, 30x40 cm',      'type' => 'PACKAGING',      'unit' => 'pcs',    'tracking' => 'none',  'stock' => 2000, 'min' => 500, 'supplier' => 'Generic'],
            // Blanks and backing the sub-assemblies are built from.
            ['code' => 'MAT-GAR-POLO-NV',  'name' => 'Piqué Polo Blank Navy',            'description' => 'Piqué cotton polo blank, navy, assorted sizes',      'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 300,  'min' => 60,  'supplier' => 'Gildan'],
            ['code' => 'MAT-GAR-CAP-NV',   'name' => 'Baseball Cap Blank Navy',          'description' => 'Structured six-panel cap blank, navy',               'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 180,  'min' => 40,  'supplier' => 'Flexfit'],
            ['code' => 'MAT-GAR-TOTE-NAT', 'name' => 'Cotton Tote Blank Natural',        'description' => 'Natural cotton tote blank, long handles',            'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 260,  'min' => 60,  'supplier' => 'Westford Mill'],
            ['code' => 'MAT-GAR-PIL-NAT',  'name' => 'Pillow Cover Blank',               'description' => 'Polyester pillow cover blank for sublimation, 40x40', 'type' => 'GARMENT',        'unit' => 'pcs',    'tracking' => 'batch', 'stock' => 140,  'min' => 30,  'supplier' => 'Texprint'],
            ['code' => 'MAT-FELT-PATCH',   'name' => 'Patch Felt Roll',                  'description' => 'Wool-blend felt for embroidered patch blanks',        'type' => 'TRANSFER_MEDIA', 'unit' => 'm2',     'tracking' => 'batch', 'stock' => 90,   'min' => 20,  'supplier' => 'Madeira'],
            ['code' => 'MAT-STAB-CUT',     'name' => 'Cut-away Stabiliser',              'description' => 'Cut-away backing, 75gsm, for patch and garment embroidery', 'type' => 'TRANSFER_MEDIA', 'unit' => 'm2', 'tracking' => 'batch', 'stock' => 210, 'min' => 50, 'supplier' => 'Madeira'],
        ];

        $materials = [];
        foreach ($materialDefs as $def) {
            $mat = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'material_type_id' => $typeModels[$def['type']]->id,
                    'unit_of_measure' => $def['unit'],
                    'tracking_type' => $def['tracking'],
                    'stock_quantity' => $def['stock'],
                    'min_stock_level' => $def['min'],
                    'is_active' => true,
                    'supplier_name' => $def['supplier'],
                ]
            );
            $materials[$def['code']] = $mat;
        }

        // The sub-assemblies. `is_manufactured` plus the routing that produces
        // them is what lets a BOM line for one explode into the level below.
        //
        // Stock is uneven on purpose: the patch is held short, so a hoodie run
        // has to be netted two levels down before the shortage appears.
        $subAssemblies = [
            ['code' => 'SA-PATCH',    'template' => 'SA_PATCH',    'name' => 'Embroidered Patch',      'stock' => 60],
            ['code' => 'SA-DECOKIT',  'template' => 'SA_DECOKIT',  'name' => 'Decoration Kit',         'stock' => 45],
            ['code' => 'SA-TRANSFER', 'template' => 'SA_TRANSFER', 'name' => 'Printed Transfer Sheet', 'stock' => 320],
        ];

        $semiFinished = MaterialType::updateOrCreate(
            ['code' => 'SEMI_FINISHED'],
            ['name' => 'Semi-finished']
        );

        foreach ($subAssemblies as $def) {
            $template = $templates[$def['template']] ?? null;
            if (! $template) {
                continue;
            }

            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $semiFinished->id,
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

    // ── Reported issues ──────────────────────────────────────────────────────

    /**
     * Problems the shop floor actually reported.
     *
     * The seeder only created issue *types* — the dictionary an operator picks
     * from — so the Reported Issues page came up empty and neither the planner
     * nor the shift monitor had anything to show.
     *
     * Two things decide whether an issue is visible rather than merely present:
     *
     *  - The shift monitor pins an issue on its timeline when the issue's work
     *    order is on that station's line AND reported_at falls inside the shift
     *    window being viewed (ShiftMonitorService). So the times below are
     *    placed inside real shifts, spread back across the fortnight of history
     *    the monitor keeps, with two in the shift running now.
     *  - The planner has no notion of issues at all; a problem reaches the board
     *    only as a blocked order. A blocking issue type is what does that on the
     *    shop floor (Web\Operator\IssueController), so the blocking ones here
     *    put their order into BLOCKED the same way.
     *
     * @param  array<string, Line>  $lines
     * @param  array<int, User>  $users
     */
    private function seedIssues(array $lines, array $users): void
    {
        $types = IssueType::pluck('id', 'code');
        // Prefer the operators, but do not depend on roles existing: this
        // seeder can run before RolesAndPermissionsSeeder, and filtering on a
        // role nobody holds yet would silently report nothing at all.
        $reporters = collect($users)->filter(fn (User $u) => $u->hasRole('Operator'))->values();

        if ($reporters->isEmpty()) {
            $reporters = collect($users)->values();
        }

        if ($types->isEmpty() || $reporters->isEmpty()) {
            return;
        }

        // Only lines the shift monitor actually watches — an issue on a line
        // with no monitored station would never draw a pin.
        $onLine = function (string $lineCode) use ($lines): ?WorkOrder {
            $line = $lines[$lineCode] ?? null;

            return $line
                ? WorkOrder::where('line_id', $line->id)->orderBy('order_no')->first()
                : null;
        };

        // [line, type, title, description, status, hours ago, at this hour]
        $defs = [
            // Running now. `null` hours means "inside whatever shift is open on
            // that line right now" — a fixed offset only lands in the live
            // window while a shift happens to be running, and outside those
            // hours ShiftWindow falls back to a synthetic split the issue would
            // sit outside of. The monitor would then draw no pin at all.
            ['DTG', 'PRINT_HEAD_FAILURE', 'Print head dropping cyan on the left third',
                'Nozzle check shows a full bank out on cyan. Cleaning cycle ran twice with no change. Stopped the run rather than scrap shirts.',
                Issue::STATUS_OPEN, null],
            ['HAFT', 'THREAD_BREAK', 'Upper thread snapping every few hundred stitches',
                'Head 3 keeps breaking on the dense fill. Re-threaded and dropped tension a touch; watching it.',
                Issue::STATUS_OPEN, null],

            // Earlier today and yesterday.
            ['SITO', 'SCREEN_CLOGGED', 'Screen blocking on the fine detail',
                'Small text filling in after about forty pulls. Flooded and wiped, holding for now but it will need re-washing.',
                Issue::STATUS_ACKNOWLEDGED, 6],
            ['DTG', 'PRINT_COLOR_MISMATCH', 'Navy printing closer to purple',
                'Customer supplied RGB artwork. Converted to the shop profile and reprinted one for approval.',
                Issue::STATUS_RESOLVED, 20],
            ['HAFT', 'SIZE_MISMATCH', 'Logo sitting 15 mm low on the left chest',
                'Hooping guide had slipped. Re-set the guide and checked the next five.',
                Issue::STATUS_RESOLVED, 28],

            // Older, worked through to closed — history when paging back.
            ['SITO', 'INK_SHORTAGE', 'Out of plastisol black mid-run',
                'Tin ran dry with sixty pieces to go. Held the order until the delivery came in the afternoon.',
                Issue::STATUS_CLOSED, 3 * 24 + 4],
            ['DTG', 'SUBSTRATE_DAMAGE', 'Scorch marks from the heat press',
                'Press platen ran hot and marked four shirts. Re-calibrated the thermostat and replaced the garments.',
                Issue::STATUS_CLOSED, 5 * 24 + 7],
            ['HAFT', 'ARTWORK_ERROR', 'Digitised file has the wrong stitch order',
                'Border stitched before the fill, so the fill pulled over it. Sent back to be re-digitised.',
                Issue::STATUS_CLOSED, 8 * 24 + 3],
            ['SITO', 'PRESS_TEMP_ERROR', 'Dryer running 20 degrees under set point',
                'Cure test failed on the first three. Element replaced and the dryer re-profiled.',
                Issue::STATUS_CLOSED, 11 * 24 + 5],
            ['DTG', 'PRINT_SMEAR', 'Ink smearing on the shoulder seam',
                'Platen height was set for a flat tee, not a raglan. Adjusted and reprinted the batch.',
                Issue::STATUS_CLOSED, 13 * 24 + 6],
        ];

        foreach ($defs as $i => [$lineCode, $typeCode, $title, $description, $status, $hoursAgo]) {
            $workOrder = $onLine($lineCode);
            $typeId = $types[$typeCode] ?? null;

            if (! $workOrder || ! $typeId) {
                continue;
            }

            // `now()` rather than a computed offset: whichever shift the
            // monitor is showing, its window contains the present moment by
            // definition, so a report stamped now is always inside it. Placing
            // it relative to the window instead is a race — the shift can turn
            // over between seeding and reading, and the issue lands in the one
            // that just closed.
            $reportedAt = $hoursAgo === null ? now() : now()->subHours((int) round($hoursAgo));
            $reporter = $reporters[$i % $reporters->count()];

            $issue = Issue::updateOrCreate(
                ['work_order_id' => $workOrder->id, 'title' => $title],
                [
                    'issue_type_id' => $typeId,
                    'description' => $description,
                    'status' => $status,
                    'reported_by_id' => $reporter->id,
                    'reported_at' => $reportedAt,
                    // Each stamp only exists once the issue has reached that
                    // point, so a half-closed row cannot claim it was resolved.
                    'acknowledged_at' => in_array($status, [Issue::STATUS_ACKNOWLEDGED, Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(12) : null,
                    'resolved_at' => in_array($status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(70) : null,
                    'closed_at' => $status === Issue::STATUS_CLOSED
                        ? $reportedAt->copy()->addMinutes(150) : null,
                ]
            );

            // A blocking type stops the order on the shop floor, and a stopped
            // order is the only way a problem reaches the planner board.
            $blocking = IssueType::find($typeId)?->is_blocking;

            if ($blocking && in_array($status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED], true)) {
                $workOrder->forceFill(['status' => WorkOrder::STATUS_BLOCKED])->saveQuietly();
            }

            unset($issue);
        }
    }

    // ── Customers ────────────────────────────────────────────────────────────

    /**
     * Who the work is for.
     *
     * Nothing seeded these, so the Customers page was empty and every order
     * showed a blank customer — which also left the priority scoring and the
     * tier/payment-score columns with nothing to act on.
     *
     * Tiers and payment scores are spread deliberately: priority scoring reads
     * both, so a board where everyone is Gold and pays on time would rank every
     * order identically.
     *
     * @return array<int, Customer>
     */
    private function seedCustomers(): array
    {
        $defs = [
            ['code' => 'CUST-NORDWEAR', 'name' => 'NordWear Retail Group',   'tier' => Tier::Vip,    'payment_score' => 96, 'notes' => 'Seasonal ranges, firm launch dates. Artwork always supplied print-ready.'],
            ['code' => 'CUST-VOLTFC',   'name' => 'Volt FC',                 'tier' => Tier::Gold,   'payment_score' => 88, 'notes' => 'Match kit and supporter merchandise. Numbers and names supplied per order.'],
            ['code' => 'CUST-BRIGHTAG', 'name' => 'Brightside Agency',       'tier' => Tier::Gold,   'payment_score' => 71, 'notes' => 'Event and conference merchandise. Briefs often change late.'],
            ['code' => 'CUST-KAMBUILD', 'name' => 'Kaminski Build Sp. z o.o.', 'tier' => Tier::Silver, 'payment_score' => 64, 'notes' => 'Workwear polos and hi-vis. Repeat orders, same artwork.'],
            ['code' => 'CUST-CAFELOOP', 'name' => 'Café Loop',               'tier' => Tier::Bronze, 'payment_score' => 52, 'notes' => 'Small batches of aprons, totes and mugs for two sites.'],
            ['code' => 'CUST-UNIHACK',  'name' => 'University Hack Society', 'tier' => Tier::Bronze, 'payment_score' => 40, 'notes' => 'One-off hoodie runs. Pays on invoice, sometimes late.'],
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
     * Put a customer behind every order.
     *
     * Assignment is by position rather than at random, so the same order keeps
     * the same customer across re-seeds — the board would otherwise reshuffle
     * who owns what on every run.
     *
     * @param  array<int, Customer>  $customers
     */
    private function assignCustomers(array $customers): void
    {
        if ($customers === []) {
            return;
        }

        $orders = WorkOrder::orderBy('order_no')->get();

        foreach ($orders->values() as $i => $order) {
            $customer = $customers[$i % count($customers)];

            $order->forceFill([
                'customer_id' => $customer->id,
                // Their own reference for the job, which is what the shop floor
                // actually quotes back to them.
                'customer_order_no' => sprintf('PO-%s-%04d', now()->year, 1000 + $i),
            ])->saveQuietly();
        }
    }

    // ── Multi-line orders ────────────────────────────────────────────────────

    /**
     * Orders that run on more than one line.
     *
     * A decorated garment really does move between lines: printed on one,
     * embroidered on another, packed on a third. The planner draws these as a
     * badge on the primary block plus a connector down to the extra segment,
     * and that whole display had no example data behind it — the only way to
     * see it was to drag a block onto a second line by hand.
     *
     * Segments are coarse (day + shift); the minute-level plan stays with the
     * primary placement, so this only sets due_date/shift_number.
     *
     * @param  array<string, Line>  $lines
     */
    private function seedMultiLinePlacements(array $lines): void
    {
        // Where work hands off, and how many shifts later the next line picks
        // it up. Packing is downstream of everything, so it appears most.
        $handoffs = [
            'DTG' => [['HAFT', 1], ['PACKING', 2]],
            'SITO' => [['PACKING', 2]],
            'HAFT' => [['PACKING', 1]],
            'TRANSFER' => [['HAFT', 1], ['PACKING', 2]],
        ];

        $lineById = [];
        foreach ($lines as $code => $line) {
            $lineById[$line->id] = $code;
        }

        // Only the generated orders (WO-2026-0100+), and only those with a
        // planned start — a segment hanging off an unscheduled order would
        // draw a connector to nothing.
        $orders = WorkOrder::where('order_no', 'like', 'WO-2026-01%')
            ->whereNotNull('planned_start_at')
            ->orderBy('order_no')
            ->get();

        // Wipe every candidate's segments before laying any down. The order
        // generator assigns lines at random, so a re-run both moves orders to
        // different lines and picks a different subset — clearing only the
        // orders selected this time would strand the previous run's segments
        // and grow the set on every seed.
        WorkOrderPlacement::whereIn('work_order_id', $orders->pluck('id'))->delete();

        $n = 0;

        foreach ($orders as $order) {
            $fromCode = $lineById[$order->line_id] ?? null;
            if (! $fromCode || ! isset($handoffs[$fromCode])) {
                continue;
            }

            // Every fourth eligible order, so the board shows the case often
            // enough to notice without every block sprouting a connector.
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
                // rather than emitting a fourth shift that no column matches.
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

    // ── Bill of materials ────────────────────────────────────────────────────

    /**
     * What each product is built from, and what the sub-assemblies are built
     * from in turn.
     *
     * Two things this is shaped to demonstrate. The hoodie runs three
     * manufactured levels deep — kit, then patch, then felt and stabiliser — so
     * a shortage can surface below the first manufactured line. And the patch
     * feeds both the kit and the cap, while the transfer sheet feeds both the
     * tote and the pillow, so netting has to sum two parents' demand before it
     * decides how many to make.
     *
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<string, Material>  $materials
     */
    private function seedBom(array $templates, array $materials): void
    {
        // [product => [[step number, material code, qty per unit, scrap %, consumed at], …]]
        $defs = [
            'TSHIRT' => [
                [2, 'MAT-GAR-TSH-W',    1,     2, 'start'],
                [4, 'MAT-INK-DTG-CMYK', 0.004, 3, 'during'],
                [4, 'MAT-INK-DTG-W',    0.02,  4, 'during'],
                [7, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            'SWEATSHIRT' => [
                [2, 'MAT-GAR-HOOD-BK',  1,     2, 'start'],
                [3, 'MAT-INK-DTG-CMYK', 0.006, 3, 'during'],
                [6, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            // Three levels start here: kit → patch → felt.
            'HOODIE' => [
                [3, 'MAT-GAR-HOOD-BK',  1,     2, 'start'],
                [3, 'SA-DECOKIT',       1,     1, 'start'],
                [4, 'MAT-STAB-CUT',     0.08,  5, 'during'],
                [7, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            'POLO' => [
                [3, 'MAT-GAR-POLO-NV',  1,     2, 'start'],
                [4, 'MAT-INK-PLAST-BK', 0.012, 4, 'during'],
                [7, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            // Takes the patch directly — the other parent of SA-PATCH.
            'CAP' => [
                [2, 'MAT-GAR-CAP-NV',   1,     2, 'start'],
                [2, 'SA-PATCH',         1,     1, 'start'],
                [5, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            'TOTE' => [
                [2, 'MAT-GAR-TOTE-NAT', 1,     2, 'start'],
                [2, 'SA-TRANSFER',      1,     2, 'start'],
                [5, 'MAT-PKG-POLY30',   1,     0, 'end'],
            ],
            // ── Sub-assemblies ───────────────────────────────────────────────
            'SA_PATCH' => [
                [1, 'MAT-FELT-PATCH',   0.006, 6, 'start'],
                [1, 'MAT-STAB-CUT',     0.008, 5, 'start'],
                [2, 'MAT-THR-POLY',     0.03,  4, 'during'],
            ],
            'SA_DECOKIT' => [
                [1, 'SA-PATCH',         1,     1, 'start'],
                [2, 'MAT-THR-POLY',     0.01,  2, 'during'],
            ],
            'SA_TRANSFER' => [
                [1, 'MAT-TRN-SUB-A3',   1,     4, 'start'],
                [1, 'MAT-INK-DTG-CMYK', 0.002, 3, 'during'],
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
                    [
                        'process_template_id' => $template->id,
                        'material_id' => $material->id,
                    ],
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

    // ── Material Lots ────────────────────────────────────────────────────────

    private function seedMaterialLots(array $materials): void
    {
        $lots = [
            ['lot' => 'LOT-INK-2026-001', 'material' => 'MAT-INK-DTG-W',    'qty' => 10, 'unit' => 'litre', 'supplier_lot' => 'EPS-W-20260101'],
            ['lot' => 'LOT-INK-2026-002', 'material' => 'MAT-INK-DTG-CMYK', 'qty' => 5,  'unit' => 'set',   'supplier_lot' => 'EPS-CMYK-20260115'],
            ['lot' => 'LOT-INK-2026-003', 'material' => 'MAT-INK-PLAST-BK', 'qty' => 20, 'unit' => 'kg',    'supplier_lot' => 'UI-BK-20260210'],
            ['lot' => 'LOT-GAR-2026-001', 'material' => 'MAT-GAR-TSH-W',    'qty' => 250, 'unit' => 'pcs',   'supplier_lot' => 'FOTL-WHT-B4420'],
            ['lot' => 'LOT-GAR-2026-002', 'material' => 'MAT-GAR-HOOD-BK',  'qty' => 100, 'unit' => 'pcs',   'supplier_lot' => 'GIL-BLK-H1822'],
        ];

        foreach ($lots as $def) {
            MaterialLot::updateOrCreate(
                ['lot_number' => $def['lot']],
                [
                    'material_id' => $materials[$def['material']]->id,
                    'quantity_received' => $def['qty'],
                    'quantity_available' => $def['qty'],
                    'unit_of_measure' => $def['unit'],
                    'received_at' => now()->subDays(rand(5, 30)),
                    'status' => 'available',
                    'supplier_lot_no' => $def['supplier_lot'],
                ]
            );
        }
    }

    // ── ISA-95 Hierarchy ─────────────────────────────────────────────────────

    private function seedISA95Hierarchy(array $lines): Site
    {
        $site = Site::updateOrCreate(
            ['code' => 'PS-HQ'],
            [
                'name' => 'PrintShop HQ',
                'description' => 'Main production facility for garment printing and decoration',
                'address' => 'ul. Drukarska 15',
                'city' => 'Warsaw',
                'country' => 'PL',
                'timezone' => 'Europe/Warsaw',
                'is_active' => true,
            ]
        );

        $areaDefs = [
            ['code' => 'HALL-A', 'name' => 'Production Hall A', 'description' => 'Main production hall — all printing and embroidery lines'],
            ['code' => 'WH-1',   'name' => 'Warehouse',         'description' => 'Raw materials and finished goods warehouse'],
            ['code' => 'SHIP-1', 'name' => 'Shipping',          'description' => 'Shipping and dispatch area'],
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

        // Link lines to areas (if column exists)
        if (Schema::hasColumn('lines', 'area_id')) {
            $lineAreaMap = [
                'DTG' => 'HALL-A',
                'SITO' => 'HALL-A',
                'HAFT' => 'HALL-A',
                'TRANSFER' => 'HALL-A',
                'PACKING' => 'SHIP-1',
            ];

            foreach ($lineAreaMap as $lineCode => $areaCode) {
                if (isset($lines[$lineCode], $areas[$areaCode])) {
                    $lines[$lineCode]->update(['area_id' => $areas[$areaCode]->id]);
                }
            }
        }

        return $site;
    }

    // ── Skills & Personnel Classes ───────────────────────────────────────────

    private function seedSkillsAndPersonnelClasses(): void
    {
        $skillDefs = [
            ['code' => 'DTG_OPERATION',    'name' => 'DTG Operation',    'description' => 'Operating DTG digital printers including pretreatment and curing'],
            ['code' => 'SCREEN_PRINTING',  'name' => 'Screen Printing',  'description' => 'Screen preparation, registration, and manual/semi-auto printing'],
            ['code' => 'EMBROIDERY',       'name' => 'Embroidery',       'description' => 'Machine embroidery operation, hooping, thread management'],
            ['code' => 'HEAT_TRANSFER',    'name' => 'Heat Transfer',    'description' => 'Heat press and sublimation operations'],
            ['code' => 'QUALITY_CONTROL',  'name' => 'Quality Control',  'description' => 'Visual and instrumental quality inspection of printed goods'],
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
                'code' => 'PRINT_OPERATOR',
                'name' => 'Print Operator',
                'description' => 'Operates DTG and screen printing equipment',
                'required_skill_ids' => [$skills['DTG_OPERATION']->id, $skills['SCREEN_PRINTING']->id],
            ],
            [
                'code' => 'EMBROIDERY_SPECIALIST',
                'name' => 'Embroidery Specialist',
                'description' => 'Specialist in machine embroidery operations',
                'required_skill_ids' => [$skills['EMBROIDERY']->id],
            ],
            [
                'code' => 'QC_INSPECTOR',
                'name' => 'QC Inspector',
                'description' => 'Quality control inspector for all product types',
                'required_skill_ids' => [$skills['QUALITY_CONTROL']->id],
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

    // ── Process Segments ─────────────────────────────────────────────────────

    private function seedProcessSegments(): void
    {
        $defs = [
            ['code' => 'SEG-PRETREAT',     'name' => 'Pretreatment',       'description' => 'Apply pretreat solution to garment before DTG printing',       'segment_type' => 'production', 'duration' => 10, 'operators' => 1, 'instruction' => 'Shake pretreat bottle. Apply evenly over print area. Press with heat press to dry.'],
            ['code' => 'SEG-DTG-PRINT',    'name' => 'DTG Print',          'description' => 'Direct-to-garment digital printing',                           'segment_type' => 'production', 'duration' => 15, 'operators' => 1, 'instruction' => 'Load garment on platen, centre artwork, select correct colour profile, run print.'],
            ['code' => 'SEG-SCREEN-PRINT', 'name' => 'Screen Print',       'description' => 'Screen printing production run',                               'segment_type' => 'production', 'duration' => 30, 'operators' => 2, 'instruction' => 'Mount screen, set registration, pull test print, run production batch.'],
            ['code' => 'SEG-EMBROIDERY',   'name' => 'Embroidery Run',     'description' => 'Machine embroidery of design onto garment or accessory',       'segment_type' => 'production', 'duration' => 25, 'operators' => 1, 'instruction' => 'Thread machine per colour card, hoop garment, start machine, monitor tension.'],
            ['code' => 'SEG-HEAT-PRESS',   'name' => 'Heat Press',         'description' => 'Heat transfer pressing for flex, foil, or sublimation',        'segment_type' => 'production', 'duration' => 8,  'operators' => 1, 'instruction' => 'Set temperature and time per transfer type. Position transfer, press, peel.'],
            ['code' => 'SEG-QC-CHECK',     'name' => 'Quality Check',      'description' => 'Visual and dimensional quality inspection of finished product', 'segment_type' => 'inspection',    'duration' => 5,  'operators' => 1, 'instruction' => 'Inspect colour accuracy, coverage, edge sharpness. Reject defects. Log results.'],
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

    // ── Maintenance Schedules & Events ───────────────────────────────────────

    private function seedMaintenanceSchedulesAndEvents(array $lines, array $workstations): void
    {
        $schedules = [];

        $schedules['dtg_cleaning'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Weekly DTG Printhead Cleaning'],
            [
                'description' => 'Clean DTG printhead nozzles to prevent clogging and colour shift',
                'line_id' => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '06:00',
                'next_due_at' => now()->next('Monday')->setTime(6, 0),
                'is_active' => true,
            ]
        );

        $schedules['embroidery_calibration'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Monthly Embroidery Machine Calibration'],
            [
                'description' => 'Calibrate embroidery machine tension, needle position, and hoop alignment',
                'line_id' => $lines['HAFT']->id,
                'workstation_id' => $workstations['HAFT-1']->id,
                'event_type' => 'planned',
                'frequency' => 'monthly',
                'interval_value' => 1,
                'preferred_time' => '07:00',
                'next_due_at' => now()->startOfMonth()->addMonth()->setTime(7, 0),
                'is_active' => true,
            ]
        );

        $schedules['screen_press'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Bi-weekly Screen Press Maintenance'],
            [
                'description' => 'Inspect and maintain screen printing press — squeegee, clamps, off-contact',
                'line_id' => $lines['SITO']->id,
                'workstation_id' => $workstations['SITO-1']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 2,
                'preferred_time' => '06:30',
                'next_due_at' => now()->addWeeks(2)->setTime(6, 30),
                'is_active' => true,
            ]
        );

        // Events — 2 completed, 1 scheduled, 1 overdue
        $events = [
            [
                'title' => 'DTG Printhead Cleaning (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'schedule_id' => $schedules['dtg_cleaning']->id,
                'scheduled_at' => now()->subWeeks(2)->setTime(6, 0),
                'scheduled_end_at' => now()->subWeeks(2)->setTime(7, 0),
                'description' => 'Routine printhead cleaning completed without issues.',
            ],
            [
                'title' => 'Embroidery Calibration (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['HAFT']->id,
                'workstation_id' => $workstations['HAFT-1']->id,
                'schedule_id' => $schedules['embroidery_calibration']->id,
                'scheduled_at' => now()->subMonth()->setTime(7, 0),
                'scheduled_end_at' => now()->subMonth()->setTime(9, 0),
                'description' => 'Monthly calibration done. Tension adjusted on head 3.',
            ],
            [
                'title' => 'Screen Press Maintenance (scheduled)',
                'event_type' => 'planned',
                'status' => 'pending',
                'line_id' => $lines['SITO']->id,
                'workstation_id' => $workstations['SITO-1']->id,
                'schedule_id' => $schedules['screen_press']->id,
                'scheduled_at' => now()->addDays(1)->setTime(6, 30),
                'scheduled_end_at' => now()->addDays(1)->setTime(8, 30),
                'description' => 'Bi-weekly screen press inspection.',
            ],
            [
                'title' => 'DTG Printhead Cleaning (overdue)',
                'event_type' => 'planned',
                'status' => 'pending',
                'line_id' => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'schedule_id' => $schedules['dtg_cleaning']->id,
                'scheduled_at' => now()->setTime(14, 0),
                'scheduled_end_at' => now()->setTime(15, 0),
                'description' => 'Weekly printhead cleaning — due today.',
            ],
        ];

        foreach ($events as $event) {
            MaintenanceEvent::updateOrCreate(
                ['title' => $event['title']],
                $event
            );
        }
    }

    // ── Inspection Plans ─────────────────────────────────────────────────────

    private function seedInspectionPlans(array $materials): void
    {
        InspectionPlan::updateOrCreate(
            ['name' => 'Garment Incoming Inspection'],
            [
                'description' => 'Quality inspection for incoming garment blanks',
                'material_id' => $materials['MAT-GAR-TSH-W']->id,
                'criteria' => ['fabric_weight', 'colour_consistency', 'stitching_quality'],
                'is_active' => true,
            ]
        );

        InspectionPlan::updateOrCreate(
            ['name' => 'Ink Quality Check'],
            [
                'description' => 'Quality verification for DTG ink batches',
                'material_id' => $materials['MAT-INK-DTG-W']->id,
                'criteria' => ['viscosity', 'colour_accuracy', 'expiry_check'],
                'is_active' => true,
            ]
        );
    }

    // ── OEE Records ─────────────────────────────────────────────────────────

    private function seedOeeRecords(array $lines): void
    {
        foreach ($lines as $code => $line) {
            if ($code === 'PACKING') {
                continue;
            } // skip non-production line

            for ($day = -14; $day <= 0; $day++) {
                $date = now()->addDays($day)->format('Y-m-d');
                $planned = rand(420, 480);          // 7-8h planned
                $downtime = rand(10, 60);
                $operating = $planned - $downtime;
                $totalProduced = rand(40, 200);
                $scrap = rand(0, (int) ($totalProduced * 0.08));
                $good = $totalProduced - $scrap;

                $availability = round($operating / max($planned, 1) * 100, 1);
                $performance = round(rand(70, 98), 1);
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
