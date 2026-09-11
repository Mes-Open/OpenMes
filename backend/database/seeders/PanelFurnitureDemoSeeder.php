<?php

namespace Database\Seeders;

use App\Enums\RevisionLifecycle;
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
use App\Models\ProductRevision;
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
 * Demo data for a panel / carcase furniture factory — flat-pack wardrobes,
 * chests, desks and kitchen units in melamine-faced board.
 *
 * The fifth example company, and it shows the system from an angle the other
 * four do not. Every part here is a rectangle cut from a sheet, and the whole
 * plant is a queue behind one machine: the beam saw. Two consequences run
 * through the whole dataset.
 *
 * The bill of materials is not an assembly tree, it is a chain of
 * transformations — a board becomes a blank, a blank becomes an edged panel,
 * edged panels become a carcase or a drawer box, and only then a product. Four
 * manufactured levels above the purchased sheet, and the drawer box is pulled
 * by four different finished products, so netting has to sum parents before it
 * knows how many to build.
 *
 * And scrap is a yield problem rather than a defect problem. The offcut is lost
 * in the cutting plan, the tape start and stop are lost at the bander, and
 * neither is anybody's fault — which is why the scrap percentages here sit on
 * the sub-assemblies rather than on the finished goods.
 *
 * Run with: `php artisan db:seed --class=PanelFurnitureDemoSeeder`
 *
 * Upsert-safe throughout, so it can be re-run on top of itself.
 */
