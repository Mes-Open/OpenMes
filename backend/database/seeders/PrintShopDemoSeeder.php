<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Line;
use App\Models\Workstation;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use App\Models\Shift;
use App\Models\MaterialType;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Site;
use App\Models\Area;
use App\Models\Skill;
use App\Models\PersonnelClass;
use App\Models\ProcessSegment;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceEvent;
use App\Models\InspectionPlan;
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
        $lines        = $this->seedLines();
        $workstations = $this->seedWorkstations($lines);
        $productTypes = $this->seedProductTypes();
        $this->seedProcessTemplates($productTypes, $workstations, $lines);
        $this->seedUsers($lines);
        $this->seedWorkOrders($productTypes, $lines);
        $this->seedShifts($lines);
        $materials = $this->seedMaterials();
        $this->seedMaterialLots($materials);
        $site = $this->seedISA95Hierarchy($lines);
        $this->seedSkillsAndPersonnelClasses();
        $this->seedProcessSegments();
        $this->seedMaintenanceSchedulesAndEvents($lines, $workstations);
        $this->seedInspectionPlans($materials);
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
            ['line' => 'HAFT',     'code' => 'HAFT-1',      'name' => 'Embroidery Machine #1 (Barudan)','workstation_type' => 'embroidery'],
            ['line' => 'HAFT',     'code' => 'HAFT-2',      'name' => 'Embroidery Machine #2 (Barudan)','workstation_type' => 'embroidery'],
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
                    'line_id'          => $lines[$def['line']]->id,
                    'name'             => $def['name'],
                    'workstation_type' => $def['workstation_type'],
                    'is_active'        => true,
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
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    // ── Process templates ─────────────────────────────────────────────────────

    private function seedProcessTemplates(array $pt, array $ws, array $lines): void
    {
        $this->createTemplate($pt['TSHIRT'], 'T-Shirt — DTG Printing', [
            [1, 'Artwork verification',      'Check resolution (min 150 dpi), colour profile, no elements too close to edges.', 10, null],
            [2, 'Pre-wash and press',        'Pre-wash garment if label says "wash before print". Press flat with heat press.', 5, $ws['DTG-PRE-1'] ?? null],
            [3, 'Pretreating',               'Apply pretreat solution evenly over print area. Shake bottle well before use.', 10, $ws['DTG-PRE-1'] ?? null],
            [4, 'DTG printing',              'Place shirt on platen, centre artwork. Run print using correct colour profile.', 15, $ws['DTG-1'] ?? null],
            [5, 'Curing',                    'Pass through conveyor oven: 165 °C, approx. 90 sec. Check moisture level first.', 8, $ws['DTG-CURE-1'] ?? null],
            [6, 'Quality control',           'Check colour coverage, edge sharpness, no smearing. Reject any defects.', 5, null],
            [7, 'Packing',                   'Fold neatly, place in poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);

        $this->createTemplate($pt['HOODIE'], 'Hoodie — Machine Embroidery', [
            [1, 'Embroidery file check',     'Open DST/PES file, verify thread colours, start/stop points and density.', 15, null],
            [2, 'Machine & thread setup',    'Thread machine per colour card. Mount correct stabiliser (tearaway / cutaway).', 10, $ws['HAFT-1'] ?? null],
            [3, 'Hooping',                   'Hoop the hoodie taut and flat — no wrinkles or puckers.', 8, $ws['HAFT-1'] ?? null],
            [4, 'Embroidery run',            'Start machine. Monitor first 10 stitches. Check thread tension every 5 min.', 25, $ws['HAFT-1'] ?? null],
            [5, 'Trim and finish',           'Remove excess stabiliser, trim jump stitches, steam if required.', 10, null],
            [6, 'Quality control',           'Check stitch density, no jumps, colour alignment against artwork.', 5, null],
            [7, 'Packing',                   'Fold hoodie, poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);

        $this->createTemplate($pt['POLO'], 'Polo Shirt — Screen Printing', [
            [1, 'Screen preparation',        'Expose screen from film positive. Check open areas after washing out.', 20, $ws['SITO-EXP-1'] ?? null],
            [2, 'Registration setup',        'Mount screen on press. Set registration using rulers and tape.', 10, $ws['SITO-1'] ?? null],
            [3, 'Test print',                'Pull one test print. Check coverage, registration and colour. Sign off before production.', 10, $ws['SITO-1'] ?? null],
            [4, 'Production run',            'Print full batch. Top up ink every ~30 pcs. Spot-check every 10th piece.', 30, $ws['SITO-1'] ?? null],
            [5, 'Curing',                    'Pass through conveyor dryer: 160 °C / 60 sec. Tape-peel adhesion test.', 10, $ws['SITO-DRY-1'] ?? null],
            [6, 'Quality control',           'Sample-check every 20 pcs: coverage, sharpness, no ink haze.', 5, null],
            [7, 'Packing',                   'Fold polo shirts, pack in dozens (12 pcs). Apply batch labels.', 8, $ws['PAK-2'] ?? null],
        ]);

        $this->createTemplate($pt['CAP'], 'Baseball Cap — Embroidery', [
            [1, 'Embroidery file check',     'Verify file is adapted for cap embroidery (flat area, max 80 mm width).', 10, null],
            [2, 'Cap frame setup',           'Mount cap frame on machine. Stretch cap brim flat in frame.', 8, $ws['HAFT-2'] ?? null],
            [3, 'Embroidery run',            'Start machine. Monitor carefully — curved surface needs stable hooping.', 20, $ws['HAFT-2'] ?? null],
            [4, 'Trim and finish',           'Remove stabiliser, trim threads, inspect back of brim.', 5, null],
            [5, 'Quality control',           'Check embroidery centring on brim, colour accuracy.', 5, null],
            [6, 'Packing',                   'Place cap in poly bag, attach order label.', 3, $ws['PAK-1'] ?? null],
        ]);

        $this->createTemplate($pt['TOTE'], 'Cotton Tote Bag — Heat Transfer', [
            [1, 'Print transfer film',       'Print transfer on plotter or transfer printer. Allow to dry fully.', 10, null],
            [2, 'Heat press setup',          'Set temperature: 160 °C, time 15 sec, medium pressure. Pre-heat 5 min.', 5, $ws['TRANS-1'] ?? null],
            [3, 'Position transfer',         'Lay bag flat on press platen, centre transfer. Use ruler or template.', 5, $ws['TRANS-1'] ?? null],
            [4, 'Press transfer',            'Apply cover sheet, close press. Hold 15 sec. Peel film cold or hot per manufacturer instructions.', 5, $ws['TRANS-1'] ?? null],
            [5, 'Quality control',           'Check transfer edges, no air bubbles, full coverage.', 3, null],
            [6, 'Packing',                   'Fold bag, place in poly bag with print facing out.', 3, $ws['PAK-2'] ?? null],
        ]);

        $this->createTemplate($pt['MUG'], 'Sublimation Mug', [
            [1, 'Print sublimation transfer','Print artwork mirrored on sublimation paper. Trim with 5 mm margin.', 10, null],
            [2, 'Wrap mug',                  'Wrap mug with transfer paper, secure with heat-resistant tape. No wrinkles.', 5, $ws['TRANS-SUB-1'] ?? null],
            [3, 'Sublimation in oven',       'Place in sublimation oven: 200 °C / 4 min. Do not open early.', 5, $ws['TRANS-SUB-1'] ?? null],
            [4, 'Cool and unwrap',           'Remove mug, allow to cool 2 min. Peel paper.', 3, null],
            [5, 'Quality control',           'Check colour saturation, no white spots (insufficient pressure), sharpness.', 5, null],
            [6, 'Packing',                   'Place mug in box with protective padding to prevent breakage.', 3, $ws['PAK-1'] ?? null],
        ]);

        $this->createTemplate($pt['SWEATSHIRT'], 'Crewneck Sweatshirt — DTG Printing', [
            [1, 'Artwork verification',      'Check resolution, colour profile, print dimensions (max A3).', 10, null],
            [2, 'Pretreating',               'Apply pretreat to sweatshirt. Heavier fabric — increase dose by 15%.', 12, $ws['DTG-PRE-1'] ?? null],
            [3, 'DTG printing',              'Load sweatshirt on platen, centre artwork. Use heavy-fabric print profile.', 18, $ws['DTG-2'] ?? null],
            [4, 'Curing',                    'Pass through oven: 165 °C, 100 sec (longer than t-shirt — thicker fabric).', 10, $ws['DTG-CURE-1'] ?? null],
            [5, 'Quality control',           'Check coverage, no smearing, no gaps in print.', 5, null],
            [6, 'Packing',                   'Fold, place in poly bag, attach order label.', 5, $ws['PAK-1'] ?? null],
        ]);
    }

    private function createTemplate(ProductType $productType, string $name, array $steps): void
    {
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'version' => 1],
            ['name' => $name, 'is_active' => true]
        );

        foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $workstation]) {
            DB::table('template_steps')->updateOrInsert(
                ['process_template_id' => $template->id, 'step_number' => $stepNo],
                [
                    'name'                       => $stepName,
                    'instruction'                => $instruction,
                    'estimated_duration_minutes' => $duration,
                    'workstation_id'             => $workstation?->id,
                    'created_at'                 => now(),
                ]
            );
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole   = Role::where('name', 'Operator')->first();

        $users = [];

        $supervisor = User::updateOrCreate(
            ['username' => 'peter.wilson'],
            [
                'name'                  => 'Peter Wilson',
                'email'                 => 'peter.wilson@printshop.local',
                'password'              => Hash::make('Supervisor1!'),
                'account_type'          => 'user',
                'force_password_change' => false,
            ]
        );
        if ($supervisorRole && !$supervisor->hasRole('Supervisor')) {
            $supervisor->assignRole($supervisorRole);
        }
        $supervisor->lines()->syncWithoutDetaching(array_map(fn($l) => $l->id, $lines));
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
                    'name'                  => $def['name'],
                    'email'                 => $def['email'],
                    'password'              => Hash::make('Operator1!'),
                    'account_type'          => 'user',
                    'force_password_change' => false,
                ]
            );
            if ($operatorRole && !$user->hasRole('Operator')) {
                $user->assignRole($operatorRole);
            }
            $lineIds = array_map(fn($code) => $lines[$code]->id, $def['lines']);
            $user->lines()->syncWithoutDetaching($lineIds);
            $users[] = $user;
        }

        return $users;
    }

    // ── Example work orders ───────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        $orders = [
            [
                'order_no'        => 'WO-2026-001',
                'line_id'         => $lines['DTG']->id,
                'product_type_id' => $pt['TSHIRT']->id,
                'planned_qty'     => 50,
                'status'          => WorkOrder::STATUS_IN_PROGRESS,
                'priority'        => 3,
                'due_date'        => now()->addDays(2),
                'description'     => 'Corporate t-shirts — XYZ Ltd. logo, white base, DTG print, sizes M/L/XL',
            ],
            [
                'order_no'        => 'WO-2026-002',
                'line_id'         => $lines['HAFT']->id,
                'product_type_id' => $pt['CAP']->id,
                'planned_qty'     => 30,
                'status'          => WorkOrder::STATUS_PENDING,
                'priority'        => 2,
                'due_date'        => now()->addDays(5),
                'description'     => 'Sports team caps — 3D embroidery logo, navy blue',
            ],
            [
                'order_no'        => 'WO-2026-003',
                'line_id'         => $lines['SITO']->id,
                'product_type_id' => $pt['POLO']->id,
                'planned_qty'     => 100,
                'status'          => WorkOrder::STATUS_ACCEPTED,
                'priority'        => 4,
                'due_date'        => now()->addDay(),
                'description'     => 'Polo shirts screen print 2 colours — workwear for construction company',
            ],
            [
                'order_no'        => 'WO-2026-004',
                'line_id'         => $lines['TRANSFER']->id,
                'product_type_id' => $pt['TOTE']->id,
                'planned_qty'     => 200,
                'status'          => WorkOrder::STATUS_PENDING,
                'priority'        => 1,
                'due_date'        => now()->addDays(7),
                'description'     => 'Conference tote bags — flex transfer, single colour print',
            ],
            [
                'order_no'        => 'WO-2026-005',
                'line_id'         => $lines['DTG']->id,
                'product_type_id' => $pt['HOODIE']->id,
                'planned_qty'     => 25,
                'status'          => WorkOrder::STATUS_DONE,
                'priority'        => 2,
                'due_date'        => now()->subDay(),
                'description'     => 'Artist hoodies — limited edition, full-colour DTG print',
                'completed_at'    => now()->subHours(3),
            ],
            [
                'order_no'        => 'WO-2026-006',
                'line_id'         => $lines['TRANSFER']->id,
                'product_type_id' => $pt['MUG']->id,
                'planned_qty'     => 48,
                'status'          => WorkOrder::STATUS_IN_PROGRESS,
                'priority'        => 3,
                'due_date'        => now()->addDays(3),
                'description'     => 'Sublimation mugs — personalised customer photos, gift order',
            ],
            [
                'order_no'        => 'WO-2026-007',
                'line_id'         => $lines['HAFT']->id,
                'product_type_id' => $pt['SWEATSHIRT']->id,
                'planned_qty'     => 15,
                'status'          => WorkOrder::STATUS_PENDING,
                'priority'        => 2,
                'due_date'        => now()->addDays(4),
                'description'     => 'University crewneck sweatshirts — embroidered crest, black, sizes S–XXL',
            ],
        ];

        foreach ($orders as $orderData) {
            WorkOrder::updateOrCreate(
                ['order_no' => $orderData['order_no']],
                array_merge($orderData, ['produced_qty' => 0])
            );
        }
    }

    // ── Shifts ───────────────────────────────────────────────────────────────

    private function seedShifts(array $lines): void
    {
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $monToThu = ['monday', 'tuesday', 'wednesday', 'thursday'];

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
                $uniqueCode = $def['code'] . '-' . $shortLine;
                Shift::updateOrCreate(
                    ['code' => $uniqueCode],
                    [
                        'name'         => $def['name'],
                        'start_time'   => $def['start_time'],
                        'end_time'     => $def['end_time'],
                        'days_of_week' => $def['days_of_week'],
                        'line_id'      => $lines[$lineCode]->id,
                        'is_active'    => true,
                        'sort_order'   => $def['sort_order'],
                    ]
                );
            }
        }
    }

    // ── Materials & Material Types ───────────────────────────────────────────

    private function seedMaterials(): array
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
        ];

        $materials = [];
        foreach ($materialDefs as $def) {
            $mat = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name'             => $def['name'],
                    'description'      => $def['description'],
                    'material_type_id' => $typeModels[$def['type']]->id,
                    'unit_of_measure'  => $def['unit'],
                    'tracking_type'    => $def['tracking'],
                    'stock_quantity'   => $def['stock'],
                    'min_stock_level'  => $def['min'],
                    'is_active'        => true,
                    'supplier_name'    => $def['supplier'],
                ]
            );
            $materials[$def['code']] = $mat;
        }

        return $materials;
    }

    // ── Material Lots ────────────────────────────────────────────────────────

    private function seedMaterialLots(array $materials): void
    {
        $lots = [
            ['lot' => 'LOT-INK-2026-001', 'material' => 'MAT-INK-DTG-W',    'qty' => 10, 'unit' => 'litre', 'supplier_lot' => 'EPS-W-20260101'],
            ['lot' => 'LOT-INK-2026-002', 'material' => 'MAT-INK-DTG-CMYK', 'qty' => 5,  'unit' => 'set',   'supplier_lot' => 'EPS-CMYK-20260115'],
            ['lot' => 'LOT-INK-2026-003', 'material' => 'MAT-INK-PLAST-BK', 'qty' => 20, 'unit' => 'kg',    'supplier_lot' => 'UI-BK-20260210'],
            ['lot' => 'LOT-GAR-2026-001', 'material' => 'MAT-GAR-TSH-W',    'qty' => 250,'unit' => 'pcs',   'supplier_lot' => 'FOTL-WHT-B4420'],
            ['lot' => 'LOT-GAR-2026-002', 'material' => 'MAT-GAR-HOOD-BK',  'qty' => 100,'unit' => 'pcs',   'supplier_lot' => 'GIL-BLK-H1822'],
        ];

        foreach ($lots as $def) {
            MaterialLot::updateOrCreate(
                ['lot_number' => $def['lot']],
                [
                    'material_id'        => $materials[$def['material']]->id,
                    'quantity_received'   => $def['qty'],
                    'quantity_available'  => $def['qty'],
                    'unit_of_measure'     => $def['unit'],
                    'received_at'         => now()->subDays(rand(5, 30)),
                    'status'              => 'available',
                    'supplier_lot_no'     => $def['supplier_lot'],
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
                'name'        => 'PrintShop HQ',
                'description' => 'Main production facility for garment printing and decoration',
                'address'     => 'ul. Drukarska 15',
                'city'        => 'Warsaw',
                'country'     => 'PL',
                'timezone'    => 'Europe/Warsaw',
                'is_active'   => true,
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
                    'name'        => $def['name'],
                    'site_id'     => $site->id,
                    'description' => $def['description'],
                    'is_active'   => true,
                ]
            );
        }

        // Link lines to areas (if column exists)
        if (Schema::hasColumn('lines', 'area_id')) {
            $lineAreaMap = [
                'DTG'      => 'HALL-A',
                'SITO'     => 'HALL-A',
                'HAFT'     => 'HALL-A',
                'TRANSFER' => 'HALL-A',
                'PACKING'  => 'SHIP-1',
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
                    'name'               => $def['name'],
                    'description'        => $def['description'],
                    'required_skill_ids' => $def['required_skill_ids'],
                    'is_active'          => true,
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
            ['code' => 'SEG-QC-CHECK',     'name' => 'Quality Check',      'description' => 'Visual and dimensional quality inspection of finished product','segment_type' => 'inspection',    'duration' => 5,  'operators' => 1, 'instruction' => 'Inspect colour accuracy, coverage, edge sharpness. Reject defects. Log results.'],
        ];

        foreach ($defs as $def) {
            ProcessSegment::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name'                       => $def['name'],
                    'description'                => $def['description'],
                    'segment_type'               => $def['segment_type'],
                    'estimated_duration_minutes' => $def['duration'],
                    'required_operators'         => $def['operators'],
                    'standard_instruction'       => $def['instruction'],
                    'is_active'                  => true,
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
                'description'    => 'Clean DTG printhead nozzles to prevent clogging and colour shift',
                'line_id'        => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'event_type'     => 'planned',
                'frequency'      => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '06:00',
                'next_due_at'    => now()->next('Monday')->setTime(6, 0),
                'is_active'      => true,
            ]
        );

        $schedules['embroidery_calibration'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Monthly Embroidery Machine Calibration'],
            [
                'description'    => 'Calibrate embroidery machine tension, needle position, and hoop alignment',
                'line_id'        => $lines['HAFT']->id,
                'workstation_id' => $workstations['HAFT-1']->id,
                'event_type'     => 'planned',
                'frequency'      => 'monthly',
                'interval_value' => 1,
                'preferred_time' => '07:00',
                'next_due_at'    => now()->startOfMonth()->addMonth()->setTime(7, 0),
                'is_active'      => true,
            ]
        );

        $schedules['screen_press'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Bi-weekly Screen Press Maintenance'],
            [
                'description'    => 'Inspect and maintain screen printing press — squeegee, clamps, off-contact',
                'line_id'        => $lines['SITO']->id,
                'workstation_id' => $workstations['SITO-1']->id,
                'event_type'     => 'planned',
                'frequency'      => 'biweekly',
                'interval_value' => 2,
                'preferred_time' => '06:30',
                'next_due_at'    => now()->addWeeks(2)->setTime(6, 30),
                'is_active'      => true,
            ]
        );

        // Events — 2 completed, 1 scheduled, 1 overdue
        $events = [
            [
                'title'        => 'DTG Printhead Cleaning (completed)',
                'event_type'   => 'planned',
                'status'       => 'completed',
                'line_id'      => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'schedule_id'  => $schedules['dtg_cleaning']->id,
                'scheduled_at' => now()->subWeeks(2)->setTime(6, 0),
                'description'  => 'Routine printhead cleaning completed without issues.',
            ],
            [
                'title'        => 'Embroidery Calibration (completed)',
                'event_type'   => 'planned',
                'status'       => 'completed',
                'line_id'      => $lines['HAFT']->id,
                'workstation_id' => $workstations['HAFT-1']->id,
                'schedule_id'  => $schedules['embroidery_calibration']->id,
                'scheduled_at' => now()->subMonth()->setTime(7, 0),
                'description'  => 'Monthly calibration done. Tension adjusted on head 3.',
            ],
            [
                'title'        => 'Screen Press Maintenance (scheduled)',
                'event_type'   => 'planned',
                'status'       => 'scheduled',
                'line_id'      => $lines['SITO']->id,
                'workstation_id' => $workstations['SITO-1']->id,
                'schedule_id'  => $schedules['screen_press']->id,
                'scheduled_at' => now()->addWeeks(2)->setTime(6, 30),
                'description'  => 'Upcoming bi-weekly screen press inspection.',
            ],
            [
                'title'        => 'DTG Printhead Cleaning (overdue)',
                'event_type'   => 'planned',
                'status'       => 'overdue',
                'line_id'      => $lines['DTG']->id,
                'workstation_id' => $workstations['DTG-1']->id,
                'schedule_id'  => $schedules['dtg_cleaning']->id,
                'scheduled_at' => now()->subWeek()->setTime(6, 0),
                'description'  => 'Missed weekly printhead cleaning — needs immediate attention.',
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
                'criteria'    => ['fabric_weight', 'colour_consistency', 'stitching_quality'],
                'is_active'   => true,
            ]
        );

        InspectionPlan::updateOrCreate(
            ['name' => 'Ink Quality Check'],
            [
                'description' => 'Quality verification for DTG ink batches',
                'material_id' => $materials['MAT-INK-DTG-W']->id,
                'criteria'    => ['viscosity', 'colour_accuracy', 'expiry_check'],
                'is_active'   => true,
            ]
        );
    }
}
