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
    }

    // ── Issue types ──────────────────────────────────────────────────────────

    private function seedIssueTypes(): void
    {
        $types = [
            ['code' => 'PRINT_COLOR_MISMATCH', 'name' => 'Niezgodność kolorów druku',          'severity' => 'HIGH',     'is_blocking' => false],
            ['code' => 'PRINT_SMEAR',          'name' => 'Rozmazanie / rozlanie druku',         'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SUBSTRATE_DAMAGE',     'name' => 'Uszkodzenie podłoża / odzieży',       'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PRINT_HEAD_FAILURE',   'name' => 'Awaria głowicy drukarki DTG',         'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'THREAD_BREAK',         'name' => 'Zerwanie nici hafciarskiej',          'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'SCREEN_CLOGGED',       'name' => 'Zatkanie sita / szablonu',            'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'INK_SHORTAGE',         'name' => 'Brak farby / tuszów',                 'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'ARTWORK_ERROR',        'name' => 'Błąd pliku graficznego',              'severity' => 'MEDIUM',   'is_blocking' => true],
            ['code' => 'PRESS_TEMP_ERROR',     'name' => 'Błąd temperatury prasy termicznej',   'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SIZE_MISMATCH',        'name' => 'Zły rozmiar / pozycja nadruku',       'severity' => 'MEDIUM',   'is_blocking' => false],
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
}