class PanelFurnitureDemoSeeder extends Seeder
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
        $this->seedProductRevisions($productTypes, $templates, $users);
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
            // Anything that reaches a customer's living room as a visible fault
            // stops the order. A cosmetic mark on a part nobody sees does not.
            ['code' => 'MOISTURE',       'name' => 'Board Moisture / Swollen Edge', 'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'DECOR_MISMATCH', 'name' => 'Decor Batch Mismatch',          'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'EDGE_LIFT',      'name' => 'Edge Tape Lifting / Open Joint', 'severity' => 'HIGH',    'is_blocking' => true],
            ['code' => 'BORE_POSITION',  'name' => 'Hole Pattern Out of Position',  'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PANEL_SIZE',     'name' => 'Panel Out of Size / Out of Square', 'severity' => 'HIGH', 'is_blocking' => true],
            ['code' => 'FITTING_SHORT',  'name' => 'Fittings Pack Short or Wrong',  'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'BOARD_DAMAGE',   'name' => 'Board Face Damaged or Scratched', 'severity' => 'MEDIUM', 'is_blocking' => false],
            ['code' => 'CHIPPING',       'name' => 'Saw Chipping on Face',          'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'GLOSS_DEFECT',   'name' => 'Gloss Front Orange Peel or Pinhole', 'severity' => 'MEDIUM', 'is_blocking' => false],
            ['code' => 'CARTON_DAMAGE',  'name' => 'Carton Crushed or Split',       'severity' => 'LOW',      'is_blocking' => false],
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
            ['code' => 'CUT',   'name' => 'Panel Cutting',        'description' => 'Beam sawing and nested routing from sheet stock, plus part labelling'],
            ['code' => 'EDGE',  'name' => 'Edgebanding',          'description' => 'Single and double-sided banding, softforming of profiled edges'],
            ['code' => 'DRILL', 'name' => 'Drilling & Boring',    'description' => 'CNC point-to-point boring, through-feed dowel insertion, hinge boring'],
            ['code' => 'SURF',  'name' => 'Fronts & Finishing',   'description' => 'Membrane pressing, UV lacquering and profile wrapping of MDF fronts'],
            ['code' => 'ASSY',  'name' => 'Sub-assembly & Hardware', 'description' => 'Drawer box building, carcase clamping and fittings kitting'],
            ['code' => 'PACK',  'name' => 'Flat-pack Packing',    'description' => 'Part sets checked, protected and cartoned'],
            ['code' => 'DISP',  'name' => 'Dispatch',             'description' => 'Pallet build, wrapping and load marshalling'],
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
            ['line' => 'CUT',   'code' => 'SAW-BEAM-01', 'name' => 'Beam Saw #1 (front-loading, 3.2 m)',   'workstation_type' => 'panel_saw'],
            ['line' => 'CUT',   'code' => 'SAW-BEAM-02', 'name' => 'Beam Saw #2 (rear-loading, angular)',  'workstation_type' => 'panel_saw'],
            ['line' => 'CUT',   'code' => 'NEST-01',     'name' => 'Nesting CNC Router (5×10 bed)',        'workstation_type' => 'nesting_router'],
            ['line' => 'CUT',   'code' => 'LABEL-01',    'name' => 'Part Labelling & Sorting Bench',       'workstation_type' => 'part_labeling'],

            ['line' => 'EDGE',  'code' => 'EDGE-01',     'name' => 'Single-sided Edgebander #1 (EVA)',     'workstation_type' => 'edgebander'],
            ['line' => 'EDGE',  'code' => 'EDGE-02',     'name' => 'Single-sided Edgebander #2 (PUR)',     'workstation_type' => 'edgebander'],
            ['line' => 'EDGE',  'code' => 'EDGE-03',     'name' => 'Double-sided Throughfeed Edgebander',  'workstation_type' => 'edgebander_double'],
            ['line' => 'EDGE',  'code' => 'SOFT-01',     'name' => 'Softforming / Postforming Line',       'workstation_type' => 'postformer'],

            ['line' => 'DRILL', 'code' => 'CNC-BORE-01', 'name' => 'CNC Point-to-Point Boring #1',         'workstation_type' => 'cnc_boring'],
            ['line' => 'DRILL', 'code' => 'CNC-BORE-02', 'name' => 'CNC Point-to-Point Boring #2',         'workstation_type' => 'cnc_boring'],
            ['line' => 'DRILL', 'code' => 'DOWEL-01',    'name' => 'Through-feed Drilling & Dowel Inserter', 'workstation_type' => 'dowel_inserter'],
            ['line' => 'DRILL', 'code' => 'HINGE-01',    'name' => 'Hinge Boring & Insertion Machine',     'workstation_type' => 'hinge_borer'],

            ['line' => 'SURF',  'code' => 'MEMB-01',     'name' => 'Membrane Vacuum Press',                'workstation_type' => 'membrane_press'],
            ['line' => 'SURF',  'code' => 'COAT-01',     'name' => 'UV Roller Coater & Lamp Tunnel',       'workstation_type' => 'uv_coater'],
            ['line' => 'SURF',  'code' => 'WRAP-01',     'name' => 'Profile Wrapping Machine',             'workstation_type' => 'profile_wrapper'],

            ['line' => 'ASSY',  'code' => 'DRAWER-01',   'name' => 'Drawer Box Assembly Cell',             'workstation_type' => 'drawer_assembly'],
            ['line' => 'ASSY',  'code' => 'CASE-01',     'name' => 'Carcase Clamp & Squaring Press',       'workstation_type' => 'case_clamp'],
            ['line' => 'ASSY',  'code' => 'FIT-01',      'name' => 'Fittings Kitting Bench',               'workstation_type' => 'kitting'],

            ['line' => 'PACK',  'code' => 'PACK-01',     'name' => 'Carton Packing Line',                  'workstation_type' => 'flatpack_packing'],
            ['line' => 'DISP',  'code' => 'PALL-01',     'name' => 'Pallet Build & Wrapping',              'workstation_type' => 'palletising'],
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
            ['code' => 'WARD_2D',      'name' => 'Wardrobe 2-Door 1800×1000', 'description' => 'Hinged double wardrobe, white MFC carcase and doors, hanging rail and top shelf', 'unit_of_measure' => 'pcs'],
            ['code' => 'WARD_SLIDE',   'name' => 'Sliding Wardrobe 2400×1600', 'description' => 'Two-track sliding wardrobe in oak decor, grain-matched doors',                   'unit_of_measure' => 'pcs'],
            ['code' => 'CHEST_4D',     'name' => 'Chest of Drawers, 4 Drawer', 'description' => 'Four soft-close drawers on 500 mm runners, gloss fronts',                        'unit_of_measure' => 'pcs'],
            ['code' => 'DESK_1600',    'name' => 'Office Desk 1600×800',      'description' => 'Desk top with postformed front edge and cable port, single-drawer pedestal',     'unit_of_measure' => 'pcs'],
            ['code' => 'KIT_BASE_600', 'name' => 'Kitchen Base Unit 600 mm',  'description' => 'Moisture-resistant 600×720 base carcase, one drawer, one gloss door',            'unit_of_measure' => 'pcs'],
            ['code' => 'KIT_WALL_800', 'name' => 'Kitchen Wall Unit 800 mm',  'description' => '800×720 wall carcase, two gloss doors, adjustable shelf',                        'unit_of_measure' => 'pcs'],
            ['code' => 'BOOK_5S',      'name' => 'Bookcase 5 Shelf 1800×800', 'description' => 'Tall bookcase, three adjustable shelves on 32 mm system holes, wall anchor',     'unit_of_measure' => 'pcs'],
            ['code' => 'TVUNIT_1400',  'name' => 'TV Unit 1400',              'description' => 'Low unit built around the standard 600 carcase, one drawer and an open shelf',   'unit_of_measure' => 'pcs'],
            // Sub-assemblies. Each is produced in its own right before it becomes
            // part of something else, so each needs a product type to hang a
            // routing on — and that routing is what lets the BOM descend.
            ['code' => 'SA_BLANK_18',  'name' => 'Cut Panel Blank 18 mm',     'description' => 'Rectangular blank cut from an 18 mm sheet on the beam saw, labelled and stacked', 'unit_of_measure' => 'pcs'],
            ['code' => 'SA_EDGED_18',  'name' => 'Edged Panel 18 mm',         'description' => 'Blank with 2 mm ABS tape applied, trimmed and scraped',                          'unit_of_measure' => 'pcs'],
            ['code' => 'SA_CARCASE',   'name' => 'Carcase Set 600×720',       'description' => 'Five edged panels plus back, bored and dowel-inserted as a matched set',         'unit_of_measure' => 'set'],
            ['code' => 'SA_DRAWER',    'name' => 'Drawer Box 500 mm',         'description' => 'Dowelled four-sided box with HDF base and a runner pair fitted',                 'unit_of_measure' => 'pcs'],
            ['code' => 'SA_FRONT',     'name' => 'Gloss Front, Foil-wrapped', 'description' => 'Routed MDF front, membrane-pressed gloss foil, edge-lacquered',                  'unit_of_measure' => 'pcs'],
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

        $t['WARD_2D'] = $this->createTemplate($pt['WARD_2D'], 'Wardrobe 2-Door 1800×1000 — v3', [
            [1, 'Cutting plan check', 'Check the cutting plan decor batch against the order. Two batches of white in one wardrobe will be seen under a shop light, and it comes back.', 12, $ws['SAW-BEAM-01'] ?? null],
            [2, 'Part check & label', 'Check the labelled set is complete before it leaves the saw. A missing gable is found at the packing bench, which is the worst place to find it.', 10, $ws['LABEL-01'] ?? null],
            [3, 'Edge & trim',        'Band all four edges on the doors, two on the shelves. Feel the trimmed edge with a thumbnail — a ridge you can feel is glue, and it will show white.', 38, $ws['EDGE-03'] ?? null],
            [4, 'Bore & dowel',       'Bore the 32 mm system pattern from the top datum. Check the first panel against the jig; if the last hole is out, the whole batch is out.', 30, $ws['CNC-BORE-01'] ?? null],
            [5, 'Hinge boring',       'Bore the hinge cups and press the hinges in. Rock the plate — if it moves, the cup bottom is fluffy and the cutter is due.', 18, $ws['HINGE-01'] ?? null],
            [6, 'Kit fittings',       'Pick the fittings pack against the checklist. Count the cam bolts; do not judge by the weight of the bag.', 14, $ws['FIT-01'] ?? null],
            [7, 'Pack',               'Two cartons. Faced sides inward, corner protectors on the gables, and mark carton 1 of 2 on both.', 22, $ws['PACK-01'] ?? null],
        ]);

        $t['WARD_SLIDE'] = $this->createTemplate($pt['WARD_SLIDE'], 'Sliding Wardrobe 2400×1600 — v2', [
            [1, 'Cutting plan check', 'Oak decor runs directional. Check the grain arrow on the plan before the first cut — a panel cut across the grain is scrap, there is no rework.', 14, $ws['SAW-BEAM-02'] ?? null],
            [2, 'Edge & trim',        'Band with 0.8 mm oak tape on the PUR machine. Match the tape roll to the board batch and write both on the traveller.', 40, $ws['EDGE-02'] ?? null],
            [3, 'Bore & dowel',       'Bore and insert dowels. Keep the carcase panels together as a set — mixing sets across orders loses the batch match.', 32, $ws['DOWEL-01'] ?? null],
            [4, 'Track fitting',      'Fit and square the top and bottom track. Run a door on it before packing; if it drags anywhere, the track is twisted.', 26, $ws['CASE-01'] ?? null],
            [5, 'Kit fittings',       'Kit the track set, door wheels and the fixing pack. The wheel pack is the one that goes short.', 16, $ws['FIT-01'] ?? null],
            [6, 'Pack',               'Two cartons plus a separate track tube. Foam the door edges — a chipped sliding door edge is visible for the life of the wardrobe.', 28, $ws['PACK-01'] ?? null],
        ]);

        $t['CHEST_4D'] = $this->createTemplate($pt['CHEST_4D'], 'Chest of Drawers, 4 Drawer — v2', [
            [1, 'Carcase parts check', 'Check the five carcase panels are from one decor batch and square to the label dimensions.', 10, $ws['LABEL-01'] ?? null],
            [2, 'Bore & dowel',        'Bore the runner fixing pattern. Measure the first and the last panel in the stack — the pattern drifts, it does not jump.', 26, $ws['CNC-BORE-02'] ?? null],
            [3, 'Drawer boxes',        'Draw four boxes from stock and check each one is square by the diagonals before it goes near the carcase.', 20, $ws['DRAWER-01'] ?? null],
            [4, 'Front fitting',       'Hang the gloss fronts and set the gaps to 3 mm all round. Handle fronts by the edges; a fingerprint in the lacquer means stripping the front.', 30, $ws['CASE-01'] ?? null],
            [5, 'Function test',       'Run every drawer in and out five times. Listen for the soft-close to catch — if it slams, the runner is not clipped home.', 14, $ws['CASE-01'] ?? null],
            [6, 'Pack',                'Fronts wrapped in foam and packed face to face, never face to back. Drawer boxes flat with the runners bagged.', 24, $ws['PACK-01'] ?? null],
        ]);

        $t['DESK_1600'] = $this->createTemplate($pt['DESK_1600'], 'Office Desk 1600×800 — v2', [
            [1, 'Top selection',    'Pick the desk tops face-up and reject anything with a surface scratch. The top is the only part the customer looks at every day.', 10, $ws['LABEL-01'] ?? null],
            [2, 'Postform edge',    'Softform the front edge of the top. Watch the radius on the first one — a flat spot in the profile catches the light.', 22, $ws['SOFT-01'] ?? null],
            [3, 'Edge remaining',   'Band the gables and the modesty panel on the double-sided machine.', 18, $ws['EDGE-03'] ?? null],
            [4, 'Bore & cable port', 'Bore the connector pattern and rout the cable port. Deburr the port — a rough port cuts the installer.', 24, $ws['CNC-BORE-01'] ?? null],
            [5, 'Pedestal',         'Draw a drawer box and fit it to the pedestal. Check it runs before it is packed, not after.', 16, $ws['DRAWER-01'] ?? null],
            [6, 'Pack',             'Single carton, top on the bottom of the stack with corner protectors. Fixings bag taped inside the lid.', 18, $ws['PACK-01'] ?? null],
        ]);

        $t['KIT_BASE_600'] = $this->createTemplate($pt['KIT_BASE_600'], 'Kitchen Base Unit 600 mm — v4', [
            [1, 'Carcase set',   'Draw a 600 carcase set and confirm the plinth stock is moisture-resistant board. Standard board under a sink swells within a year and we replace the kitchen.', 10, $ws['CASE-01'] ?? null],
            [2, 'Drawer box',    'Fit the drawer box and runners. Check the box front is flush with the carcase before the front goes on.', 16, $ws['DRAWER-01'] ?? null],
            [3, 'Door hanging',  'Hang the gloss door on soft-close hinges and set the gap. Peel the protective film only at the final check, not before.', 18, $ws['HINGE-01'] ?? null],
            [4, 'Kit fittings',  'Kit the handle, screws, legs and plinth clips. The leg pack is counted in fours, not in bags.', 12, $ws['FIT-01'] ?? null],
            [5, 'Pack',          'Carton with the door packed face-in against foam. Mark the carton with the unit width — 600 and 500 cartons look identical on a pallet.', 16, $ws['PACK-01'] ?? null],
        ]);

        $t['KIT_WALL_800'] = $this->createTemplate($pt['KIT_WALL_800'], 'Kitchen Wall Unit 800 mm — v2', [
            [1, 'Panel check',      'Check the four carcase panels and the shelf. Wall units hang at head height, so a mis-drilled hole is visible from below.', 8, $ws['LABEL-01'] ?? null],
            [2, 'Bore & dowel',     'Bore the system holes and the hanging bracket pattern. The bracket pattern must be square to the top edge or the unit hangs crooked.', 22, $ws['DOWEL-01'] ?? null],
            [3, 'Hinge boring',     'Bore two pairs of cups. Check the cup depth on a scrap first — through the back of an 18 mm door is a scrapped door.', 16, $ws['HINGE-01'] ?? null],
            [4, 'Fronts & fittings', 'Draw two gloss doors and kit the hanging brackets and adjusters.', 14, $ws['FIT-01'] ?? null],
            [5, 'Pack',             'Carton flat with the doors face to face. Brackets bagged separately and taped inside.', 14, $ws['PACK-01'] ?? null],
        ]);

        $t['BOOK_5S'] = $this->createTemplate($pt['BOOK_5S'], 'Bookcase 5 Shelf 1800×800 — v1', [
            [1, 'Panel check',      'Check the gables are the same length. On a tall bookcase a 2 mm difference shows as a lean the customer can see across a room.', 8, $ws['LABEL-01'] ?? null],
            [2, 'Edge & trim',      'Band the front edges of the shelves and both long edges of the gables.', 24, $ws['EDGE-01'] ?? null],
            [3, 'System drilling',  'Drill the 32 mm shelf-support line both sides. Both gables must be drilled from the same datum end or the shelves sit at a slope.', 26, $ws['CNC-BORE-02'] ?? null],
            [4, 'Kit fittings',     'Kit the shelf supports, cams and the wall anchor strap. The strap is not optional on an 1800 unit.', 12, $ws['FIT-01'] ?? null],
            [5, 'Pack',             'Single long carton, shelves stacked in the middle, gables outside, corner protectors top and bottom.', 18, $ws['PACK-01'] ?? null],
        ]);

        $t['TVUNIT_1400'] = $this->createTemplate($pt['TVUNIT_1400'], 'TV Unit 1400 — v1', [
            [1, 'Carcase set',       'Draw a 600 carcase set for the centre bay and check the four open-shelf panels against the label.', 10, $ws['CASE-01'] ?? null],
            [2, 'Bore & dowel',      'Bore the open-shelf panels and dowel the outer frame.', 20, $ws['DOWEL-01'] ?? null],
            [3, 'Drawer & front',    'Fit the drawer box and hang the gloss front. Set the gap against the fixed shelf, not against the carcase.', 22, $ws['DRAWER-01'] ?? null],
            [4, 'Cable management',  'Rout the cable cut-out in the back panel and fit the grommet. Check the grommet is seated — a loose one rattles.', 12, $ws['CNC-BORE-01'] ?? null],
            [5, 'Pack',              'Single carton, back panel on top so it is not walked on.', 16, $ws['PACK-01'] ?? null],
        ]);

        // ── Sub-assembly routings ────────────────────────────────────────────

        $t['SA_BLANK_18'] = $this->createTemplate($pt['SA_BLANK_18'], 'Cut Panel Blank 18 mm — cutting v2', [
            [1, 'Load & optimise', 'Load the pack and confirm the board batch matches the plan. Check the sheet is flat — a bowed sheet lifts under the pressure beam and the cut walks.', 14, $ws['SAW-BEAM-01'] ?? null],
            [2, 'Cut to plan',     'Run the plan. Check the first book for chipping on the underside; if it is chipping there, the scorer is out, not the main blade.', 36, $ws['SAW-BEAM-01'] ?? null],
            [3, 'Label & stack',   'Label every part as it comes off and stack face to face. An unlabelled part becomes offcut within the hour.', 12, $ws['LABEL-01'] ?? null],
        ]);

        $t['SA_EDGED_18'] = $this->createTemplate($pt['SA_EDGED_18'], 'Edged Panel 18 mm — banding v3', [
            [1, 'Pre-mill & band', 'Set the pre-milling cutters to take 0.8 mm off each side. A wavy glue line means the pre-mill is blunt, not that the glue is cold.', 20, $ws['EDGE-03'] ?? null],
            [2, 'Trim & scrape',   'Flush trim and scrape. Look along the edge into the light — a white witness line means the trim knives are due.', 16, $ws['EDGE-03'] ?? null],
            [3, 'Buff & check',    'Buff off glue residue and test adhesion by trying to lift a tape end with a blade. If it peels, stop and check the pot temperature.', 10, $ws['EDGE-03'] ?? null],
        ]);

        $t['SA_CARCASE'] = $this->createTemplate($pt['SA_CARCASE'], 'Carcase Set 600×720 — boring v2', [
            [1, 'Panel set',       'Pull five edged panels from one decor batch. Once they are bored they are a set and cannot be mixed with another order.', 8, $ws['CNC-BORE-01'] ?? null],
            [2, 'System boring',   'Bore the 32 mm line and the connector holes from the top datum on every panel. Measure the first and the twentieth — the head drifts as it warms.', 26, $ws['CNC-BORE-01'] ?? null],
            [3, 'Dowel insertion', 'Insert and glue the dowels. A dowel standing proud will not let the joint close, and the carcase comes out of square.', 18, $ws['DOWEL-01'] ?? null],
            [4, 'Cam housing',     'Rout the cam housings and fit the bolts. Check bolt depth on the first panel with a gauge.', 14, $ws['CNC-BORE-02'] ?? null],
        ]);

        $t['SA_DRAWER'] = $this->createTemplate($pt['SA_DRAWER'], 'Drawer Box 500 mm — assembly v2', [
            [1, 'Panel set',    'Pull four edged panels — two sides, front and back — and the HDF base.', 6, $ws['DRAWER-01'] ?? null],
            [2, 'Dowel & glue', 'Dowel and glue the four sides. Work quickly; once the glue skins, the joint will never close fully.', 14, $ws['DRAWER-01'] ?? null],
            [3, 'Square & base', 'Clamp square and slot the base in. Measure both diagonals before the clamp comes off — out of square here is a drawer that binds in the carcase.', 12, $ws['DRAWER-01'] ?? null],
            [4, 'Runner fit',   'Fit the runner pair and cycle the box five times. A box that needs a shove is not a box that passes.', 10, $ws['DRAWER-01'] ?? null],
        ]);

        $t['SA_FRONT'] = $this->createTemplate($pt['SA_FRONT'], 'Gloss Front, Foil-wrapped — v3', [
            [1, 'Rout profile',  'Rout the front profile and sand the routed edge by hand. Foil shows every tool mark underneath it, it does not hide them.', 20, $ws['NEST-01'] ?? null],
            [2, 'Prime & dry',   'Roller-prime the face and the routed edge. Any dust settling now becomes a pimple under the gloss.', 16, $ws['COAT-01'] ?? null],
            [3, 'Membrane press', 'Press at 110 °C with full vacuum. Check the membrane for pinholes before the run — a pinhole leaves an unbonded patch at the profile corner.', 24, $ws['MEMB-01'] ?? null],
            [4, 'Trim & lacquer', 'Trim the foil overlap on the back and lacquer the raw edge. Handle by the back only from here on.', 18, $ws['COAT-01'] ?? null],
            [5, 'Film & rack',   'Apply protective film and rack vertically, faces not touching. Never stack gloss fronts flat.', 10, $ws['WRAP-01'] ?? null],
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
            // Match live rows only: template_steps is soft-deletable and
            // updateOrInsert() runs without the model's scope, so leaving
            // deleted_at out would resurrect a step the user had deleted.
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
            ['username' => 'daniel.fenwick'],
            [
                'name' => 'Daniel Fenwick',
                'email' => 'daniel.fenwick@ashcroftpanel.local',
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
            ['username' => 'owen.carver',   'name' => 'Owen Carver',   'email' => 'owen.carver@ashcroftpanel.local',   'lines' => ['CUT']],
            ['username' => 'priya.shah',    'name' => 'Priya Shah',    'email' => 'priya.shah@ashcroftpanel.local',    'lines' => ['EDGE']],
            ['username' => 'martin.doyle',  'name' => 'Martin Doyle',  'email' => 'martin.doyle@ashcroftpanel.local',  'lines' => ['DRILL']],
            ['username' => 'elise.hartley', 'name' => 'Elise Hartley', 'email' => 'elise.hartley@ashcroftpanel.local', 'lines' => ['SURF']],
            ['username' => 'sam.whitlock',  'name' => 'Sam Whitlock',  'email' => 'sam.whitlock@ashcroftpanel.local',  'lines' => ['ASSY', 'PACK', 'DISP']],
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
            ['code' => 'CUST-HABITAS',   'name' => 'Habitas Retail Group',      'tier' => Tier::Vip,    'payment_score' => 94, 'notes' => 'Forty stores. Orders arrive by EDI overnight and are expected on the board by 07:00. Rejects anything with a decor batch split.'],
            ['code' => 'CUST-KITCHWORLD', 'name' => 'KitchenWorld Studios',     'tier' => Tier::Gold,   'payment_score' => 88, 'notes' => 'Showroom chain. Kitchens ship as a full plot list, so one missing wall unit holds the whole delivery.'],
            ['code' => 'CUST-MERIDIAN',  'name' => 'Meridian Homes (Contracts)', 'tier' => Tier::Gold,  'payment_score' => 71, 'notes' => 'Housebuilder. Fitted wardrobes by plot number, delivered to site in build sequence. Pays on certified valuation — slow but certain.'],
            ['code' => 'CUST-BOXED',     'name' => 'BoxedLiving Online',        'tier' => Tier::Silver, 'payment_score' => 63, 'notes' => 'Pure e-commerce, single cartons to consumers. Carton quality matters more than anything inside it.'],
            ['code' => 'CUST-CLEARSPAN', 'name' => 'Clearspan Office Fit-Out',  'tier' => Tier::Silver, 'payment_score' => 52, 'notes' => 'Desks and storage for office refits. Orders in bursts, pays in its own time.'],
            ['code' => 'CUST-TRADECTR',  'name' => 'Trade Counter (own)',       'tier' => Tier::Bronze, 'payment_score' => 44, 'notes' => 'Our own trade counter. Takes the overruns and the B-grade, so the order moves week to week.'],
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

    /** @param array<int, Customer> $customers */
    private function assignCustomers(array $customers): void
    {
        if ($customers === []) {
            return;
        }

        foreach (WorkOrder::orderBy('order_no')->get()->values() as $i => $order) {
            $customer = $customers[$i % count($customers)];

            $order->forceFill([
                'customer_id' => $customer->id,
                'customer_order_no' => sprintf('PO-%s-%04d', now()->year, 5000 + $i),
            ])->saveQuietly();
        }
    }

    // ── Work orders ──────────────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        // Fixed seed: the generated half of the board picks lines, products and
        // quantities at random, and without pinning the sequence a re-run
        // reshuffles them — which also changes how many multi-line segments come
        // out, since an order's line decides whether it hands off.
        mt_srand(20260404);

        $orders = [
            [
                'order_no' => 'WO-PN-0001',
                'line_id' => $lines['CUT']->id,
                'product_type_id' => $pt['WARD_2D']->id,
                'planned_qty' => 60,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 5,
                'due_date' => now()->addDays(2)->setTime(14, 0),
                'planned_start_at' => now()->setTime(6, 0),
                'planned_end_at' => now()->addDay()->setTime(14, 0),
                'description' => 'Wardrobes — Habitas EDI order, single decor batch required.',
            ],
            [
                'order_no' => 'WO-PN-0002',
                'line_id' => $lines['DRILL']->id,
                'product_type_id' => $pt['KIT_BASE_600']->id,
                'planned_qty' => 120,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 5,
                'due_date' => now()->addDay()->setTime(14, 0),
                'planned_start_at' => now()->setTime(14, 0),
                'planned_end_at' => now()->addDay()->setTime(22, 0),
                'description' => 'Kitchen base units — KitchenWorld plot list, ships complete or not at all.',
            ],
            [
                'order_no' => 'WO-PN-0003',
                'line_id' => $lines['ASSY']->id,
                'product_type_id' => $pt['CHEST_4D']->id,
                'planned_qty' => 45,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 4,
                'due_date' => now()->addDays(4)->setTime(14, 0),
                'planned_start_at' => now()->addDays(2)->setTime(6, 0),
                'planned_end_at' => now()->addDays(3)->setTime(14, 0),
                'description' => 'Chests of drawers — gloss fronts, soft-close throughout.',
            ],
            [
                'order_no' => 'WO-PN-0004',
                'line_id' => $lines['CUT']->id,
                'product_type_id' => $pt['WARD_SLIDE']->id,
                'planned_qty' => 30,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 4,
                'due_date' => now()->addDays(6)->setTime(14, 0),
                'planned_start_at' => now()->addDays(3)->setTime(6, 0),
                'planned_end_at' => now()->addDays(5)->setTime(14, 0),
                'description' => 'Sliding wardrobes — oak decor, grain direction marked on the plan.',
            ],
            [
                'order_no' => 'WO-PN-0005',
                'line_id' => $lines['EDGE']->id,
                'product_type_id' => $pt['BOOK_5S']->id,
                'planned_qty' => 90,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(8)->setTime(14, 0),
                'planned_start_at' => now()->addDays(5)->setTime(14, 0),
                'planned_end_at' => now()->addDays(7)->setTime(22, 0),
                'description' => 'Bookcases — trade counter stock build.',
            ],
            [
                'order_no' => 'WO-PN-0006',
                'line_id' => $lines['DRILL']->id,
                'product_type_id' => $pt['DESK_1600']->id,
                'planned_qty' => 70,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => now()->addDays(10)->setTime(14, 0),
                'planned_start_at' => now()->addDays(7)->setTime(6, 0),
                'planned_end_at' => now()->addDays(9)->setTime(14, 0),
                'description' => 'Office desks — Clearspan fit-out, postformed front edge.',
            ],
            [
                'order_no' => 'WO-PN-0007',
                'line_id' => $lines['ASSY']->id,
                'product_type_id' => $pt['TVUNIT_1400']->id,
                'planned_qty' => 40,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(12)->setTime(14, 0),
                'planned_start_at' => now()->addDays(9)->setTime(6, 0),
                'planned_end_at' => now()->addDays(11)->setTime(14, 0),
                'description' => 'TV units — shares the 600 carcase set with the kitchen base.',
            ],
            [
                'order_no' => 'WO-PN-0008',
                'line_id' => $lines['CUT']->id,
                'product_type_id' => $pt['WARD_2D']->id,
                'planned_qty' => 50,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 4,
                'due_date' => now()->subDays(3)->setTime(14, 0),
                'planned_start_at' => now()->subDays(5)->setTime(6, 0),
                'planned_end_at' => now()->subDays(3)->setTime(14, 0),
                'completed_at' => now()->subDays(3)->setTime(13, 20),
                'description' => 'Wardrobes — previous batch, delivered complete.',
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

        $allLines = array_values($lines);
        $allPt = array_values(array_intersect_key($pt, array_flip([
            'WARD_2D', 'WARD_SLIDE', 'CHEST_4D', 'DESK_1600',
            'KIT_BASE_600', 'KIT_WALL_800', 'BOOK_5S', 'TVUNIT_1400',
        ])));

        $descriptions = [
            'Retail replenishment — standing weekly order',
            'Kitchen plot list, ships complete',
            'Contract order — delivered to site in build sequence',
            'E-commerce batch, single cartons',
            'Office fit-out, phased delivery',
            'Trade counter stock build',
            'Replacement for a transit-damaged delivery',
            'New decor trial — first production run',
            'Showroom display units',
            'Carried over from last week, part shipped',
        ];

        // A double day shift, Monday to Friday, plus a Saturday morning on
        // cutting and banding only. The beam saw is the bottleneck and
        // everything downstream is fed from its stack, so the plant buys
        // capacity by running the front of the shop a shift longer.
        $shiftHours = [6, 14];
        $saturdayLines = ['CUT', 'EDGE'];

        $weekFill = [-1 => 0.40, 0 => 0.88, 1 => 0.62, 2 => 0.48, 3 => 0.34, 4 => 0.24];

        $weekStart = now()->startOfWeek();
        $n = 100;

        foreach ($weekFill as $weekOffset => $fill) {
            foreach ($allLines as $line) {
                for ($d = 0; $d < 6; $d++) {   // Mon-Sat; no Sunday working
                    foreach ($shiftHours as $hour) {
                        // Saturday is the cutting shift only, and mornings only.
                        if ($d === 5 && (! in_array($line->code, $saturdayLines, true) || $hour !== 6)) {
                            continue;
                        }
                        if (mt_rand(1, 100) > (int) round($fill * 100)) {
                            continue;
                        }

                        $n++;
                        $start = $weekStart->copy()->addWeeks($weekOffset)->addDays($d)->setTime($hour, 0);
                        $end = $start->copy()->addHours(8);
                        // Panel furniture runs in tens and low hundreds.
                        $qty = mt_rand(2, 30) * 10;

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
                            ['order_no' => sprintf('WO-PN-%04d', $n)],
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
     * A panel travels the length of the shop in one order — cut, banded, bored,
     * assembled, packed, loaded — so an order genuinely sits on several lines.
     * The planner draws that as a badge plus a connector to the extra segment.
     *
     * @param  array<string, Line>  $lines
     */
    private function seedMultiLinePlacements(array $lines): void
    {
        $handoffs = [
            'CUT' => [['EDGE', 1]],
            'EDGE' => [['DRILL', 1]],
            'DRILL' => [['ASSY', 1], ['PACK', 2]],
            'SURF' => [['ASSY', 1]],
            'PACK' => [['DISP', 1]],
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
        $monToFri = [1, 2, 3, 4, 5];
        $saturday = [6];

        $defs = [
            [
                'name' => 'Early Shift', 'code' => 'SE',
                'start_time' => '06:00', 'end_time' => '14:00',
                'days_of_week' => $monToFri,
                'line_codes' => ['CUT', 'EDGE', 'DRILL', 'SURF', 'ASSY', 'PACK', 'DISP'],
                'sort_order' => 1,
            ],
            [
                // No SURF on lates: the finishing room is cleaned down and the
                // lacquer lamps warmed once a day, and gloss work with a
                // skeleton crew is how fingerprints get into the lacquer.
                'name' => 'Late Shift', 'code' => 'SL',
                'start_time' => '14:00', 'end_time' => '22:00',
                'days_of_week' => $monToFri,
                'line_codes' => ['CUT', 'EDGE', 'DRILL', 'ASSY', 'PACK'],
                'sort_order' => 2,
            ],
            [
                // The bottleneck buys itself a head start for Monday.
                'name' => 'Saturday Cutting', 'code' => 'SS',
                'start_time' => '06:00', 'end_time' => '14:00',
                'days_of_week' => $saturday,
                'line_codes' => ['CUT', 'EDGE'],
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
            // Sheet stock — bought by the pack, moved by vacuum lifter.
            ['code' => 'MAT-MFC-18-W',    'name' => 'Melamine-faced chipboard 18 mm, white', 'type' => 'raw_material', 'unit' => 'm2',  'stock' => 3200,  'price' => 9.80,  'supplier' => 'Kronoline Boards'],
            ['code' => 'MAT-MFC-18-OAK',  'name' => 'Melamine-faced chipboard 18 mm, oak decor', 'type' => 'raw_material', 'unit' => 'm2', 'stock' => 1450, 'price' => 12.40, 'supplier' => 'Kronoline Boards'],
            ['code' => 'MAT-MDF-18',      'name' => 'MDF 18 mm, standard, sanded',           'type' => 'raw_material', 'unit' => 'm2',  'stock' => 900,   'price' => 11.60, 'supplier' => 'Westmoor Panels'],
            ['code' => 'MAT-MDF-18-MR',   'name' => 'MDF 18 mm, moisture-resistant',         'type' => 'raw_material', 'unit' => 'm2',  'stock' => 520,   'price' => 14.90, 'supplier' => 'Westmoor Panels'],
            ['code' => 'MAT-HDF-3',       'name' => 'HDF backing board 3 mm, white one side', 'type' => 'raw_material', 'unit' => 'm2', 'stock' => 2100,  'price' => 3.20,  'supplier' => 'Westmoor Panels'],

            // Edge tape and foil.
            ['code' => 'MAT-EDGE-ABS-2',  'name' => 'ABS edge tape 2 mm × 22 mm, white',     'type' => 'raw_material', 'unit' => 'm',   'stock' => 9600,  'price' => 0.34,  'supplier' => 'Edgeline Trims'],
            ['code' => 'MAT-EDGE-ABS-08', 'name' => 'ABS edge tape 0.8 mm × 22 mm, oak decor', 'type' => 'raw_material', 'unit' => 'm', 'stock' => 6400,  'price' => 0.29,  'supplier' => 'Edgeline Trims'],
            ['code' => 'MAT-FOIL-GLOSS',  'name' => 'PVC high-gloss wrapping foil, white',   'type' => 'raw_material', 'unit' => 'm2',  'stock' => 1800,  'price' => 6.40,  'supplier' => 'Decorfoil Supplies'],

            // Fittings.
            ['code' => 'MAT-DOWEL-8',     'name' => 'Beech dowel 8 × 35 mm, fluted',         'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 82000, 'price' => 0.02,  'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-CAM-15',      'name' => 'Cam lock and bolt set, 15 mm',          'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 26000, 'price' => 0.16,  'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-HINGE-CLIP',  'name' => 'Soft-close clip-on hinge 110° with plate', 'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 8400, 'price' => 1.95, 'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-RUNNER-500',  'name' => 'Soft-close drawer runner pair, 500 mm', 'type' => 'raw_material', 'unit' => 'set', 'stock' => 3100,  'price' => 6.80,  'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-TRACK-SLIDE', 'name' => 'Sliding door track set, 1600 mm',       'type' => 'raw_material', 'unit' => 'set', 'stock' => 640,   'price' => 38.50, 'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-HANDLE-BAR',  'name' => 'Bar handle 160 mm, brushed steel',      'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 5200,  'price' => 2.40,  'supplier' => 'Fastfix Fittings'],
            ['code' => 'MAT-SCREW-KIT',   'name' => 'Screw, shelf-support and fixings pack', 'type' => 'raw_material', 'unit' => 'pcs', 'stock' => 7400,  'price' => 0.85,  'supplier' => 'Fastfix Fittings'],

            // Adhesives and lacquer.
            ['code' => 'MAT-GLUE-EVA',    'name' => 'EVA hot-melt granulate, edgebanding',   'type' => 'auxiliary',    'unit' => 'kg',  'stock' => 420,   'price' => 5.60,  'supplier' => 'Adhesa Chemicals'],
            ['code' => 'MAT-GLUE-PUR',    'name' => 'PUR hot-melt granulate, gloss and high-humidity', 'type' => 'auxiliary', 'unit' => 'kg', 'stock' => 180, 'price' => 14.20, 'supplier' => 'Adhesa Chemicals'],
            ['code' => 'MAT-LACQ-UV',     'name' => 'UV roller lacquer, matt',               'type' => 'auxiliary',    'unit' => 'kg',  'stock' => 140,   'price' => 21.50, 'supplier' => 'Adhesa Chemicals'],

            // Packaging.
            ['code' => 'MAT-CARTON-FP',   'name' => 'Flat-pack carton, printed, double-wall', 'type' => 'packaging',   'unit' => 'pcs', 'stock' => 3200,  'price' => 2.10,  'supplier' => 'Boxwell Packaging'],
            ['code' => 'MAT-CORNER-PRO',  'name' => 'Cardboard corner protector',            'type' => 'packaging',    'unit' => 'pcs', 'stock' => 14000, 'price' => 0.18,  'supplier' => 'Boxwell Packaging'],
            ['code' => 'MAT-FILM-STRETCH', 'name' => 'Pallet stretch wrap film',             'type' => 'packaging',    'unit' => 'kg',  'stock' => 260,   'price' => 4.30,  'supplier' => 'Boxwell Packaging'],
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

        // The made parts. Stock is deliberately uneven: the saw runs ahead so
        // blanks are never the constraint, while the drawer box is held short
        // — and it is pulled by four different products, so the shortage only
        // shows once their demand is summed.
        $subAssemblies = [
            ['code' => 'SA-BLANK-18',  'template' => 'SA_BLANK_18', 'name' => 'Cut Panel Blank 18 mm',    'unit' => 'pcs', 'stock' => 1400],
            ['code' => 'SA-EDGED-18',  'template' => 'SA_EDGED_18', 'name' => 'Edged Panel 18 mm',        'unit' => 'pcs', 'stock' => 320],
            ['code' => 'SA-CARCASE',   'template' => 'SA_CARCASE',  'name' => 'Carcase Set 600×720',      'unit' => 'set', 'stock' => 40],
            ['code' => 'SA-DRAWER',    'template' => 'SA_DRAWER',   'name' => 'Drawer Box 500 mm',        'unit' => 'pcs', 'stock' => 26],
            ['code' => 'SA-FRONT',     'template' => 'SA_FRONT',    'name' => 'Gloss Front, Foil-wrapped', 'unit' => 'pcs', 'stock' => 95],
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
                    'unit_of_measure' => $def['unit'],
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
     * What each product is built from, and what the made parts are built from
     * in turn.
     *
     * Four manufactured levels above the purchased sheet:
     *   kitchen base → carcase set → edged panel → blank → board.
     *
     * Three nodes have more than one parent, which is the whole point. The
     * edged panel is pulled by six, the drawer box by four and the carcase set
     * by two, so netting has to sum parents before it knows what to build.
     *
     * Scrap sits mostly on the made parts rather than the products: the offcut
     * is lost in the cutting plan and the tape start and stop are lost at the
     * bander, and neither is a defect anybody caused.
     *
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<string, Material>  $materials
     */
    private function seedBom(array $templates, array $materials): void
    {
        $defs = [
            'WARD_2D' => [
                [3, 'SA-EDGED-18',    8,    2, 'start'],
                [3, 'MAT-HDF-3',      1.90, 8, 'start'],
                [4, 'MAT-DOWEL-8',   32,    3, 'during'],
                [4, 'MAT-CAM-15',    16,    2, 'during'],
                [5, 'MAT-HINGE-CLIP', 4,    1, 'during'],
                [6, 'MAT-HANDLE-BAR', 2,    1, 'during'],
                [6, 'MAT-SCREW-KIT',  1,    0, 'during'],
                [7, 'MAT-CARTON-FP',  2,    2, 'end'],
                [7, 'MAT-CORNER-PRO', 8,    5, 'end'],
            ],
            // Oak runs its own flow: different board, different tape and PUR
            // glue, so it does not draw on the white edged-panel stock.
            'WARD_SLIDE' => [
                [1, 'MAT-MFC-18-OAK',   4.20,  7, 'start'],
                [1, 'MAT-HDF-3',        2.60,  8, 'start'],
                [2, 'MAT-EDGE-ABS-08', 26.00,  8, 'during'],
                [2, 'MAT-GLUE-PUR',     0.09,  8, 'during'],
                [3, 'MAT-DOWEL-8',     36,     3, 'during'],
                [3, 'MAT-CAM-15',      20,     2, 'during'],
                [4, 'MAT-TRACK-SLIDE',  1,     1, 'during'],
                [5, 'MAT-SCREW-KIT',    1,     0, 'during'],
                [6, 'MAT-CARTON-FP',    2,     2, 'end'],
                [6, 'MAT-CORNER-PRO',  10,     5, 'end'],
            ],
            'CHEST_4D' => [
                [1, 'SA-EDGED-18',    5,    2, 'start'],
                [1, 'MAT-HDF-3',      0.62, 8, 'start'],
                [2, 'MAT-DOWEL-8',   24,    3, 'during'],
                [2, 'MAT-CAM-15',    12,    2, 'during'],
                [3, 'SA-DRAWER',      4,    1, 'during'],
                [4, 'SA-FRONT',       4,    2, 'during'],
                [4, 'MAT-HANDLE-BAR', 4,    1, 'during'],
                [6, 'MAT-CARTON-FP',  1,    2, 'end'],
                [6, 'MAT-CORNER-PRO', 6,    5, 'end'],
            ],
            'DESK_1600' => [
                [1, 'SA-EDGED-18',    4,    2, 'start'],
                [4, 'MAT-DOWEL-8',   12,    3, 'during'],
                [4, 'MAT-CAM-15',     8,    2, 'during'],
                [5, 'SA-DRAWER',      1,    1, 'during'],
                [5, 'MAT-HANDLE-BAR', 1,    1, 'during'],
                [6, 'MAT-CARTON-FP',  1,    2, 'end'],
                [6, 'MAT-CORNER-PRO', 4,    5, 'end'],
            ],
            'KIT_BASE_600' => [
                [1, 'SA-CARCASE',     1,    1, 'start'],
                [1, 'MAT-MDF-18-MR',  0.16, 6, 'start'],
                [2, 'SA-DRAWER',      1,    1, 'during'],
                [3, 'SA-FRONT',       1,    2, 'during'],
                [3, 'MAT-HINGE-CLIP', 2,    1, 'during'],
                [4, 'MAT-HANDLE-BAR', 1,    1, 'during'],
                [4, 'MAT-SCREW-KIT',  1,    0, 'during'],
                [5, 'MAT-CARTON-FP',  1,    2, 'end'],
                [5, 'MAT-CORNER-PRO', 4,    5, 'end'],
            ],
            'KIT_WALL_800' => [
                [1, 'SA-EDGED-18',    4,    2, 'start'],
                [1, 'MAT-HDF-3',      0.55, 8, 'start'],
                [2, 'MAT-DOWEL-8',   16,    3, 'during'],
                [2, 'MAT-CAM-15',     8,    2, 'during'],
                [3, 'MAT-HINGE-CLIP', 4,    1, 'during'],
                [4, 'SA-FRONT',       2,    2, 'during'],
                [5, 'MAT-CARTON-FP',  1,    2, 'end'],
                [5, 'MAT-CORNER-PRO', 4,    5, 'end'],
            ],
            'BOOK_5S' => [
                [1, 'SA-EDGED-18',    7,    2, 'start'],
                [1, 'MAT-HDF-3',      1.10, 8, 'start'],
                [3, 'MAT-DOWEL-8',   20,    3, 'during'],
                [3, 'MAT-CAM-15',     8,    2, 'during'],
                [4, 'MAT-SCREW-KIT',  1,    0, 'during'],
                [5, 'MAT-CARTON-FP',  1,    2, 'end'],
                [5, 'MAT-CORNER-PRO', 4,    5, 'end'],
            ],
            'TVUNIT_1400' => [
                [1, 'SA-CARCASE',     1,    1, 'start'],
                [1, 'SA-EDGED-18',    4,    2, 'start'],
                [1, 'MAT-HDF-3',      0.70, 8, 'start'],
                [2, 'MAT-DOWEL-8',   20,    3, 'during'],
                [2, 'MAT-CAM-15',    10,    2, 'during'],
                [3, 'SA-DRAWER',      1,    1, 'during'],
                [3, 'SA-FRONT',       1,    2, 'during'],
                [5, 'MAT-CARTON-FP',  1,    2, 'end'],
                [5, 'MAT-CORNER-PRO', 4,    5, 'end'],
            ],

            // ── Made parts, deepest last ─────────────────────────────────────
            'SA_CARCASE' => [
                [1, 'SA-EDGED-18',  5,    2, 'start'],
                [1, 'MAT-HDF-3',    0.45, 8, 'start'],
                [3, 'MAT-DOWEL-8', 24,    3, 'during'],
                [4, 'MAT-CAM-15',   8,    2, 'during'],
            ],
            'SA_DRAWER' => [
                [1, 'SA-EDGED-18',    4,    2, 'start'],
                [1, 'MAT-HDF-3',      0.16, 6, 'start'],
                [2, 'MAT-DOWEL-8',   16,    3, 'during'],
                [4, 'MAT-RUNNER-500', 1,    1, 'end'],
            ],
            'SA_FRONT' => [
                [1, 'MAT-MDF-18',     0.34,  9, 'start'],
                [3, 'MAT-FOIL-GLOSS', 0.44, 12, 'during'],
                [3, 'MAT-GLUE-PUR',   0.11,  8, 'during'],
                [4, 'MAT-LACQ-UV',    0.04,  5, 'during'],
            ],
            'SA_EDGED_18' => [
                [1, 'SA-BLANK-18',    1,     2, 'start'],
                [1, 'MAT-EDGE-ABS-2', 2.60,  7, 'during'],
                [1, 'MAT-GLUE-EVA',   0.018, 10, 'during'],
            ],
            'SA_BLANK_18' => [
                // Cutting-plan waste — the offcut, not a defect.
                [2, 'MAT-MFC-18-W', 0.42, 6, 'start'],
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

    // ── Product revisions ────────────────────────────────────────────────────

    /**
     * Specification revisions.
     *
     * A panel-furniture maker versions hardware specification, board thickness
     * and grade, edge tape thickness, the drilling pattern and how many cartons
     * the thing ships in. Each of those is a different product on the bench,
     * and most of the changes here were forced by something coming back.
     *
     * @param  array<string, ProductType>  $pt
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<int, User>  $users
     */
    private function seedProductRevisions(array $pt, array $templates, array $users): void
    {
        $engineer = collect($users)->first();

        $defs = [
            ['WARD_2D', 'A', RevisionLifecycle::Obsolete, '15 mm board, 0.8 mm edge tape, nailed HDF back',
                'Superseded by rev B. The 15 mm gables flexed on a filled wardrobe and the back nails pulled through.', 640],
            ['WARD_2D', 'B', RevisionLifecycle::Obsolete, '18 mm board, 0.8 mm tape, grooved HDF back',
                'Superseded by rev C. Thin tape chipped on the door edges in transit — every return was edge damage.', 300],
            ['WARD_2D', 'C', RevisionLifecycle::Released, '18 mm board, 2 mm ABS tape, grooved back, two-carton pack',
                'Thicker tape on the doors and a split into two cartons. Damage claims fell by two thirds, and the single carton was over the carrier weight limit anyway.', 110],

            ['KIT_BASE_600', 'C', RevisionLifecycle::Obsolete, 'Standard MDF carcase, cam-and-dowel',
                'Superseded by rev D. Standard board under a sink swelled within a year and we replaced two kitchens.', 420],
            ['KIT_BASE_600', 'D', RevisionLifecycle::Released, 'Moisture-resistant plinth, soft-close hinges and runners as standard',
                'MR board where it gets wet, and soft-close made standard rather than an upgrade. Warranty claims on swelling stopped.', 95],

            ['KIT_WALL_800', 'B', RevisionLifecycle::Released, '32 mm system drilling, adjustable hanging brackets',
                'Moved to the standard 32 mm pattern so the wall unit shares a program with the base unit. One setup instead of two.', 180],

            ['CHEST_4D', 'B', RevisionLifecycle::Released, 'Gloss foil fronts, 500 mm soft-close runners',
                'Ball-bearing runners replaced by soft-close after showroom feedback — customers open a drawer before they look at anything else.', 200],
            ['CHEST_4D', 'C', RevisionLifecycle::Draft, 'Trial: dowel-and-glue carcase in place of cam fittings',
                'Cleaner outside face with no cam covers, but it stops being flat-pack. Two prototypes built, assembly time not yet measured.', null],

            ['DESK_1600', 'B', RevisionLifecycle::Released, 'Postformed front edge, routed cable port with grommet',
                'The square front edge was chipping where people rest their forearms. A postformed radius fixed it, and the cable port stopped the aftermarket drilling.', 250],

            ['BOOK_5S', 'A', RevisionLifecycle::Released, '18 mm gables, 32 mm shelf supports, wall anchor strap included',
                'Initial release. Anchor strap included from day one rather than sold separately.', 330],

            ['WARD_SLIDE', 'B', RevisionLifecycle::Released, 'Oak decor with grain-direction cutting plan, 0.8 mm matching tape',
                'Grain direction was not specified on rev A and half a batch was cut across. The plan now carries the arrow.', 140],

            ['TVUNIT_1400', 'A', RevisionLifecycle::Released, 'Built around the standard 600×720 carcase set',
                'Initial release, designed around the existing carcase set deliberately — no new drilling program.', 160],
        ];

        foreach ($defs as [$productCode, $code, $status, $description, $reason, $releasedAgo]) {
            $productType = $pt[$productCode] ?? null;
            if (! $productType) {
                continue;
            }

            $releasedAt = $releasedAgo === null ? null : now()->subDays($releasedAgo);
            $supersededAt = $releasedAgo === null ? null : now()->subDays(max(1, (int) $releasedAgo - 130));

            ProductRevision::updateOrCreate(
                ['product_type_id' => $productType->id, 'revision_code' => $code],
                [
                    'description' => $description,
                    'change_reason' => $reason,
                    'lifecycle_status' => $status,
                    // Only the revision in use points at the routing the shop is
                    // actually running.
                    'process_template_id' => $status === RevisionLifecycle::Released
                        ? ($templates[$productCode]?->id)
                        : null,
                    'external_ref' => sprintf('SPEC-%s-%s', $productCode, $code),
                    'effective_from' => $releasedAt,
                    'effective_to' => $status === RevisionLifecycle::Obsolete ? $supersededAt : null,
                    'released_at' => $releasedAt,
                    'obsolete_at' => $status === RevisionLifecycle::Obsolete ? $supersededAt : null,
                    'released_by_id' => $status === RevisionLifecycle::Draft ? null : $engineer?->id,
                ]
            );
        }
    }

    // ── Material lots ────────────────────────────────────────────────────────

    private function seedMaterialLots(array $materials): void
    {
        // Decor batch traceability is the real story here: a batch change
        // part-way through a wardrobe is invisible in the board store and
        // obvious in a showroom, and it comes back.
        $lots = [
            ['lot' => 'MFC-W-26031',  'material' => 'MAT-MFC-18-W',    'qty' => 800,  'unit' => 'm2',  'supplier_lot' => 'KL-W1000-26031'],
            ['lot' => 'MFC-OAK-26028', 'material' => 'MAT-MFC-18-OAK', 'qty' => 420,  'unit' => 'm2',  'supplier_lot' => 'KL-H3303-26028'],
            ['lot' => 'MDF-MR-26019', 'material' => 'MAT-MDF-18-MR',   'qty' => 260,  'unit' => 'm2',  'supplier_lot' => 'WM-MR18-26019'],
            ['lot' => 'HDF-26040',    'material' => 'MAT-HDF-3',       'qty' => 900,  'unit' => 'm2',  'supplier_lot' => 'WM-HDF3-26040'],
            ['lot' => 'EDG-W-26022',  'material' => 'MAT-EDGE-ABS-2',  'qty' => 3000, 'unit' => 'm',   'supplier_lot' => 'EL-ABS2W-26022'],
            ['lot' => 'FOIL-26015',   'material' => 'MAT-FOIL-GLOSS',  'qty' => 600,  'unit' => 'm2',  'supplier_lot' => 'DF-HG-26015'],
            ['lot' => 'HNG-26007',    'material' => 'MAT-HINGE-CLIP',  'qty' => 3000, 'unit' => 'pcs', 'supplier_lot' => 'FF-SC110-26007'],
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
                    'received_at' => now()->subDays(mt_rand(3, 25)),
                    'status' => 'available',
                    'supplier_lot_no' => $def['supplier_lot'],
                ]
            );
        }
    }

    // ── Reported issues ──────────────────────────────────────────────────────

    /**
     * @param  array<string, Line>  $lines
     * @param  array<int, User>  $users
     */
    private function seedIssues(array $lines, array $users): void
    {
        $types = IssueType::pluck('id', 'code');

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

        $defs = [
            ['CUT', 'CHIPPING', 'Underside chipping on the white gables',
                'Bottom face is chipping along the full cut on saw 2. The top face is clean, so it looks like the scorer is out of alignment rather than blunt. Moved the order to saw 1 and flagged the scorer.',
                Issue::STATUS_OPEN, null],
            ['EDGE', 'EDGE_LIFT', 'Tape lifting at the panel ends on the PUR bander',
                'Last twenty oak panels have an open joint in the first 30 mm. Glue pot temperature reads right but the nozzle looks skinned. Panels quarantined, not re-banded yet.',
                Issue::STATUS_OPEN, null],

            ['DRILL', 'BORE_POSITION', 'System holes drifting on the bookcase gables',
                'First panel measured right, the twentieth is 1.5 mm low on the last hole. Suspect the boring head has warmed and moved. Batch held and the setter called.',
                Issue::STATUS_ACKNOWLEDGED, 5],
            ['CUT', 'PANEL_SIZE', 'Wardrobe gables 2 mm long',
                'Pressure beam was letting the sheet creep mid-cut. Pads replaced and the batch re-cut from the same board batch so the decor still matches.',
                Issue::STATUS_RESOLVED, 22],
            ['SURF', 'GLOSS_DEFECT', 'Pinhole patch on gloss kitchen doors',
                'Small unbonded patch at the profile corner on six doors. The membrane had a pinhole. Membrane changed and the six doors stripped and re-pressed.',
                Issue::STATUS_RESOLVED, 31],

            ['ASSY', 'FITTING_SHORT', 'Leg packs short on a kitchen order',
                'Three base units went out with three legs instead of four. The bags had been counted by weight. Replacement packs couriered and counting by hand reinstated at the kitting bench.',
                Issue::STATUS_CLOSED, 3 * 24 + 4],
            ['CUT', 'DECOR_MISMATCH', 'Two white batches in one wardrobe order',
                'Board store issued from the end of one pack and the start of the next. The difference is invisible in the store and obvious in a showroom. Order re-cut from a single batch and the remainder tagged.',
                Issue::STATUS_CLOSED, 5 * 24 + 8],
            ['CUT', 'MOISTURE', 'Swollen edges on a pack of moisture-resistant board',
                'Outer sheets had been stood on end in the yard overnight in the rain and the edges have swollen 1-2 mm. Whole pack quarantined and a claim raised with Westmoor.',
                Issue::STATUS_CLOSED, 7 * 24 + 6],
            ['DRILL', 'BOARD_DAMAGE', 'Scratches across desk tops from the machine bed',
                'Swarf left on the boring machine bed was dragging across the faces. Bed cleaned and a felt sheet added. Four tops scrapped, the rest buffed out.',
                Issue::STATUS_CLOSED, 10 * 24 + 3],
            ['PACK', 'CARTON_DAMAGE', 'Cartons splitting at the bottom seam',
                'A run of cartons split when lifted — the glue seam on the batch was weak. Batch returned to Boxwell and the remaining stock double-taped before use.',
                Issue::STATUS_CLOSED, 13 * 24 + 5],
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
                        ? $reportedAt->copy()->addMinutes(12) : null,
                    'resolved_at' => in_array($status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(75) : null,
                    'closed_at' => $status === Issue::STATUS_CLOSED
                        ? $reportedAt->copy()->addMinutes(165) : null,
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
            ['code' => 'PANEL-01'],
            [
                'name' => 'Ashcroft Panel Works',
                'description' => 'Flat-pack carcase furniture: wardrobes, chests, desks and kitchen units in melamine-faced board',
                'address' => 'Unit 7, Calverton Industrial Park',
                'city' => 'Mansfield',
                'country' => 'GB',
                'timezone' => 'Europe/London',
                'is_active' => true,
            ]
        );

        $areaDefs = [
            ['code' => 'BOARD-STORE', 'name' => 'Board Store',            'description' => 'Flat-stacked sheet stock, humidity-controlled — board is bought by the pack and moved by vacuum lifter'],
            ['code' => 'MACH-HALL',   'name' => 'Machining Hall',         'description' => 'Beam saws, nesting router, edgebanders and CNC boring'],
            ['code' => 'FINISH-RM',   'name' => 'Fronts & Finishing Room', 'description' => 'Membrane press, UV coater and profile wrapping — kept separate, dust is the enemy here'],
            ['code' => 'ASSY-HALL',   'name' => 'Assembly & Packing Hall', 'description' => 'Drawer cells, case clamp, fittings kitting and carton packing'],
            ['code' => 'FG-WH',       'name' => 'Finished Goods Warehouse', 'description' => 'Pallet build and loading bays for the trade counter and the carriers'],
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
                'CUT' => 'MACH-HALL',
                'EDGE' => 'MACH-HALL',
                'DRILL' => 'MACH-HALL',
                'SURF' => 'FINISH-RM',
                'ASSY' => 'ASSY-HALL',
                'PACK' => 'ASSY-HALL',
                'DISP' => 'FG-WH',
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
            ['code' => 'CUT_OPT',      'name' => 'Cutting Optimisation', 'description' => 'Reading and adjusting cutting plans for yield, grain direction and decor batch'],
            ['code' => 'EDGE_SETUP',   'name' => 'Edgebander Setting',   'description' => 'Pre-mill, trim and scraper setting, glue pot temperature and adhesion checks'],
            ['code' => 'CNC_SETUP',    'name' => 'CNC Setting',          'description' => 'Loading programs, setting datums and proving a boring pattern before a batch'],
            ['code' => 'GLOSS_HANDLE', 'name' => 'Gloss Handling',       'description' => 'Membrane pressing, lacquering and the handling discipline gloss fronts need'],
            ['code' => 'FITTING_KIT',  'name' => 'Fittings & Kitting',   'description' => 'Hardware knowledge and disciplined counting of fixing packs'],
            ['code' => 'PANEL_QC',     'name' => 'Panel Quality Inspection', 'description' => 'Squareness, size, edge adhesion and surface grading against the decor standard'],
        ];

        $skills = [];
        foreach ($skillDefs as $def) {
            $skills[$def['code']] = Skill::updateOrCreate(
                ['code' => $def['code']],
                ['name' => $def['name'], 'description' => $def['description']]
            );
        }

        $classDefs = [
            ['code' => 'SAW_OPERATOR', 'name' => 'Saw Operator',      'description' => 'Runs the beam saws and the nesting router from the cutting plan', 'required_skill_ids' => [$skills['CUT_OPT']->id, $skills['PANEL_QC']->id]],
            ['code' => 'EDGE_SETTER',  'name' => 'Edgebander Setter', 'description' => 'Sets and runs the banding line, owns edge quality',               'required_skill_ids' => [$skills['EDGE_SETUP']->id, $skills['PANEL_QC']->id]],
            ['code' => 'CNC_SETTER',   'name' => 'CNC Setter',        'description' => 'Programs and proves the boring machines',                         'required_skill_ids' => [$skills['CNC_SETUP']->id, $skills['PANEL_QC']->id]],
            ['code' => 'FINISHER',     'name' => 'Finisher',          'description' => 'Membrane press, UV coating and profile wrapping of fronts',        'required_skill_ids' => [$skills['GLOSS_HANDLE']->id, $skills['PANEL_QC']->id]],
            ['code' => 'ASSEMBLER',    'name' => 'Assembler / Packer', 'description' => 'Drawer boxes, carcase clamping, kitting and cartoning',           'required_skill_ids' => [$skills['FITTING_KIT']->id]],
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
            ['code' => 'CREW-CUT',    'name' => 'Cutting Crew',       'lines' => ['CUT'],                  'workstations' => ['SAW-BEAM-01', 'SAW-BEAM-02', 'NEST-01']],
            ['code' => 'CREW-EDGE',   'name' => 'Banding Crew',       'lines' => ['EDGE'],                 'workstations' => ['EDGE-01', 'EDGE-03', 'SOFT-01']],
            ['code' => 'CREW-CNC',    'name' => 'CNC Crew',           'lines' => ['DRILL'],                'workstations' => ['CNC-BORE-01', 'CNC-BORE-02', 'DOWEL-01']],
            ['code' => 'CREW-FRONTS', 'name' => 'Fronts Crew',        'lines' => ['SURF'],                 'workstations' => ['MEMB-01', 'COAT-01', 'WRAP-01']],
            ['code' => 'CREW-ASSY',   'name' => 'Assembly & Packing', 'lines' => ['ASSY', 'PACK', 'DISP'], 'workstations' => ['DRAWER-01', 'CASE-01', 'PACK-01']],
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
            ['code' => 'SEG-PLAN',  'name' => 'Cutting Plan',      'description' => 'Confirm decor, grain direction and board batch before the first cut', 'segment_type' => 'production', 'duration' => 12, 'operators' => 1, 'instruction' => 'Confirm decor, grain direction and board batch against the order before the first cut.'],
            ['code' => 'SEG-CUT',   'name' => 'Panel Cutting',     'description' => 'Beam sawing to the optimised plan',                                   'segment_type' => 'production', 'duration' => 36, 'operators' => 2, 'instruction' => 'Run the plan, check the first book for chipping on both faces, label every part as it comes off.'],
            ['code' => 'SEG-EDGE',  'name' => 'Edgebanding',       'description' => 'Applying and finishing edge tape',                                    'segment_type' => 'production', 'duration' => 22, 'operators' => 1, 'instruction' => 'Pre-mill, band, trim and scrape. Check adhesion by lifting a tape end on the first panel.'],
            ['code' => 'SEG-BORE',  'name' => 'Boring & Dowelling', 'description' => 'Connector and system drilling with dowel insertion',                 'segment_type' => 'production', 'duration' => 26, 'operators' => 1, 'instruction' => 'Bore from a single datum, measure the first and last panel of the stack, insert dowels flush.'],
            ['code' => 'SEG-FRONT', 'name' => 'Front Finishing',   'description' => 'Routing, pressing and lacquering of gloss fronts',                    'segment_type' => 'production', 'duration' => 24, 'operators' => 2, 'instruction' => 'Rout, prime, press and lacquer. Handle fronts by the back only once pressed.'],
            ['code' => 'SEG-ASSY',  'name' => 'Sub-assembly',      'description' => 'Drawer box and carcase building',                                     'segment_type' => 'production', 'duration' => 20, 'operators' => 2, 'instruction' => 'Build, clamp square and check the diagonals before the clamp comes off.'],
            ['code' => 'SEG-QC',    'name' => 'Quality Check',     'description' => 'Dimensional and surface check before packing',                        'segment_type' => 'inspection', 'duration' => 10, 'operators' => 1, 'instruction' => 'Check size, squareness, edge adhesion and surface grade against the decor standard.'],
            ['code' => 'SEG-PACK',  'name' => 'Pack & Palletise',  'description' => 'Cartoning, protection and pallet build by route',                     'segment_type' => 'production', 'duration' => 18, 'operators' => 2, 'instruction' => 'Pack faced sides inward, protect corners, mark the carton count and build the pallet by route.'],
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

    /** @return array<string, Tool> */
    private function seedTools(): array
    {
        $defs = [
            ['code' => 'TL-SAW-MAIN',    'name' => 'Beam saw main blade, 450 mm diamond', 'status' => Tool::STATUS_IN_USE,      'dueInDays' => 9,    'description' => 'Re-tip at the scheduled cut length. A blunt main blade burns the chipboard core and you smell it before you see it.'],
            ['code' => 'TL-SAW-SCORE',   'name' => 'Beam saw scoring blade set',          'status' => Tool::STATUS_IN_USE,      'dueInDays' => 4,    'description' => 'Set the scorer 0.1 mm wider than the main blade. Chipping on the underside is the scorer, not the main blade.'],
            ['code' => 'TL-SAW-PRESS',   'name' => 'Pressure beam pads and clamps',       'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 38,   'description' => 'Replace when the pads glaze. A slipping pad lets the sheet creep mid-cut and the parts come out long.'],
            ['code' => 'TL-NEST-CUT',    'name' => 'Nesting router compression cutters, 12 mm', 'status' => Tool::STATUS_IN_USE, 'dueInDays' => 2,   'description' => 'Change on cut length. Fuzzy edges and the smell of burning mean the cutter has gone, not that the feed is wrong.'],
            ['code' => 'TL-NEST-BED',    'name' => 'Nesting vacuum bed seals and spoilboard', 'status' => Tool::STATUS_MAINTENANCE, 'dueInDays' => -1, 'description' => 'Re-skim the spoilboard and replace the seals. Parts shifting on the last row mean vacuum has dropped below 0.7 bar.'],
            ['code' => 'TL-EDGE-PREMILL', 'name' => 'Edgebander pre-milling cutters',     'status' => Tool::STATUS_IN_USE,      'dueInDays' => 6,    'description' => 'Change quarterly. A wavy glue line is a blunt pre-mill, and no amount of glue temperature will fix it.'],
            ['code' => 'TL-EDGE-TRIM',   'name' => 'Flush trim knives and radius cutters', 'status' => Tool::STATUS_IN_USE,     'dueInDays' => 3,    'description' => 'A white witness line along the trimmed edge of a decor tape is the knives asking to be changed.'],
            ['code' => 'TL-EDGE-SCRAPE', 'name' => 'Edge scrapers and profile shoes',     'status' => Tool::STATUS_IN_USE,      'dueInDays' => 11,   'description' => 'Set to the tape thickness. A glue ridge you can feel with a thumbnail is a scraper set shallow or worn.'],
            ['code' => 'TL-EDGE-POT',    'name' => 'PUR glue pot, nozzle and hose',       'status' => Tool::STATUS_MAINTENANCE, 'dueInDays' => -3,   'description' => 'Purge at every shift end. A skinned nozzle gives intermittent open joints in the first 30 mm of each panel.'],
            ['code' => 'TL-BORE-HEAD',   'name' => 'CNC boring head, 21-spindle',         'status' => Tool::STATUS_IN_USE,      'dueInDays' => 18,   'description' => 'Rebuild yearly. Hole spacing drifting off the 32 mm system through a batch is a worn head, not the program.'],
            ['code' => 'TL-BORE-BITS',   'name' => 'Dowel drill bit set, 8 mm',           'status' => Tool::STATUS_IN_USE,      'dueInDays' => 7,    'description' => 'Replace on hole count. Breakout around the hole mouth and dowels standing proud say the bits are due.'],
            ['code' => 'TL-HINGE-CUP',   'name' => 'Hinge cup cutter, 35 mm',             'status' => Tool::STATUS_IN_USE,      'dueInDays' => 14,   'description' => 'A fluffy cup bottom that lets the hinge plate rock is the cutter, and it shows on the customer\'s door alignment.'],
            ['code' => 'TL-MEMB-SKIN',   'name' => 'Membrane press silicone membrane',    'status' => Tool::STATUS_IN_USE,      'dueInDays' => 22,   'description' => 'Inspect against the light before every gloss run. A pinhole leaves an unbonded patch at the profile corner.'],
            ['code' => 'TL-COAT-ROLL',   'name' => 'UV coater application rollers',       'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 29,   'description' => 'Re-grind when tram lines appear in the lacquer film — you will see them at a low angle before a customer does.'],
            ['code' => 'TL-CLAMP-PAD',   'name' => 'Case clamp pads and squaring stops',  'status' => Tool::STATUS_IN_USE,      'dueInDays' => 45,   'description' => 'Replace glazed pads and re-square the stops. Dents pressed into a faced panel come from a hardened pad.'],
            ['code' => 'TL-SAW-SLIDE',   'name' => 'Sliding table saw blades (legacy)',   'status' => Tool::STATUS_RETIRED,     'dueInDays' => null, 'description' => 'From the old workshop saw, replaced when the second beam saw went in. Kept for the odd one-off that never comes.'],
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

        $schedules['scorer'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Weekly Scoring Blade Alignment'],
            [
                'tool_id' => $tools['TL-SAW-SCORE']?->id,
                'description' => 'Check and reset the scoring blade against the main blade, and cut a test book',
                'line_id' => $lines['CUT']->id,
                'workstation_id' => $workstations['SAW-BEAM-02']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '13:00',
                'next_due_at' => now()->next('Monday')->setTime(13, 0),
                'is_active' => true,
            ]
        );

        $schedules['gluepot'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Monthly Edgebander Glue Pot Service'],
            [
                'tool_id' => $tools['TL-EDGE-POT']?->id,
                'description' => 'Strip, purge and rebuild the PUR glue pot, nozzle and hose',
                'line_id' => $lines['EDGE']->id,
                'workstation_id' => $workstations['EDGE-02']->id,
                'event_type' => 'planned',
                'frequency' => 'monthly',
                'interval_value' => 1,
                'preferred_time' => '13:30',
                'next_due_at' => now()->addDays(3)->setTime(13, 30),
                'is_active' => true,
            ]
        );

        $schedules['borehead'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Quarterly Boring Head Check'],
            [
                'tool_id' => $tools['TL-BORE-HEAD']?->id,
                'description' => 'Measure hole spacing hot and cold against the 32 mm system and shim if required',
                'line_id' => $lines['DRILL']->id,
                'workstation_id' => $workstations['CNC-BORE-01']->id,
                'event_type' => 'inspection',
                'frequency' => 'quarterly',
                'interval_value' => 1,
                'preferred_time' => '14:00',
                'next_due_at' => now()->addDays(26)->setTime(14, 0),
                'is_active' => true,
            ]
        );

        $schedules['membrane'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Monthly Membrane Inspection'],
            [
                'tool_id' => $tools['TL-MEMB-SKIN']?->id,
                'description' => 'Inspect the press membrane against the light for pinholes and thinning',
                'line_id' => $lines['SURF']->id,
                'workstation_id' => $workstations['MEMB-01']->id,
                'event_type' => 'inspection',
                'frequency' => 'monthly',
                'interval_value' => 1,
                'preferred_time' => '06:30',
                'next_due_at' => now()->addDays(12)->setTime(6, 30),
                'is_active' => true,
            ]
        );

        $events = [
            [
                'title' => 'Scoring Blade Alignment (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['CUT']->id,
                'workstation_id' => $workstations['SAW-BEAM-02']->id,
                'schedule_id' => $schedules['scorer']->id,
                'scheduled_at' => now()->subDays(2)->setTime(13, 0),
                'scheduled_end_at' => now()->subDays(2)->setTime(14, 0),
                'description' => 'Scorer reset and a test book cut. Underside clean on the test, though chipping was reported again since.',
            ],
            [
                'title' => 'Boring Head Measurement (completed)',
                'event_type' => 'inspection',
                'status' => 'completed',
                'line_id' => $lines['DRILL']->id,
                'workstation_id' => $workstations['CNC-BORE-01']->id,
                'schedule_id' => $schedules['borehead']->id,
                'scheduled_at' => now()->subWeek()->setTime(14, 0),
                'scheduled_end_at' => now()->subWeek()->setTime(16, 0),
                'description' => 'Last hole in the pattern was 1.5 mm out at temperature. Head shimmed and the program re-proved.',
            ],
            [
                'title' => 'PUR Glue Pot Strip-down (in progress)',
                'event_type' => 'corrective',
                'status' => 'in_progress',
                'line_id' => $lines['EDGE']->id,
                'workstation_id' => $workstations['EDGE-02']->id,
                'schedule_id' => $schedules['gluepot']->id,
                'scheduled_at' => now()->subHours(3),
                'scheduled_end_at' => now()->addHours(2),
                'description' => 'Nozzle stripped after open joints on the oak run. Banding on the EVA machine meanwhile, so no oak tape until it is back.',
            ],
            [
                'title' => 'Nesting Bed Re-skim (due)',
                'event_type' => 'planned',
                'status' => 'pending',
                'line_id' => $lines['CUT']->id,
                'workstation_id' => $workstations['NEST-01']->id,
                'schedule_id' => null,
                'scheduled_at' => now()->addDay()->setTime(13, 0),
                'scheduled_end_at' => now()->addDay()->setTime(16, 0),
                'description' => 'Spoilboard re-skim and seal change — vacuum has been reading low on the last row.',
            ],
            [
                'title' => 'Membrane Inspection (scheduled)',
                'event_type' => 'inspection',
                'status' => 'pending',
                'line_id' => $lines['SURF']->id,
                'workstation_id' => $workstations['MEMB-01']->id,
                'schedule_id' => $schedules['membrane']->id,
                'scheduled_at' => now()->addDays(12)->setTime(6, 30),
                'scheduled_end_at' => now()->addDays(12)->setTime(8, 0),
                'description' => 'Monthly membrane check before the next gloss run.',
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
                'name' => 'Board Goods-in Check',
                'material' => 'MAT-MFC-18-W',
                'description' => 'Sheet stock on arrival: thickness, decor batch, moisture and edge condition',
                'criteria' => ['thickness', 'decor_batch_number', 'moisture_content', 'sheet_squareness', 'face_damage'],
            ],
            [
                'name' => 'Edge Tape Goods-in Check',
                'material' => 'MAT-EDGE-ABS-2',
                'description' => 'Tape rolls: decor match against the board standard, width, thickness and roll condition',
                'criteria' => ['decor_match_to_board', 'tape_width', 'tape_thickness', 'roll_damage'],
            ],
            [
                'name' => 'Hardware Goods-in Check',
                'material' => 'MAT-HINGE-CLIP',
                'description' => 'Sample fit and function on hinges from each delivery',
                'criteria' => ['sample_fit_to_cup', 'soft_close_function', 'plate_dimensions', 'finish_consistency'],
            ],
            [
                'name' => 'Gloss Foil Goods-in Check',
                'material' => 'MAT-FOIL-GLOSS',
                'description' => 'Foil rolls: gloss level, colour against the master and surface defects',
                'criteria' => ['gloss_level', 'colour_vs_master', 'surface_defects', 'roll_width'],
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
            if ($code === 'DISP') {
                continue; // dispatch is not a production line
            }

            for ($day = -14; $day <= 0; $day++) {
                $date = now()->addDays($day)->format('Y-m-d');

                $planned = mt_rand(440, 480);
                // Board changes and cutting-plan swaps are the downtime here.
                $downtime = mt_rand(15, 70);
                $operating = $planned - $downtime;
                $totalProduced = mt_rand(120, 900);
                $scrap = mt_rand(0, (int) max(1, $totalProduced * 0.04));
                $good = $totalProduced - $scrap;

                $availability = round($operating / max($planned, 1) * 100, 1);
                $performance = round(mt_rand(70, 96), 1);
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
