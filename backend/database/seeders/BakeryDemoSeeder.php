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
 * Demo data for a craft bakery — bread, rolls and cakes.
 *
 * The fourth example company, and the one that looks least like a factory.
 * Three things make it its own case rather than a relabelled plant:
 *
 *  - The work happens at night. Dough is mixed at ten in the evening and the
 *    vans leave at six, so the night shift is the main one and the day shift
 *    is the quiet one. Every other dataset here runs days.
 *  - The bill of materials is a genuine chain, not an assembly. Rye bread is
 *    made from rye dough, which is made from levain, which is made from flour —
 *    and the levain is kept alive rather than bought. Wheat dough feeds both
 *    the loaves and the kaiser rolls, so netting has to sum both before it
 *    decides how much to mix.
 *  - Quality problems are food problems. A foreign body or an allergen cross-
 *    contact blocks the order outright; under-proofed dough does not.
 *
 * Product revisions here are recipe revisions — hydration raised, butter
 * content changed — which is what a baker actually versions.
 *
 * Run with: `php artisan db:seed --class=BakeryDemoSeeder`
 *
 * Upsert-safe throughout, so it can be re-run on top of itself.
 */
class BakeryDemoSeeder extends Seeder
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
            // Food safety stops the line. No argument, no judgement call.
            ['code' => 'FOREIGN_BODY',   'name' => 'Foreign Body Found',           'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'ALLERGEN_RISK',  'name' => 'Allergen Cross-contact Risk',  'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'OVEN_TEMP',      'name' => 'Oven Temperature Out of Range', 'severity' => 'HIGH',    'is_blocking' => true],
            ['code' => 'LEVAIN_DEAD',    'name' => 'Levain Not Active',            'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'FLOUR_QUALITY',  'name' => 'Flour Damp or Contaminated',   'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PROOF_FAILURE',  'name' => 'Dough Under-proofed',          'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'BAKE_COLOUR',    'name' => 'Under-baked / Over-baked',     'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'WEIGHT_OFF',     'name' => 'Piece Weight Out of Tolerance', 'severity' => 'MEDIUM',  'is_blocking' => false],
            ['code' => 'DIVIDER_FAULT',  'name' => 'Divider Fault',                'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'PACK_DAMAGE',    'name' => 'Packaging Damaged',            'severity' => 'LOW',      'is_blocking' => false],
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
            ['code' => 'DOUGH',  'name' => 'Mixing & Dough',      'description' => 'Flour handling, levain feeding and bulk dough mixing'],
            ['code' => 'PROOF',  'name' => 'Dividing & Proofing', 'description' => 'Dividing, shaping and controlled proofing'],
            ['code' => 'BAKE',   'name' => 'Ovens',               'description' => 'Deck, rack and tunnel ovens'],
            ['code' => 'PASTRY', 'name' => 'Pastry & Cakes',      'description' => 'Lamination, fillings, baking and decoration of cakes and pastries'],
            ['code' => 'PACK',   'name' => 'Cooling & Packing',   'description' => 'Cooling spiral, slicing, bagging and labelling'],
            ['code' => 'DISP',   'name' => 'Dispatch',            'description' => 'Crate loading and van routing for the morning delivery round'],
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
            ['line' => 'DOUGH',  'code' => 'SILO-01',   'name' => 'Flour Silo & Sifter',            'workstation_type' => 'silo'],
            ['line' => 'DOUGH',  'code' => 'MIX-01',    'name' => 'Spiral Mixer #1 (160 l)',        'workstation_type' => 'mixer'],
            ['line' => 'DOUGH',  'code' => 'MIX-02',    'name' => 'Spiral Mixer #2 (120 l)',        'workstation_type' => 'mixer'],
            ['line' => 'DOUGH',  'code' => 'LEVAIN-01', 'name' => 'Levain Station',                 'workstation_type' => 'mixer'],

            ['line' => 'PROOF',  'code' => 'DIV-01',    'name' => 'Volumetric Divider',             'workstation_type' => 'divider'],
            ['line' => 'PROOF',  'code' => 'SHAPE-01',  'name' => 'Moulder / Shaping Line #1',      'workstation_type' => 'shaping'],
            ['line' => 'PROOF',  'code' => 'SHAPE-02',  'name' => 'Hand Shaping Bench',             'workstation_type' => 'shaping'],
            ['line' => 'PROOF',  'code' => 'PROOF-01',  'name' => 'Proofing Chamber #1',            'workstation_type' => 'proofer'],
            ['line' => 'PROOF',  'code' => 'PROOF-02',  'name' => 'Retarder-Proofer #2',            'workstation_type' => 'proofer'],

            ['line' => 'BAKE',   'code' => 'OVEN-01',   'name' => 'Deck Oven #1 (stone)',           'workstation_type' => 'oven_deck'],
            ['line' => 'BAKE',   'code' => 'OVEN-02',   'name' => 'Rack Oven #2',                   'workstation_type' => 'oven_rack'],
            ['line' => 'BAKE',   'code' => 'OVEN-03',   'name' => 'Tunnel Oven #3',                 'workstation_type' => 'oven_tunnel'],

            ['line' => 'PASTRY', 'code' => 'LAM-01',    'name' => 'Dough Sheeter / Laminator',      'workstation_type' => 'sheeter'],
            ['line' => 'PASTRY', 'code' => 'CREAM-01',  'name' => 'Filling Mixer & Depositor',      'workstation_type' => 'depositor'],
            ['line' => 'PASTRY', 'code' => 'DECOR-01',  'name' => 'Decorating Bench',               'workstation_type' => 'decorating'],

            ['line' => 'PACK',   'code' => 'COOL-01',   'name' => 'Cooling Spiral',                 'workstation_type' => 'cooling'],
            ['line' => 'PACK',   'code' => 'SLICE-01',  'name' => 'Slicer & Bagger',                'workstation_type' => 'slicer'],
            ['line' => 'PACK',   'code' => 'PACK-01',   'name' => 'Packing Bench #1',               'workstation_type' => 'packing'],

            ['line' => 'DISP',   'code' => 'DISP-01',   'name' => 'Dispatch & Crate Loading',       'workstation_type' => 'packing'],
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
            ['code' => 'BREAD_WHEAT', 'name' => 'Wheat Loaf 700 g',      'description' => 'Tin-baked wheat loaf, type 750 flour, soft crumb',            'unit_of_measure' => 'pcs'],
            ['code' => 'BREAD_RYE',   'name' => 'Rye Sourdough 900 g',   'description' => 'Naturally leavened rye loaf, long fermentation, dark crust',   'unit_of_measure' => 'pcs'],
            ['code' => 'ROLL_KAISER', 'name' => 'Kaiser Roll 60 g',      'description' => 'Classic stamped wheat roll with poppy or sesame topping',      'unit_of_measure' => 'pcs'],
            ['code' => 'ROLL_GRAHAM', 'name' => 'Graham Roll 70 g',      'description' => 'Wholemeal roll, type 1850 flour, seeded top',                  'unit_of_measure' => 'pcs'],
            ['code' => 'BAGUETTE',    'name' => 'Baguette 250 g',        'description' => 'Long-fermented baguette, scored and steam-baked',              'unit_of_measure' => 'pcs'],
            ['code' => 'CROISSANT',   'name' => 'Butter Croissant 80 g', 'description' => 'Laminated croissant, 82% butter, 27 layers',                   'unit_of_measure' => 'pcs'],
            ['code' => 'CAKE_CHEESE', 'name' => 'Baked Cheesecake',      'description' => 'Traditional baked curd cheesecake on a shortcrust base',       'unit_of_measure' => 'pcs'],
            // Sub-assemblies. In a bakery these are the doughs and fillings —
            // each is produced in its own right before it becomes a product, so
            // each needs a product type to hang a routing on.
            ['code' => 'SA_LEVAIN',   'name' => 'Rye Levain',            'description' => 'Live sourdough culture, refreshed daily and carried forward',   'unit_of_measure' => 'kg'],
            ['code' => 'SA_DOUGH_RY', 'name' => 'Rye Dough',             'description' => 'Bulk rye dough built on levain, long bulk fermentation',        'unit_of_measure' => 'kg'],
            ['code' => 'SA_DOUGH_WH', 'name' => 'Wheat Dough',           'description' => 'Bulk wheat dough for loaves and rolls',                         'unit_of_measure' => 'kg'],
            ['code' => 'SA_LAMIN',    'name' => 'Laminated Dough',       'description' => 'Butter-laminated dough, folded and rested between turns',       'unit_of_measure' => 'kg'],
            ['code' => 'SA_FILLING',  'name' => 'Cheesecake Filling',    'description' => 'Curd, egg and cream filling, beaten and rested before baking',  'unit_of_measure' => 'kg'],
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

        $t['BREAD_WHEAT'] = $this->createTemplate($pt['BREAD_WHEAT'], 'Wheat Loaf 700 g — v2', [
            [1, 'Dough check',      'Check the bulk dough temperature and that it has doubled. Cold dough will not rise in the tin.', 6, $ws['MIX-01'] ?? null],
            [2, 'Divide',           'Divide at 760 g to allow for bake loss. Weigh every tenth piece.', 10, $ws['DIV-01'] ?? null],
            [3, 'Mould & tin',      'Mould and drop into greased tins, seam down.', 12, $ws['SHAPE-01'] ?? null],
            [4, 'Final proof',      'Proof at 32 °C, 80% humidity until the dough crowns the tin. Do not go by the clock alone.', 55, $ws['PROOF-01'] ?? null],
            [5, 'Bake',             'Bake at 230 °C with steam for the first 10 minutes, then vent.', 35, $ws['OVEN-02'] ?? null],
            [6, 'Cool',             'De-tin immediately and cool on racks. Bagging a warm loaf makes it sweat and go mouldy.', 45, $ws['COOL-01'] ?? null],
            [7, 'Slice & bag',      'Slice, bag and date-label.', 10, $ws['SLICE-01'] ?? null],
        ]);

        $t['BREAD_RYE'] = $this->createTemplate($pt['BREAD_RYE'], 'Rye Sourdough 900 g — v3', [
            [1, 'Levain check',     'Check the levain is active — it should have risen and smell sharp, not acetic. A flat levain will not raise this loaf.', 8, $ws['LEVAIN-01'] ?? null],
            [2, 'Divide',           'Divide at 980 g. Rye is sticky — keep the hopper oiled rather than floured.', 12, $ws['DIV-01'] ?? null],
            [3, 'Shape',            'Shape by hand into bannetons, seam up.', 20, $ws['SHAPE-02'] ?? null],
            [4, 'Final proof',      'Proof at 30 °C for 60-80 minutes. Rye over-proofs quickly and will collapse in the oven.', 70, $ws['PROOF-02'] ?? null],
            [5, 'Bake',             'Turn out, score and bake at 250 °C falling to 210 °C. Heavy steam at the start.', 55, $ws['OVEN-01'] ?? null],
            [6, 'Cool',             'Cool at least four hours before cutting — rye crumb sets as it cools.', 240, $ws['COOL-01'] ?? null],
            [7, 'Pack',             'Bag whole, date-label.', 10, $ws['PACK-01'] ?? null],
        ]);

        $t['ROLL_KAISER'] = $this->createTemplate($pt['ROLL_KAISER'], 'Kaiser Roll 60 g — v1', [
            [1, 'Dough check',   'Check dough temperature and slackness before dividing.', 5, $ws['MIX-01'] ?? null],
            [2, 'Divide',        'Divide at 64 g. Check weights at the start of every tray.', 12, $ws['DIV-01'] ?? null],
            [3, 'Stamp & seed',  'Round, stamp the kaiser pattern and dip the top in seed.', 18, $ws['SHAPE-01'] ?? null],
            [4, 'Final proof',   'Proof 35-40 minutes. Under-proofed rolls burst at the stamp.', 38, $ws['PROOF-01'] ?? null],
            [5, 'Bake',          'Bake at 240 °C with steam, 14 minutes to a deep gold.', 16, $ws['OVEN-03'] ?? null],
            [6, 'Cool & pack',   'Cool on racks then bag in tens.', 25, $ws['PACK-01'] ?? null],
        ]);

        $t['ROLL_GRAHAM'] = $this->createTemplate($pt['ROLL_GRAHAM'], 'Graham Roll 70 g — v1', [
            [1, 'Dough check',  'Wholemeal absorbs more water — check the dough is not tightening up.', 5, $ws['MIX-02'] ?? null],
            [2, 'Divide',       'Divide at 74 g.', 12, $ws['DIV-01'] ?? null],
            [3, 'Shape & seed', 'Round and roll the tops through the seed tray.', 15, $ws['SHAPE-01'] ?? null],
            [4, 'Final proof',  'Proof 40 minutes at 32 °C.', 40, $ws['PROOF-01'] ?? null],
            [5, 'Bake',         'Bake at 235 °C for 16 minutes.', 18, $ws['OVEN-03'] ?? null],
            [6, 'Cool & pack',  'Cool and bag in sixes.', 25, $ws['PACK-01'] ?? null],
        ]);

        $t['BAGUETTE'] = $this->createTemplate($pt['BAGUETTE'], 'Baguette 250 g — v2', [
            [1, 'Dough check',  'This dough is slack and long-fermented. Handle gently — knocking it back loses the open crumb.', 6, $ws['MIX-01'] ?? null],
            [2, 'Divide',       'Divide at 265 g by hand. Avoid the volumetric divider on this one.', 15, $ws['SHAPE-02'] ?? null],
            [3, 'Pre-shape & rest', 'Pre-shape, rest 20 minutes under cloth, then shape long.', 30, $ws['SHAPE-02'] ?? null],
            [4, 'Final proof',  'Proof on couche 45 minutes. Slightly under is better than over.', 45, $ws['PROOF-02'] ?? null],
            [5, 'Score & bake', 'Score at a shallow angle. Bake 250 °C with heavy steam, 20 minutes.', 22, $ws['OVEN-01'] ?? null],
            [6, 'Cool & rack',  'Cool on wire — never flat, or the base goes soft.', 30, $ws['COOL-01'] ?? null],
        ]);

        $t['CROISSANT'] = $this->createTemplate($pt['CROISSANT'], 'Butter Croissant 80 g — v2', [
            [1, 'Sheet & cut',  'Sheet the laminated dough to 3.5 mm and cut triangles. Keep it cold or the butter smears.', 25, $ws['LAM-01'] ?? null],
            [2, 'Roll',         'Roll from the base with even tension, six turns, tuck the tip under.', 30, $ws['SHAPE-02'] ?? null],
            [3, 'Final proof',  'Proof at 26 °C — above 28 °C the butter runs out and you lose the layers.', 90, $ws['PROOF-02'] ?? null],
            [4, 'Egg wash & bake', 'Egg wash and bake at 200 °C for 18 minutes.', 20, $ws['OVEN-02'] ?? null],
            [5, 'Cool & pack',  'Cool completely before boxing.', 30, $ws['PACK-01'] ?? null],
        ]);

        $t['CAKE_CHEESE'] = $this->createTemplate($pt['CAKE_CHEESE'], 'Baked Cheesecake — v1', [
            [1, 'Base',          'Press the shortcrust base into the tins and dock it.', 20, $ws['DECOR-01'] ?? null],
            [2, 'Deposit filling', 'Deposit the filling to weight. Tap the tin to release air pockets.', 18, $ws['CREAM-01'] ?? null],
            [3, 'Bake',          'Bake at 160 °C in a water bath. Low and slow — a hot oven cracks the top.', 65, $ws['OVEN-02'] ?? null],
            [4, 'Cool & set',    'Cool in the oven with the door ajar, then chill overnight before cutting.', 120, $ws['COOL-01'] ?? null],
            [5, 'Decorate',      'Dust or glaze to the order, portion and place on trays.', 25, $ws['DECOR-01'] ?? null],
            [6, 'Pack',          'Box, label with the allergen list and date.', 15, $ws['PACK-01'] ?? null],
        ]);

        // ── Sub-assembly routings ────────────────────────────────────────────

        $t['SA_LEVAIN'] = $this->createTemplate($pt['SA_LEVAIN'], 'Rye Levain — refresh v1', [
            [1, 'Refresh',  'Feed the mother with rye flour and water at 1:2:2. Keep some back — this culture is carried forward, not bought.', 15, $ws['LEVAIN-01'] ?? null],
            [2, 'Ripen',    'Ripen at 28 °C for 14-16 hours until it domes and just begins to fall.', 60, $ws['LEVAIN-01'] ?? null],
        ]);

        $t['SA_DOUGH_RY'] = $this->createTemplate($pt['SA_DOUGH_RY'], 'Rye Dough — bulk v2', [
            [1, 'Mix',      'Mix levain, rye flour, water and salt to a sticky, cohesive mass. Rye needs mixing, not kneading.', 18, $ws['MIX-02'] ?? null],
            [2, 'Bulk ferment', 'Bulk ferment 90 minutes at 28 °C.', 90, $ws['MIX-02'] ?? null],
        ]);

        $t['SA_DOUGH_WH'] = $this->createTemplate($pt['SA_DOUGH_WH'], 'Wheat Dough — bulk v1', [
            [1, 'Mix',      'Mix to full gluten development — the dough should pass a windowpane test.', 16, $ws['MIX-01'] ?? null],
            [2, 'Bulk ferment', 'Bulk ferment 60 minutes, one fold at the halfway point.', 60, $ws['MIX-01'] ?? null],
        ]);

        $t['SA_LAMIN'] = $this->createTemplate($pt['SA_LAMIN'], 'Laminated Dough — v1', [
            [1, 'Détrempe',  'Mix the base dough lightly and chill it — over-mixing here fights the lamination later.', 15, $ws['MIX-02'] ?? null],
            [2, 'Butter block', 'Beat the butter to the same plasticity as the dough. Mismatched firmness tears the layers.', 12, $ws['LAM-01'] ?? null],
            [3, 'Turns',     'Three single turns with a 30-minute rest between each. Keep everything at 14-16 °C.', 95, $ws['LAM-01'] ?? null],
        ]);

        $t['SA_FILLING'] = $this->createTemplate($pt['SA_FILLING'], 'Cheesecake Filling — v1', [
            [1, 'Beat',   'Beat the curd smooth before anything else goes in, or it stays lumpy.', 14, $ws['CREAM-01'] ?? null],
            [2, 'Combine', 'Add egg, sugar and cream on low speed. Do not whip air in — it cracks in the oven.', 12, $ws['CREAM-01'] ?? null],
            [3, 'Rest',   'Rest 30 minutes so the bubbles rise out.', 30, $ws['CREAM-01'] ?? null],
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
            // updateOrInsert() runs without the model's scope.
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
            ['username' => 'grazyna.wojcik'],
            [
                'name' => 'Grażyna Wójcik',
                'email' => 'grazyna.wojcik@piekarnia.local',
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
            ['username' => 'janusz.mlynarz', 'name' => 'Janusz Młynarz', 'email' => 'janusz.mlynarz@piekarnia.local', 'lines' => ['DOUGH', 'PROOF']],
            ['username' => 'kasia.piec',     'name' => 'Katarzyna Piec', 'email' => 'katarzyna.piec@piekarnia.local', 'lines' => ['BAKE']],
            ['username' => 'adam.cukier',    'name' => 'Adam Cukier',    'email' => 'adam.cukier@piekarnia.local',    'lines' => ['PASTRY']],
            ['username' => 'olga.pakuje',    'name' => 'Olga Pakuła',    'email' => 'olga.pakula@piekarnia.local',    'lines' => ['PACK', 'DISP']],
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
            ['code' => 'CUST-DELIKAT',  'name' => 'Delikatesy Centrum',   'tier' => Tier::Vip,    'payment_score' => 93, 'notes' => 'Twelve stores on the morning round. Standing order adjusted by phone before 20:00.'],
            ['code' => 'CUST-HOTELREG', 'name' => 'Hotel Regent',         'tier' => Tier::Gold,   'payment_score' => 87, 'notes' => 'Breakfast delivery by 05:30, no exceptions. Croissants and baguettes daily.'],
            ['code' => 'CUST-SZKOLA',   'name' => 'Catering Szkolny',     'tier' => Tier::Gold,   'payment_score' => 72, 'notes' => 'School catering. Allergen declaration required with every delivery note.'],
            ['code' => 'CUST-KAWIARNIA', 'name' => 'Kawiarnia Alt',       'tier' => Tier::Silver, 'payment_score' => 64, 'notes' => 'Small daily order of pastry and cheesecake. Orders late, pays on time.'],
            ['code' => 'CUST-HURT',     'name' => 'Hurtownia Spożywcza',  'tier' => Tier::Silver, 'payment_score' => 55, 'notes' => 'Wholesale rolls in bulk. Price-driven, tolerant on delivery windows.'],
            ['code' => 'CUST-SKLEP',    'name' => 'Sklep firmowy',        'tier' => Tier::Bronze, 'payment_score' => 48, 'notes' => 'Our own shop. Takes whatever the round does not, so the order moves daily.'],
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
                'customer_order_no' => sprintf('ZAM-%s-%04d', now()->year, 4000 + $i),
            ])->saveQuietly();
        }
    }

    // ── Work orders ──────────────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        mt_srand(20260303);

        $orders = [
            [
                'order_no' => 'WO-BK-0001',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['ROLL_KAISER']->id,
                'planned_qty' => 2400,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 5,
                'due_date' => now()->addDay()->setTime(5, 0),
                'planned_start_at' => now()->setTime(23, 0),
                'planned_end_at' => now()->addDay()->setTime(4, 0),
                'description' => 'Kaiser rolls — Delikatesy Centrum morning round, twelve stores.',
            ],
            [
                'order_no' => 'WO-BK-0002',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['BREAD_RYE']->id,
                'planned_qty' => 320,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 4,
                'due_date' => now()->addDay()->setTime(6, 0),
                'planned_start_at' => now()->setTime(22, 0),
                'planned_end_at' => now()->addDay()->setTime(5, 0),
                'description' => 'Rye sourdough — long ferment, out of the oven by 05:00.',
            ],
            [
                'order_no' => 'WO-BK-0003',
                'line_id' => $lines['PASTRY']->id,
                'product_type_id' => $pt['CROISSANT']->id,
                'planned_qty' => 600,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 5,
                'due_date' => now()->addDay()->setTime(5, 30),
                'planned_start_at' => now()->addDay()->setTime(1, 0),
                'planned_end_at' => now()->addDay()->setTime(5, 0),
                'description' => 'Croissants — Hotel Regent breakfast, delivery 05:30.',
            ],
            [
                'order_no' => 'WO-BK-0004',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['BREAD_WHEAT']->id,
                'planned_qty' => 450,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => now()->addDays(2)->setTime(6, 0),
                'planned_start_at' => now()->addDay()->setTime(23, 0),
                'planned_end_at' => now()->addDays(2)->setTime(4, 0),
                'description' => 'Wheat loaves — wholesale, sliced and bagged.',
            ],
            [
                'order_no' => 'WO-BK-0005',
                'line_id' => $lines['PASTRY']->id,
                'product_type_id' => $pt['CAKE_CHEESE']->id,
                'planned_qty' => 80,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => now()->addDays(3)->setTime(10, 0),
                'planned_start_at' => now()->addDays(2)->setTime(6, 0),
                'planned_end_at' => now()->addDays(2)->setTime(14, 0),
                'description' => 'Cheesecakes — needs overnight chill before portioning.',
            ],
            [
                'order_no' => 'WO-BK-0006',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['BAGUETTE']->id,
                'planned_qty' => 700,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 4,
                'due_date' => now()->addDays(4)->setTime(6, 0),
                'planned_start_at' => now()->addDays(3)->setTime(22, 0),
                'planned_end_at' => now()->addDays(4)->setTime(5, 0),
                'description' => 'Baguettes — restaurant and hotel orders combined.',
            ],
            [
                'order_no' => 'WO-BK-0007',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['ROLL_GRAHAM']->id,
                'planned_qty' => 1800,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 2,
                'due_date' => now()->addDays(5)->setTime(6, 0),
                'planned_start_at' => now()->addDays(4)->setTime(23, 0),
                'planned_end_at' => now()->addDays(5)->setTime(4, 0),
                'description' => 'Graham rolls — school catering, allergen sheet with the note.',
            ],
            [
                'order_no' => 'WO-BK-0008',
                'line_id' => $lines['BAKE']->id,
                'product_type_id' => $pt['ROLL_KAISER']->id,
                'planned_qty' => 2200,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 5,
                'due_date' => now()->subDay()->setTime(5, 0),
                'planned_start_at' => now()->subDays(2)->setTime(23, 0),
                'planned_end_at' => now()->subDay()->setTime(4, 0),
                'completed_at' => now()->subDay()->setTime(3, 40),
                'description' => 'Kaiser rolls — yesterday\'s round, delivered complete.',
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
            'BREAD_WHEAT', 'BREAD_RYE', 'ROLL_KAISER', 'ROLL_GRAHAM', 'BAGUETTE', 'CROISSANT', 'CAKE_CHEESE',
        ])));

        $descriptions = [
            'Standing order — morning round',
            'Extra tray requested by phone last night',
            'Weekend volume — double the usual',
            'Hotel breakfast order',
            'School catering delivery',
            'Wholesale pallet order',
            'Own shop top-up',
            'Seasonal line — limited run',
            'Replacement for a short delivery',
            'Sampling batch for a new customer',
        ];

        // A bakery's day starts at night. The heavy shift is 22:00-06:00, the
        // morning shift finishes what the night started, and the afternoon is
        // the quiet one — the opposite shape to every other dataset here.
        $shifts = [['h' => 23, 'night' => true], ['h' => 7, 'night' => false], ['h' => 15, 'night' => false]];
        $nightLines = ['DOUGH', 'PROOF', 'BAKE', 'PASTRY'];

        $weekFill = [-1 => 0.35, 0 => 0.85, 1 => 0.58, 2 => 0.44, 3 => 0.32, 4 => 0.22];

        $weekStart = now()->startOfWeek();
        $n = 100;

        foreach ($weekFill as $weekOffset => $fill) {
            foreach ($allLines as $line) {
                for ($d = 0; $d < 7; $d++) {
                    foreach ($shifts as $shift) {
                        if ($shift['night'] && ! in_array($line->code, $nightLines, true)) {
                            continue;
                        }
                        if (mt_rand(1, 100) > (int) round($fill * 100)) {
                            continue;
                        }

                        $n++;
                        $start = $weekStart->copy()->addWeeks($weekOffset)->addDays($d)->setTime($shift['h'], 0);
                        $end = $start->copy()->addHours(8);
                        // Bread and rolls run in the hundreds and thousands.
                        $qty = mt_rand(2, 24) * 50;

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
                            ['order_no' => sprintf('WO-BK-%04d', $n)],
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
                                'due_date' => $end->copy()->addHours(mt_rand(1, 6)),
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
     * Bread moves down the bakery in one continuous run — mixed, divided,
     * proofed, baked, cooled, loaded — so an order sits on several lines the
     * same night. The planner draws that as a badge plus a connector.
     *
     * @param  array<string, Line>  $lines
     */
    private function seedMultiLinePlacements(array $lines): void
    {
        $handoffs = [
            'DOUGH' => [['PROOF', 1]],
            'PROOF' => [['BAKE', 1]],
            'BAKE' => [['PACK', 1], ['DISP', 2]],
            'PASTRY' => [['PACK', 1]],
        ];

        $lineById = [];
        foreach ($lines as $code => $line) {
            $lineById[$line->id] = $code;
        }

        $orders = WorkOrder::whereNotNull('planned_start_at')->orderBy('order_no')->get();

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
        $allDays = [1, 2, 3, 4, 5, 6, 7];
        $monToSat = [1, 2, 3, 4, 5, 6];

        $defs = [
            [
                // The main shift. Bread is mixed in the evening and leaves on
                // the vans at six — the bakery's day runs through the night.
                'name' => 'Night Shift', 'code' => 'SN',
                'start_time' => '22:00', 'end_time' => '06:00',
                'days_of_week' => $allDays, 'line_codes' => ['DOUGH', 'PROOF', 'BAKE', 'PASTRY'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Morning Shift', 'code' => 'SM',
                'start_time' => '06:00', 'end_time' => '14:00',
                'days_of_week' => $allDays, 'line_codes' => ['DOUGH', 'PROOF', 'BAKE', 'PASTRY', 'PACK', 'DISP'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Afternoon Shift', 'code' => 'SA',
                'start_time' => '14:00', 'end_time' => '22:00',
                'days_of_week' => $monToSat, 'line_codes' => ['PASTRY', 'PACK'],
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
            ['code' => 'MAT-FLOUR-750',  'name' => 'Wheat flour type 750',     'type' => 'raw_material', 'unit' => 'kg',    'stock' => 2400, 'price' => 2.35, 'supplier' => 'Młyn Wisła'],
            ['code' => 'MAT-FLOUR-RYE',  'name' => 'Rye flour type 720',       'type' => 'raw_material', 'unit' => 'kg',    'stock' => 860,  'price' => 2.80, 'supplier' => 'Młyn Wisła'],
            ['code' => 'MAT-FLOUR-GRAH', 'name' => 'Graham flour type 1850',   'type' => 'raw_material', 'unit' => 'kg',    'stock' => 520,  'price' => 3.10, 'supplier' => 'Młyn Wisła'],
            ['code' => 'MAT-YEAST',      'name' => 'Fresh yeast',              'type' => 'raw_material', 'unit' => 'kg',    'stock' => 48,   'price' => 9.60, 'supplier' => 'Lesaffre'],
            ['code' => 'MAT-SALT',       'name' => 'Bakers salt',              'type' => 'raw_material', 'unit' => 'kg',    'stock' => 310,  'price' => 1.40, 'supplier' => 'Kłodawa'],
            ['code' => 'MAT-BUTTER',     'name' => 'Butter 82%, sheet',        'type' => 'raw_material', 'unit' => 'kg',    'stock' => 180,  'price' => 32.50, 'supplier' => 'Mlekovita'],
            ['code' => 'MAT-SUGAR',      'name' => 'Caster sugar',             'type' => 'raw_material', 'unit' => 'kg',    'stock' => 260,  'price' => 3.90, 'supplier' => 'Pfeifer & Langen'],
            ['code' => 'MAT-EGG',        'name' => 'Pasteurised whole egg',    'type' => 'raw_material', 'unit' => 'litre', 'stock' => 140,  'price' => 11.20, 'supplier' => 'Ovopol'],
            ['code' => 'MAT-CURD',       'name' => 'Curd cheese, cheesecake grade', 'type' => 'raw_material', 'unit' => 'kg', 'stock' => 95, 'price' => 14.80, 'supplier' => 'Mlekovita'],
            ['code' => 'MAT-CREAM',      'name' => 'Cream 36%',                'type' => 'raw_material', 'unit' => 'litre', 'stock' => 70,   'price' => 16.40, 'supplier' => 'Mlekovita'],
            ['code' => 'MAT-SEEDS',      'name' => 'Seed mix (sesame, poppy, sunflower)', 'type' => 'raw_material', 'unit' => 'kg', 'stock' => 85, 'price' => 12.90, 'supplier' => 'Bakels'],
            ['code' => 'MAT-IMPROVER',   'name' => 'Bread improver',           'type' => 'auxiliary',    'unit' => 'kg',    'stock' => 42,   'price' => 18.50, 'supplier' => 'Bakels'],

            ['code' => 'MAT-BAG-PAPER',  'name' => 'Paper bread bag',          'type' => 'packaging',    'unit' => 'pcs',   'stock' => 4200, 'price' => 0.22, 'supplier' => 'PackLine'],
            ['code' => 'MAT-TRAY-CAKE',  'name' => 'Cake tray and lid',        'type' => 'packaging',    'unit' => 'pcs',   'stock' => 380,  'price' => 1.85, 'supplier' => 'PackLine'],
            ['code' => 'MAT-CRATE',      'name' => 'Delivery crate',           'type' => 'packaging',    'unit' => 'pcs',   'stock' => 240,  'price' => 24.00, 'supplier' => 'PackLine'],
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

        // The doughs and fillings. Stock is deliberately uneven: the levain is
        // held short, so a rye order has to be netted three levels down before
        // the shortage shows up.
        $subAssemblies = [
            ['code' => 'SA-LEVAIN',    'template' => 'SA_LEVAIN',   'name' => 'Rye Levain',         'stock' => 18],
            ['code' => 'SA-DOUGH-RY',  'template' => 'SA_DOUGH_RY', 'name' => 'Rye Dough',          'stock' => 60],
            ['code' => 'SA-DOUGH-WH',  'template' => 'SA_DOUGH_WH', 'name' => 'Wheat Dough',        'stock' => 140],
            ['code' => 'SA-LAMIN',     'template' => 'SA_LAMIN',    'name' => 'Laminated Dough',    'stock' => 35],
            ['code' => 'SA-FILLING',   'template' => 'SA_FILLING',  'name' => 'Cheesecake Filling', 'stock' => 22],
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
                    'unit_of_measure' => 'kg',
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
     * Recipes, expressed as a bill of materials.
     *
     * The rye loaf runs three manufactured levels deep — dough, then levain,
     * then flour — and the levain is refreshed rather than bought, which is
     * why it is a made part at all. Wheat dough feeds the loaves, the kaiser
     * rolls and the baguettes, so netting has to sum three parents before it
     * decides how much to mix.
     *
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<string, Material>  $materials
     */
    private function seedBom(array $templates, array $materials): void
    {
        $defs = [
            'BREAD_WHEAT' => [
                [1, 'SA-DOUGH-WH',  0.76,  2, 'start'],
                [7, 'MAT-BAG-PAPER', 1,    1, 'end'],
            ],
            'BREAD_RYE' => [
                [1, 'SA-DOUGH-RY',  0.98,  2, 'start'],
                [7, 'MAT-BAG-PAPER', 1,    1, 'end'],
            ],
            'ROLL_KAISER' => [
                [1, 'SA-DOUGH-WH',  0.064, 3, 'start'],
                [3, 'MAT-SEEDS',    0.002, 5, 'during'],
                [6, 'MAT-BAG-PAPER', 0.1,  1, 'end'],
            ],
            'ROLL_GRAHAM' => [
                [1, 'MAT-FLOUR-GRAH', 0.045, 3, 'start'],
                [1, 'MAT-YEAST',    0.001, 2, 'start'],
                [3, 'MAT-SEEDS',    0.003, 5, 'during'],
                [6, 'MAT-BAG-PAPER', 0.17, 1, 'end'],
            ],
            'BAGUETTE' => [
                [1, 'SA-DOUGH-WH',  0.265, 3, 'start'],
            ],
            'CROISSANT' => [
                [1, 'SA-LAMIN',     0.085, 4, 'start'],
                [4, 'MAT-EGG',      0.004, 3, 'during'],
            ],
            'CAKE_CHEESE' => [
                [1, 'MAT-FLOUR-750', 0.18, 3, 'start'],
                [1, 'MAT-BUTTER',   0.09,  2, 'start'],
                [2, 'SA-FILLING',   0.85,  2, 'during'],
                [6, 'MAT-TRAY-CAKE', 1,    1, 'end'],
            ],

            // ── Sub-assemblies, deepest last ─────────────────────────────────
            'SA_DOUGH_RY' => [
                [1, 'SA-LEVAIN',    0.28,  2, 'start'],
                [1, 'MAT-FLOUR-RYE', 0.62, 2, 'start'],
                [1, 'MAT-SALT',     0.018, 1, 'start'],
            ],
            'SA_DOUGH_WH' => [
                [1, 'MAT-FLOUR-750', 0.60, 2, 'start'],
                [1, 'MAT-YEAST',    0.012, 2, 'start'],
                [1, 'MAT-SALT',     0.012, 1, 'start'],
                [1, 'MAT-IMPROVER', 0.004, 1, 'start'],
            ],
            'SA_LEVAIN' => [
                // Only flour and water go in — the culture itself is carried
                // forward from the previous refresh, not purchased.
                [1, 'MAT-FLOUR-RYE', 0.45, 4, 'start'],
            ],
            'SA_LAMIN' => [
                [1, 'MAT-FLOUR-750', 0.52, 3, 'start'],
                [2, 'MAT-BUTTER',   0.30,  3, 'start'],
                [1, 'MAT-SUGAR',    0.05,  1, 'start'],
                [1, 'MAT-YEAST',    0.015, 2, 'start'],
            ],
            'SA_FILLING' => [
                [1, 'MAT-CURD',     0.68,  2, 'start'],
                [2, 'MAT-EGG',      0.14,  2, 'during'],
                [2, 'MAT-SUGAR',    0.12,  1, 'during'],
                [2, 'MAT-CREAM',    0.10,  2, 'during'],
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
     * Recipe revisions — what a baker actually versions.
     *
     * Hydration, fermentation time and butter content are the levers, and a
     * change to any of them is a different product in the oven. Each part has
     * the revision in use plus the history around it: what it replaced, and
     * what is being trialled next.
     *
     * @param  array<string, ProductType>  $pt
     * @param  array<string, ProcessTemplate>  $templates
     * @param  array<int, User>  $users
     */
    private function seedProductRevisions(array $pt, array $templates, array $users): void
    {
        $baker = collect($users)->first();

        $defs = [
            ['BREAD_RYE', 'A', RevisionLifecycle::Obsolete, 'Original recipe — 68% hydration, 4 h bulk',
                'Superseded by rev B. Crumb was tight and the loaf staled by the second day.', 500],
            ['BREAD_RYE', 'B', RevisionLifecycle::Released, '74% hydration, 6 h bulk on levain',
                'Wetter dough and a longer ferment. Open crumb, keeps three days, and customers noticed.', 150],
            ['BREAD_RYE', 'C', RevisionLifecycle::Draft, 'Trial: 5% scalded rye added',
                'Scald should hold moisture longer still. Two test bakes done, shelf life not yet measured.', null],

            ['CROISSANT', 'A', RevisionLifecycle::Obsolete, 'Original — 24% butter, three half turns',
                'Superseded by rev B. Layers were indistinct and the pastry read as bready.', 380],
            ['CROISSANT', 'B', RevisionLifecycle::Released, '30% butter, three single turns, 27 layers',
                'More butter and a colder lamination. Clean honeycomb, and the hotel stopped complaining.', 120],

            ['BREAD_WHEAT', 'B', RevisionLifecycle::Released, 'Type 750 with improver, 60% hydration',
                'Improver added for a consistent crumb across seasons — the flour varies more than the recipe.', 260],

            ['ROLL_KAISER', 'A', RevisionLifecycle::Released, '64 g divide, stamped, seeded top',
                'Initial release. Divide weight set to land at 60 g baked.', 420],

            ['ROLL_GRAHAM', 'A', RevisionLifecycle::Released, 'Type 1850 wholemeal, 74 g divide',
                'Initial release.', 300],

            ['BAGUETTE', 'B', RevisionLifecycle::Released, 'Long ferment, hand-divided',
                'Moved off the volumetric divider — it was degassing the dough and closing the crumb.', 90],

            ['CAKE_CHEESE', 'A', RevisionLifecycle::Released, 'Baked in a water bath at 160 °C',
                'Initial release. Water bath added after cracking complaints from the café.', 210],
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
                    // Only the revision in use points at the routing the bakery
                    // is actually running.
                    'process_template_id' => $status === RevisionLifecycle::Released
                        ? ($templates[$productCode]?->id)
                        : null,
                    'external_ref' => sprintf('REC-%s-%s', $productCode, $code),
                    'effective_from' => $releasedAt,
                    'effective_to' => $status === RevisionLifecycle::Obsolete ? $supersededAt : null,
                    'released_at' => $releasedAt,
                    'obsolete_at' => $status === RevisionLifecycle::Obsolete ? $supersededAt : null,
                    'released_by_id' => $status === RevisionLifecycle::Draft ? null : $baker?->id,
                ]
            );
        }
    }

    // ── Material lots ────────────────────────────────────────────────────────

    private function seedMaterialLots(array $materials): void
    {
        // Food traceability is lot-driven: a recall has to reach every loaf
        // made from one sack of flour.
        $lots = [
            ['lot' => 'MAKA-750-26014', 'material' => 'MAT-FLOUR-750',  'qty' => 1000, 'unit' => 'kg',    'supplier_lot' => 'MW-750-26014'],
            ['lot' => 'MAKA-RYE-26011', 'material' => 'MAT-FLOUR-RYE',  'qty' => 400,  'unit' => 'kg',    'supplier_lot' => 'MW-720-26011'],
            ['lot' => 'MAKA-GR-26009',  'material' => 'MAT-FLOUR-GRAH', 'qty' => 250,  'unit' => 'kg',    'supplier_lot' => 'MW-1850-26009'],
            ['lot' => 'MASLO-26-0142',  'material' => 'MAT-BUTTER',     'qty' => 100,  'unit' => 'kg',    'supplier_lot' => 'MLK-B82-0142'],
            ['lot' => 'TWAROG-26-0088', 'material' => 'MAT-CURD',       'qty' => 60,   'unit' => 'kg',    'supplier_lot' => 'MLK-TW-0088'],
            ['lot' => 'JAJA-26-0231',   'material' => 'MAT-EGG',        'qty' => 80,   'unit' => 'litre', 'supplier_lot' => 'OVO-PW-0231'],
            ['lot' => 'DROZ-26-0455',   'material' => 'MAT-YEAST',      'qty' => 25,   'unit' => 'kg',    'supplier_lot' => 'LSF-FR-0455'],
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
                    'received_at' => now()->subDays(mt_rand(2, 21)),
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
            ['BAKE', 'OVEN_TEMP', 'Deck oven running 25 °C under set point',
                'Second deck is not holding temperature — the bake is pale and the crust soft. Moved the batch to the rack oven and called the engineer.',
                Issue::STATUS_OPEN, null],
            ['PROOF', 'PROOF_FAILURE', 'Rolls under-proofed on tray 14 onwards',
                'Chamber humidity dropped and the last trays are tight. They will burst at the stamp — holding them back for a longer proof.',
                Issue::STATUS_OPEN, null],

            ['DOUGH', 'LEVAIN_DEAD', 'Levain flat at the morning check',
                'No dome, smells sharply acetic. The bakery was cold overnight. Fed twice and warmed to bring it round.',
                Issue::STATUS_ACKNOWLEDGED, 6],
            ['PACK', 'WEIGHT_OFF', 'Loaves finishing 20 g light',
                'Divider drifted through the run. Re-set to 760 g and checked ten in a row.',
                Issue::STATUS_RESOLVED, 20],
            ['BAKE', 'BAKE_COLOUR', 'Baguettes pale on the bottom deck',
                'Bottom heat set too low after the deck was cleaned. Corrected and re-baked the tray.',
                Issue::STATUS_RESOLVED, 28],

            ['DOUGH', 'FLOUR_QUALITY', 'Damp sack of rye flour from the pallet',
                'One sack had absorbed moisture and was caking. Quarantined the pallet and notified the mill.',
                Issue::STATUS_CLOSED, 3 * 24 + 6],
            ['PASTRY', 'ALLERGEN_RISK', 'Sesame traces on the pastry bench',
                'Seeded rolls had been trayed on the pastry bench before the school order. Bench and trays re-washed, batch withheld.',
                Issue::STATUS_CLOSED, 5 * 24 + 9],
            ['PROOF', 'DIVIDER_FAULT', 'Divider piston sticking',
                'Every fifth piece came out heavy. Piston stripped and re-greased.',
                Issue::STATUS_CLOSED, 8 * 24 + 5],
            ['PACK', 'PACK_DAMAGE', 'Bags splitting at the seal',
                'A run of bags sealed badly — the jaws were running cool. Temperature raised and the batch re-bagged.',
                Issue::STATUS_CLOSED, 11 * 24 + 4],
            ['BAKE', 'FOREIGN_BODY', 'Metal fragment found on a tray',
                'Small metal shaving on a baking tray during the check. Whole tray destroyed, oven and trays inspected, source traced to a worn rack runner.',
                Issue::STATUS_CLOSED, 13 * 24 + 7],
        ];

        foreach ($defs as $i => [$lineCode, $typeCode, $title, $description, $status, $hoursAgo]) {
            $workOrder = $onLine($lineCode);
            $typeId = $types[$typeCode] ?? null;

            if (! $workOrder || ! $typeId) {
                continue;
            }

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
                        ? $reportedAt->copy()->addMinutes(10) : null,
                    'resolved_at' => in_array($status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
                        ? $reportedAt->copy()->addMinutes(55) : null,
                    'closed_at' => $status === Issue::STATUS_CLOSED
                        ? $reportedAt->copy()->addMinutes(140) : null,
                ]
            );

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
            ['code' => 'PIEK-01'],
            [
                'name' => 'Piekarnia Złoty Kłos',
                'description' => 'Craft bakery: bread, rolls and cakes, baked overnight for the morning round',
                'address' => 'ul. Piekarska 22',
                'city' => 'Kraków',
                'country' => 'PL',
                'timezone' => 'Europe/Warsaw',
                'is_active' => true,
            ]
        );

        $areaDefs = [
            ['code' => 'HALL-BAKE', 'name' => 'Bakehouse',       'description' => 'Mixing, proofing and ovens'],
            ['code' => 'PASTRY-RM', 'name' => 'Pastry Room',     'description' => 'Separate room for laminated dough and cakes, allergen-controlled'],
            ['code' => 'COOL-RM',   'name' => 'Cooling & Packing', 'description' => 'Cooling spiral, slicing and bagging'],
            ['code' => 'STORE-DRY', 'name' => 'Dry Store',       'description' => 'Flour silo, sugar and packaging'],
            ['code' => 'STORE-COLD', 'name' => 'Cold Store',     'description' => 'Butter, dairy and egg, temperature logged'],
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
                'DOUGH' => 'HALL-BAKE',
                'PROOF' => 'HALL-BAKE',
                'BAKE' => 'HALL-BAKE',
                'PASTRY' => 'PASTRY-RM',
                'PACK' => 'COOL-RM',
                'DISP' => 'COOL-RM',
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
            ['code' => 'DOUGH_CRAFT',  'name' => 'Dough Craft',        'description' => 'Judging dough by hand — hydration, development and bulk readiness'],
            ['code' => 'SOURDOUGH',    'name' => 'Sourdough Handling', 'description' => 'Keeping a levain alive, reading its activity and timing the build'],
            ['code' => 'OVEN_WORK',    'name' => 'Oven Work',          'description' => 'Loading, steaming and judging the bake by colour and sound'],
            ['code' => 'LAMINATION',   'name' => 'Lamination',         'description' => 'Butter blocks, turns and temperature control for laminated pastry'],
            ['code' => 'DECORATING',   'name' => 'Cake Decorating',    'description' => 'Finishing, glazing and portioning of cakes'],
            ['code' => 'FOOD_SAFETY',  'name' => 'Food Safety (HACCP)', 'description' => 'Allergen control, traceability and critical control points'],
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
                'code' => 'BAKER',
                'name' => 'Baker',
                'description' => 'Mixes, shapes and bakes bread through the night shift',
                'required_skill_ids' => [$skills['DOUGH_CRAFT']->id, $skills['OVEN_WORK']->id, $skills['FOOD_SAFETY']->id],
            ],
            [
                'code' => 'SOURDOUGH_BAKER',
                'name' => 'Sourdough Baker',
                'description' => 'Keeps the levain and runs the naturally leavened breads',
                'required_skill_ids' => [$skills['SOURDOUGH']->id, $skills['DOUGH_CRAFT']->id],
            ],
            [
                'code' => 'PASTRY_CHEF',
                'name' => 'Pastry Chef',
                'description' => 'Laminated pastry and cake work in the pastry room',
                'required_skill_ids' => [$skills['LAMINATION']->id, $skills['DECORATING']->id, $skills['FOOD_SAFETY']->id],
            ],
            [
                'code' => 'PACKER',
                'name' => 'Packer',
                'description' => 'Cooling, slicing, bagging and crate loading for the round',
                'required_skill_ids' => [$skills['FOOD_SAFETY']->id],
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
            ['code' => 'CREW-NIGHT',  'name' => 'Night Bakers',   'lines' => ['DOUGH', 'PROOF'], 'workstations' => ['MIX-01', 'DIV-01', 'PROOF-01']],
            ['code' => 'CREW-OVEN',   'name' => 'Oven Crew',      'lines' => ['BAKE'],           'workstations' => ['OVEN-01', 'OVEN-02', 'OVEN-03']],
            ['code' => 'CREW-PASTRY', 'name' => 'Pastry Crew',    'lines' => ['PASTRY'],         'workstations' => ['LAM-01', 'CREAM-01', 'DECOR-01']],
            ['code' => 'CREW-PACK',   'name' => 'Packing & Round', 'lines' => ['PACK', 'DISP'],  'workstations' => ['SLICE-01', 'PACK-01', 'DISP-01']],
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
            ['code' => 'SEG-MIX',    'name' => 'Mixing',        'description' => 'Bulk dough mixing to full development',       'segment_type' => 'production', 'duration' => 16, 'operators' => 1, 'instruction' => 'Weigh to the recipe, mix to development, check final dough temperature.'],
            ['code' => 'SEG-BULK',   'name' => 'Bulk Ferment',  'description' => 'Controlled bulk fermentation of the dough',   'segment_type' => 'production', 'duration' => 60, 'operators' => 1, 'instruction' => 'Hold at the recipe temperature. Judge by rise and feel, not the clock alone.'],
            ['code' => 'SEG-DIVIDE', 'name' => 'Divide & Shape', 'description' => 'Dividing to weight and shaping',             'segment_type' => 'production', 'duration' => 15, 'operators' => 2, 'instruction' => 'Check divide weight at the start of every tray and after any adjustment.'],
            ['code' => 'SEG-PROOF',  'name' => 'Final Proof',   'description' => 'Final proof in a controlled chamber',         'segment_type' => 'production', 'duration' => 45, 'operators' => 1, 'instruction' => 'Set temperature and humidity per the recipe. Check readiness by touch before loading.'],
            ['code' => 'SEG-BAKE',   'name' => 'Baking',        'description' => 'Oven bake with steam where required',         'segment_type' => 'production', 'duration' => 30, 'operators' => 1, 'instruction' => 'Load, steam per the recipe, vent on time and judge the bake by colour.'],
            ['code' => 'SEG-COOL',   'name' => 'Cooling',       'description' => 'Cooling before slicing or packing',           'segment_type' => 'production', 'duration' => 45, 'operators' => 1, 'instruction' => 'Cool on wire, never flat. Bagging warm product makes it sweat.'],
            ['code' => 'SEG-QC',     'name' => 'Quality Check', 'description' => 'Weight, bake and allergen check before pack', 'segment_type' => 'inspection', 'duration' => 8,  'operators' => 1, 'instruction' => 'Weigh a sample, check crust and crumb, confirm the allergen label matches the product.'],
            ['code' => 'SEG-PACK',   'name' => 'Pack & Crate',  'description' => 'Bagging, labelling and crate loading',        'segment_type' => 'production', 'duration' => 12, 'operators' => 2, 'instruction' => 'Bag, date-label and load to crates by delivery route.'],
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
            ['code' => 'TL-MIX-BOWL',  'name' => 'Spiral mixer bowl & hook',       'status' => Tool::STATUS_IN_USE,      'dueInDays' => 8,    'description' => 'Check the bowl seal and hook clearance. A worn hook leaves an unmixed collar at the bottom.'],
            ['code' => 'TL-MIX-BELT',  'name' => 'Mixer drive belt set',           'status' => Tool::STATUS_IN_USE,      'dueInDays' => 27,   'description' => 'Slipping belt shows up as long mixes and an under-developed dough.'],
            ['code' => 'TL-DIV-PIST',  'name' => 'Divider pistons & knives',       'status' => Tool::STATUS_MAINTENANCE, 'dueInDays' => -2,   'description' => 'Stripped down after sticking. A worn piston drifts the divide weight through a run.'],
            ['code' => 'TL-PROOF-HUM', 'name' => 'Proofer humidifier & probe',     'status' => Tool::STATUS_IN_USE,      'dueInDays' => 5,    'description' => 'Descale monthly and calibrate the probe. Dry chamber skins the dough and it bursts in the oven.'],
            ['code' => 'TL-OVEN-STEAM', 'name' => 'Deck oven steam generator',     'status' => Tool::STATUS_IN_USE,      'dueInDays' => 3,    'description' => 'Descale on schedule — weak steam gives a dull crust and poor oven spring.'],
            ['code' => 'TL-OVEN-BURN', 'name' => 'Oven burner & thermostat',       'status' => Tool::STATUS_IN_USE,      'dueInDays' => 12,   'description' => 'Calibrate the thermostat quarterly. A deck reading 25 °C under is invisible until the bake is pale.'],
            ['code' => 'TL-RACK-TROL', 'name' => 'Rack oven trolleys & runners',   'status' => Tool::STATUS_IN_USE,      'dueInDays' => 17,   'description' => 'Inspect the runners for wear — a shedding runner is a metal contamination risk.'],
            ['code' => 'TL-SHEET-ROLL', 'name' => 'Sheeter rollers & belts',       'status' => Tool::STATUS_IN_USE,      'dueInDays' => 21,   'description' => 'Clean between doughs and check roller gap. An uneven gap ruins lamination on one side.'],
            ['code' => 'TL-DEPOS-NOZ', 'name' => 'Depositor nozzles & seals',      'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 34,   'description' => 'Strip and sanitise after every dairy run. Seals perish — check before a cheesecake batch.'],
            ['code' => 'TL-COOL-BELT', 'name' => 'Cooling spiral belt',            'status' => Tool::STATUS_IN_USE,      'dueInDays' => 40,   'description' => 'Tension and track the belt. Product that tips on the spiral ends up on the floor.'],
            ['code' => 'TL-SLICE-BLD', 'name' => 'Slicer blade set',               'status' => Tool::STATUS_IN_USE,      'dueInDays' => 6,    'description' => 'Change blades weekly. A dull blade tears the crumb and shows as ragged slices.'],
            ['code' => 'TL-SEAL-JAW',  'name' => 'Bagger sealing jaws',            'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 25,   'description' => 'Clean and check jaw temperature — cool jaws give seals that split in the crate.'],
            ['code' => 'TL-TINS-700',  'name' => 'Bread tins 700 g (set of 200)',  'status' => Tool::STATUS_IN_USE,      'dueInDays' => 55,   'description' => 'Re-season when loaves start sticking. Retire any tin with a flaking coating.'],
            ['code' => 'TL-TRAYS',     'name' => 'Baking trays & silicone mats',   'status' => Tool::STATUS_IN_USE,      'dueInDays' => 48,   'description' => 'Discard cracked mats — a fragment in a loaf is a recall, not a complaint.'],
            ['code' => 'TL-SILO-FILT', 'name' => 'Flour silo filter & sifter',     'status' => Tool::STATUS_AVAILABLE,   'dueInDays' => 30,   'description' => 'Change the filter and inspect the sieve mesh. The sieve is the last barrier before the mixer.'],
            ['code' => 'TL-PEEL-OLD',  'name' => 'Stone oven peels (legacy)',      'status' => Tool::STATUS_RETIRED,     'dueInDays' => null, 'description' => 'From the wood-fired oven decommissioned two years ago. Kept for the shop window.'],
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

        $schedules['steam'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Monthly Steam Generator Descale'],
            [
                'tool_id' => $tools['TL-OVEN-STEAM']?->id,
                'description' => 'Descale the deck oven steam generator and check nozzle flow',
                'line_id' => $lines['BAKE']->id,
                'workstation_id' => $workstations['OVEN-01']->id,
                'event_type' => 'planned',
                'frequency' => 'monthly',
                'interval_value' => 1,
                'preferred_time' => '13:00',
                'next_due_at' => now()->addDays(3)->setTime(13, 0),
                'is_active' => true,
            ]
        );

        $schedules['divider'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Weekly Divider Strip & Grease'],
            [
                'tool_id' => $tools['TL-DIV-PIST']?->id,
                'description' => 'Strip the divider pistons, clean, grease and check divide weight repeatability',
                'line_id' => $lines['PROOF']->id,
                'workstation_id' => $workstations['DIV-01']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '14:00',
                'next_due_at' => now()->next('Monday')->setTime(14, 0),
                'is_active' => true,
            ]
        );

        $schedules['slicer'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Weekly Slicer Blade Change'],
            [
                'tool_id' => $tools['TL-SLICE-BLD']?->id,
                'description' => 'Replace slicer blades and sanitise the cabinet',
                'line_id' => $lines['PACK']->id,
                'workstation_id' => $workstations['SLICE-01']->id,
                'event_type' => 'planned',
                'frequency' => 'weekly',
                'interval_value' => 1,
                'preferred_time' => '15:00',
                'next_due_at' => now()->addDays(5)->setTime(15, 0),
                'is_active' => true,
            ]
        );

        $schedules['silo'] = MaintenanceSchedule::updateOrCreate(
            ['name' => 'Quarterly Silo Filter & Sieve Check'],
            [
                'tool_id' => $tools['TL-SILO-FILT']?->id,
                'description' => 'Change the silo filter and inspect the sieve mesh for damage',
                'line_id' => $lines['DOUGH']->id,
                'workstation_id' => $workstations['SILO-01']->id,
                'event_type' => 'inspection',
                'frequency' => 'quarterly',
                'interval_value' => 1,
                'preferred_time' => '13:30',
                'next_due_at' => now()->addDays(30)->setTime(13, 30),
                'is_active' => true,
            ]
        );

        $events = [
            [
                'title' => 'Slicer Blade Change (completed)',
                'event_type' => 'planned',
                'status' => 'completed',
                'line_id' => $lines['PACK']->id,
                'workstation_id' => $workstations['SLICE-01']->id,
                'schedule_id' => $schedules['slicer']->id,
                'scheduled_at' => now()->subDays(2)->setTime(15, 0),
                'scheduled_end_at' => now()->subDays(2)->setTime(16, 0),
                'description' => 'Blades replaced, cabinet sanitised. Slice quality back to normal.',
            ],
            [
                'title' => 'Proofer Probe Calibration (completed)',
                'event_type' => 'inspection',
                'status' => 'completed',
                'line_id' => $lines['PROOF']->id,
                'workstation_id' => $workstations['PROOF-01']->id,
                'schedule_id' => null,
                'scheduled_at' => now()->subWeek()->setTime(13, 0),
                'scheduled_end_at' => now()->subWeek()->setTime(14, 0),
                'description' => 'Humidity probe was reading 6% high. Recalibrated against the reference.',
            ],
            [
                'title' => 'Divider Repair (in progress)',
                'event_type' => 'corrective',
                'status' => 'in_progress',
                'line_id' => $lines['PROOF']->id,
                'workstation_id' => $workstations['DIV-01']->id,
                'schedule_id' => $schedules['divider']->id,
                'scheduled_at' => now()->subHours(3),
                'scheduled_end_at' => now()->addHours(1),
                'description' => 'Pistons stripped after sticking. Hand-dividing until it is back.',
            ],
            [
                'title' => 'Steam Generator Descale (due)',
                'event_type' => 'planned',
                'status' => 'pending',
                'line_id' => $lines['BAKE']->id,
                'workstation_id' => $workstations['OVEN-01']->id,
                'schedule_id' => $schedules['steam']->id,
                'scheduled_at' => now()->addDays(3)->setTime(13, 0),
                'scheduled_end_at' => now()->addDays(3)->setTime(16, 0),
                'description' => 'Monthly descale — crust quality has been falling off.',
            ],
            [
                'title' => 'Oven Thermostat Calibration (scheduled)',
                'event_type' => 'inspection',
                'status' => 'pending',
                'line_id' => $lines['BAKE']->id,
                'workstation_id' => $workstations['OVEN-01']->id,
                'schedule_id' => null,
                'scheduled_at' => now()->addDays(10)->setTime(13, 0),
                'scheduled_end_at' => now()->addDays(10)->setTime(15, 0),
                'description' => 'Engineer booked after the deck was found running under set point.',
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
                'name' => 'Flour Goods-in Check',
                'material' => 'MAT-FLOUR-750',
                'description' => 'Goods-in check for flour: sacks intact, dry, within date, certificate present',
                'criteria' => ['sack_integrity', 'moisture', 'best_before', 'delivery_certificate'],
            ],
            [
                'name' => 'Dairy Cold Chain Check',
                'material' => 'MAT-BUTTER',
                'description' => 'Temperature and condition check on butter and dairy at delivery',
                'criteria' => ['delivery_temperature', 'packaging_intact', 'best_before'],
            ],
            [
                'name' => 'Egg Goods-in Check',
                'material' => 'MAT-EGG',
                'description' => 'Pasteurised egg: temperature, seal and lot traceability',
                'criteria' => ['delivery_temperature', 'seal_intact', 'lot_number_legible'],
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
                // Changeovers between doughs are quick; the ovens rarely stop.
                $downtime = mt_rand(10, 45);
                $operating = $planned - $downtime;
                $totalProduced = mt_rand(400, 2600);
                $scrap = mt_rand(0, (int) max(1, $totalProduced * 0.03));
                $good = $totalProduced - $scrap;

                $availability = round($operating / max($planned, 1) * 100, 1);
                $performance = round(mt_rand(72, 97), 1);
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
