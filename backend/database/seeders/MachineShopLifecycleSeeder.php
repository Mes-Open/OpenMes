<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\BomItem;
use App\Models\Issue;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\PalletMovement;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Models\ScrapEntry;
use App\Models\SerialUnit;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Lot\BatchReleaseService;
use App\Services\Lot\LotService;
use App\Services\Material\StockMovementService;
use App\Services\Production\DowntimeService;
use App\Services\Production\QualityCheckService;
use App\Services\Quality\InboundInspectionService;
use App\Services\Traceability\SerialTraceService;
use App\Services\Warehouse\StockDocumentService;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderSnapshotService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL demo completion for MachineShopDemoSeeder (run it right after that one).
 *
 *   php artisan db:seed --class=MachineShopDemoSeeder
 *   php artisan db:seed --class=MachineShopLifecycleSeeder
 *
 * MachineShopDemoSeeder writes master data and an order book but leaves the order
 * history hollow. This seeder:
 *
 *  1. re-plans the whole order book onto the CURRENT week (Mon 06:00 onwards), one
 *     lane per product, two shifts a day; what does not fit flows into next week,
 *  2. derives each order's state from that plan and the real clock — finished
 *     Monday/Tuesday, running right now (two of them blocked), released or pending
 *     for the rest of the week,
 *  3. replays the history through the application's own services with the clock
 *     wound back (Carbon::setTestNow): sub-assembly orders, batches, steps, lot
 *     allocation + genealogy, scrap, downtime, quality checks, releases, finished
 *     goods lots, pallets and their movements, serial units with a record at every
 *     station, stock documents — so everything links up the way production makes it.
 *
 * Not idempotent: it refuses to run once any batch exists. Re-seed with migrate:fresh.
 */
class MachineShopLifecycleSeeder extends Seeder
{
    /** Setup / run-per-unit minutes by station code prefix. */
    private const STATION_TIMES = [
        'SAW' => [15, 0.4], 'DEBUR' => [5, 0.2], 'BARFEED' => [20, 0.2], 'LATHE' => [25, 0.8],
        'MILL' => [30, 1.0], 'FURN' => [20, 0.15], 'QUENCH' => [15, 0.1], 'TEMPER' => [15, 0.1],
        'GRIND' => [25, 0.6], 'CMM' => [10, 0.3], 'PACK' => [5, 0.1],
    ];

    private const SCRAP_STATIONS = ['SAW', 'LATHE', 'MILL', 'GRIND', 'FURN', 'QUENCH'];

    /** Products whose units carry their own serial number. */
    private const SERIALISED = ['SHAFT40', 'PINION18', 'KIT40'];

    private const MAX_BATCH = 60;

    private const SHIFT_START = 6;

    private const SHIFT_END = 22;

    private Carbon $realNow;

    private Carbon $weekStart;

    private Carbon $weekEnd;

    private WorkOrderService $orders;

    private BatchService $batches;

    private LotService $lots;

    private StockDocumentService $docs;

    private WorkOrderStockDocumentService $woDocs;

    private QualityCheckService $quality;

    private DowntimeService $downtime;

    private SerialTraceService $serials;

    private BatchReleaseService $release;

    private InboundInspectionService $inspections;

    private WorkOrderSnapshotService $snapshots;

    private StockMovementService $movements;

    private User $admin;

    private User $supervisor;

    private User $inspector;

    /** @var array<string, User> line code => operator */
    private array $operators = [];

    /** @var array<int, string> workstation id => line code */
    private array $lineOfStation = [];

    /** @var array<int, string> workstation id => code */
    private array $stationCode = [];

    /** @var array<int, int> product type id => finished-goods material id */
    private array $fgMaterial = [];

    private Warehouse $raw;

    private Warehouse $wip;

    private Warehouse $fg;

    private int $saCounter = 0;

    /** @var array<int, array{issue_id: int, wo: int, status: string}> */
    private array $parkedIssues = [];

    private float $t0;

    public function run(): void
    {
        if (Batch::exists()) {
            $this->command?->warn('Batches already exist — lifecycle seeder runs once on a fresh MachineShop seed. Skipping.');

            return;
        }
        if (! WorkOrder::where('order_no', 'like', 'WO-MS-%')->exists()) {
            $this->command?->error('Run MachineShopDemoSeeder first.');

            return;
        }

        mt_srand(4242);
        $this->t0 = microtime(true);
        $this->realNow = now()->copy()->startOfMinute();
        $this->weekStart = $this->realNow->copy()->startOfWeek(Carbon::MONDAY)->setTime(self::SHIFT_START, 0);
        $this->weekEnd = $this->weekStart->copy()->addDays(6)->setTime(self::SHIFT_END, 0);
        config(['openmmes.warehouse.auto_documents' => false]);

        $this->orders = app(WorkOrderService::class);
        $this->batches = app(BatchService::class);
        $this->lots = app(LotService::class);
        $this->docs = app(StockDocumentService::class);
        $this->woDocs = app(WorkOrderStockDocumentService::class);
        $this->quality = app(QualityCheckService::class);
        $this->downtime = app(DowntimeService::class);
        $this->serials = app(SerialTraceService::class);
        $this->release = app(BatchReleaseService::class);
        $this->inspections = app(InboundInspectionService::class);
        $this->snapshots = app(WorkOrderSnapshotService::class);
        $this->movements = app(StockMovementService::class);

        try {
            DB::transaction(function () {
                $this->resolveActors();
                $this->settings();
                $this->newProducts();
                $this->expandRoutings();
                $this->enrichBoms();
                $this->planningTimes();
                $this->dropUnusedMaterials();
                $this->finishedGoodsMaterials();
                $this->kitBom();
                $this->newOrders();
                $this->alignLinesWithRouting();
                $this->planWeek();
                $this->reassignIssues();
                $this->warehouses();
                $this->lotSequences();
                $this->openingStock();
                $this->log('stock received');
                $this->snapshotOrderBook();
                $this->log('order book snapshotted');
                $this->replayHistory();
                $this->planUpcomingSubassemblies();
                $this->pinHistoricalIssues();
                $this->settleTimestamps();
                $this->settleMaintenance();
            });
        } finally {
            Carbon::setTestNow();
        }

        $this->log('done');
    }

    private function log(string $msg): void
    {
        $this->command?->line(sprintf('  [%6.1fs] %s', microtime(true) - $this->t0, $msg));
    }

    // ── setup ────────────────────────────────────────────────────────────────

    private function resolveActors(): void
    {
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->supervisor = User::where('username', 'robert.nowak')->first() ?? $this->admin;
        $this->inspector = User::where('username', 'ewa.baran')->first() ?? $this->supervisor;

        foreach (['SAW' => 'tomasz.lis', 'TURN' => 'tomasz.lis', 'MILL' => 'marta.kowal', 'HEAT' => 'piotr.zieba', 'GRIND' => 'piotr.zieba', 'QC' => 'ewa.baran'] as $line => $username) {
            $this->operators[$line] = User::where('username', $username)->first() ?? $this->supervisor;
        }

        foreach (Workstation::with('line')->get() as $ws) {
            $this->lineOfStation[$ws->id] = $ws->line?->code ?? '';
            $this->stationCode[$ws->id] = $ws->code;
        }
    }

    private function settings(): void
    {
        foreach ([
            'lot_tracking_enabled' => true,
            'lot_picking_strategy' => 'fifo',
            'onboarding_completed' => true,
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
    }

    private function planningTimes(): void
    {
        foreach (DB::table('template_steps')->get() as $step) {
            [$setup, $run] = $this->stationTimes($step->workstation_id);
            DB::table('template_steps')->where('id', $step->id)->update([
                'setup_time_minutes' => $setup,
                'run_time_per_unit_minutes' => $run,
            ]);
        }
    }

    /** @return array{0: int, 1: float} */
    private function stationTimes(?int $workstationId): array
    {
        $code = $workstationId ? ($this->stationCode[$workstationId] ?? '') : '';
        foreach (self::STATION_TIMES as $prefix => $times) {
            if (str_starts_with($code, $prefix)) {
                return $times;
            }
        }

        return [10, 0.0];
    }

    private function prefixOf(?int $workstationId): string
    {
        $code = $workstationId ? ($this->stationCode[$workstationId] ?? '') : '';
        foreach (array_keys(self::STATION_TIMES) as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return $prefix;
            }
        }

        return '';
    }

    /** A purchased material nothing is made from is dead data. */
    private function dropUnusedMaterials(): void
    {
        foreach (Material::where('is_manufactured', false)->whereDoesntHave('bomItems')->get() as $material) {
            if (DB::table('inspection_plans')->where('material_id', $material->id)->exists()) {
                continue;
            }
            DB::table('material_lots')->where('material_id', $material->id)->delete();
            DB::table('materials')->where('id', $material->id)->delete();
            $this->log("removed unused material {$material->code}");
        }
    }

    /** Finished parts get a material record so their output lots can be traced. */
    private function finishedGoodsMaterials(): void
    {
        $typeId = DB::table('material_types')->where('code', 'finished_good')->value('id')
            ?? DB::table('material_types')->insertGetId(['code' => 'finished_good', 'name' => 'Finished Product', 'created_at' => now(), 'updated_at' => now()]);

        foreach (ProductType::where('code', 'not like', 'SA_%')->get() as $pt) {
            $template = ProcessTemplate::where('product_type_id', $pt->id)->where('is_active', true)->orderByDesc('version')->first();
            $material = Material::create([
                'code' => 'FG-'.$pt->code,
                'name' => $pt->name,
                'material_type_id' => $typeId,
                'unit_of_measure' => 'pcs',
                'tracking_type' => in_array($pt->code, self::SERIALISED, true) ? 'serial' : 'batch',
                'is_active' => true,
                'is_manufactured' => true,
                'producing_process_template_id' => $template?->id,
                'stock_quantity' => 0,
            ]);
            $this->fgMaterial[$pt->id] = $material->id;
        }
    }

    private function alignLinesWithRouting(): void
    {
        $lineIds = Line::pluck('id', 'code');

        foreach (ProcessTemplate::with('steps')->get() as $template) {
            $sequence = [];
            foreach ($template->steps->sortBy('step_number') as $step) {
                $code = $step->workstation_id ? ($this->stationCode[$step->workstation_id] ?? '') : '';
                if ($code === '' || str_starts_with($code, 'DEBUR')) {
                    continue;
                }
                $sequence[] = $this->lineOfStation[$step->workstation_id];
            }
            if ($sequence === []) {
                continue;
            }

            $counts = array_count_values(array_filter($sequence, fn ($l) => $l !== 'QC')) ?: array_count_values($sequence);
            $max = max($counts);
            $primary = collect($sequence)->first(fn ($l) => ($counts[$l] ?? 0) === $max);
            $secondary = array_values(array_diff(array_unique($sequence), [$primary]));

            $productTypeId = $template->product_type_id;
            DB::table('line_product_type')->where('product_type_id', $productTypeId)->delete();
            foreach (array_unique([$primary, ...$secondary]) as $code) {
                DB::table('line_product_type')->insert(['line_id' => $lineIds[$code], 'product_type_id' => $productTypeId]);
            }
            DB::table('work_orders')->where('product_type_id', $productTypeId)->update(['line_id' => $lineIds[$primary]]);
            $this->secondaryLines[$productTypeId] = array_map(fn ($c) => $lineIds[$c], $secondary);
        }
    }

    /** @var array<int, array<int, int>> */
    private array $secondaryLines = [];

    // ── extra products: every line gets its own orders ──────────────────────

    /**
     * Two products the base demo lacks, so the grinding and the inspection &
     * packing lines run orders of their own: a ground guide pin (most of its
     * routing is on the grinders) and a gearbox repair kit picked and boxed on
     * the QC line from finished shafts, pinions and bushings.
     */
    private function newProducts(): void
    {
        $ws = Workstation::pluck('id', 'code');
        $defs = [
            'GUIDEPIN' => ['Hardened Guide Pin Ø20', 'Through-hardened 100Cr6 guide pin, ground Ø20 g6, end faces ground parallel', 'Guide pin — hardened & ground', [
                [1, 'Saw to length', 'SAW-02'], [2, 'Turn & chamfer', 'LATHE-02'], [3, 'Harden', 'FURN-01'], [4, 'Quench', 'QUENCH-01'],
                [5, 'Rough grind Ø20', 'GRIND-CYL-01'], [6, 'Grind end faces', 'GRIND-SUR-01'], [7, 'Finish grind Ø20 g6', 'GRIND-CYL-01'],
                [8, 'CMM inspection', 'CMM-01'], [9, 'Preserve & pack', 'PACK-01'],
            ]],
            'KIT40' => ['Gearbox Repair Kit GRK-40', 'Service kit: drive shaft Ø40, pinion Z18 and two bronze bushings, boxed and labelled', 'Repair kit — pick, verify, pack', [
                [1, 'Pick & verify components', 'CMM-01'], [2, 'Kit assembly & pack', 'PACK-01'],
            ]],
        ];
        foreach ($defs as $code => [$name, $desc, $tplName, $steps]) {
            $pt = ProductType::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'unit_of_measure' => 'pcs', 'is_active' => true]);
            $tpl = ProcessTemplate::updateOrCreate(['product_type_id' => $pt->id, 'version' => 1], ['name' => $tplName, 'is_active' => true]);
            foreach ($steps as [$n, $stepName, $wsCode]) {
                DB::table('template_steps')->updateOrInsert(
                    ['process_template_id' => $tpl->id, 'step_number' => $n, 'deleted_at' => null],
                    ['name' => $stepName, 'workstation_id' => $ws[$wsCode] ?? null, 'created_at' => now()],
                );
            }
            ProductRevision::updateOrCreate(['product_type_id' => $pt->id, 'revision_code' => 'A'], [
                'description' => 'Initial release', 'change_reason' => 'New product introduced this quarter.',
                'lifecycle_status' => \App\Enums\RevisionLifecycle::Released, 'process_template_id' => $tpl->id,
                'external_ref' => "DRW-{$code}-A", 'effective_from' => now()->subDays(40), 'released_at' => now()->subDays(40),
                'released_by_id' => $this->supervisor->id,
            ]);
        }
    }

    /**
     * Full routings, about ten operations each — the checks, set-ups, deburring,
     * washing, measuring and marking a real part goes through, not just its two
     * or three headline operations. Existing steps keep their row (BOM lines
     * point at them) and are only renumbered; the rest are added around them.
     */
    private function expandRoutings(): void
    {
        $routings = [
            'SA_BILLET' => [['Heat number check', 'DEBUR-01'], ['Bar loading', 'SAW-01'], ['Saw to length', 'SAW-01'], ['Length check', 'DEBUR-01'],
                ['Face & deburr', 'DEBUR-01'], ['Centre mark', 'DEBUR-01'], ['Stamp heat number', 'DEBUR-01'], ['Billet inspection', 'CMM-01'], ['Stage to WIP rack', 'DEBUR-01']],
            'SA_ROUGH' => [['Billet check & chuck set-up', 'LATHE-01'], ['Rough turn', 'LATHE-01'], ['Flip & rough turn side B', 'LATHE-01'], ['Centre drill', 'LATHE-01'],
                ['Turn journals to pre-grind size', 'LATHE-02'], ['Turn keyway seat', 'LATHE-02'], ['Chamfer & deburr', 'DEBUR-01'], ['In-process measurement', 'CMM-01'],
                ['Wash', 'DEBUR-01'], ['Stage for heat treatment', 'DEBUR-01']],
            'SA_HARD' => [['Wash & degrease', 'DEBUR-01'], ['Fixture loading', 'FURN-01'], ['Furnace load', 'FURN-01'], ['Austenitise soak', 'FURN-01'], ['Quench', 'QUENCH-01'],
                ['Wash off quench oil', 'DEBUR-01'], ['Temper', 'TEMPER-01'], ['Straightening', 'DEBUR-01'], ['Hardness test', 'CMM-01'], ['Crack test (MPI)', 'CMM-01']],
            'SA_FLBLANK' => [['Plate check', 'DEBUR-01'], ['Mark out', 'DEBUR-01'], ['Cut blank', 'SAW-02'], ['Grind slag', 'DEBUR-01'], ['Cool & check', 'DEBUR-01'],
                ['Stamp heat number', 'DEBUR-01'], ['Blank inspection', 'CMM-01'], ['Stage to WIP rack', 'DEBUR-01']],
            'SHAFT40' => [['Drawing & revision check', null], ['Blank check', 'DEBUR-01'], ['Finish turning', 'LATHE-01'], ['Chamfer & deburr', 'DEBUR-01'], ['Keyway milling', 'MILL-01'],
                ['Deburr keyway', 'DEBUR-01'], ['Cylindrical grinding', 'GRIND-CYL-01'], ['Wash', 'DEBUR-01'], ['CMM inspection', 'CMM-01'], ['Preserve & pack', 'PACK-01']],
            'FLANGE150' => [['Blank check', 'DEBUR-01'], ['Face & bore', 'LATHE-02'], ['Chamfer bore', 'LATHE-02'], ['Bolt circle', 'MILL-02'], ['Deburr bolt holes', 'DEBUR-01'],
                ['Sealing face', 'LATHE-02'], ['Wash', 'DEBUR-01'], ['Sealing face flatness', 'CMM-01'], ['Inspection', 'CMM-01'], ['Preserve & pack', 'PACK-01']],
            'HOUSING' => [['Casting inspection', 'DEBUR-01'], ['Datum faces', 'MILL-02'], ['Bore machining', 'MILL-03'], ['Drill & tap', 'MILL-03'], ['Thread gauge check', 'CMM-01'],
                ['Wash & deburr', 'DEBUR-01'], ['Leak test', 'CMM-01'], ['CMM inspection', 'CMM-01'], ['Paint touch-up', 'DEBUR-01'], ['Preserve & pack', 'PACK-01']],
            'PINION18' => [['Billet check', 'DEBUR-01'], ['Turn blank', 'LATHE-03'], ['Bore & face', 'LATHE-03'], ['Tooth cutting', 'MILL-04'], ['Deburr teeth', 'DEBUR-01'],
                ['Case hardening', 'FURN-01'], ['Bore grinding', 'GRIND-CYL-01'], ['Wash', 'DEBUR-01'], ['Inspection', 'CMM-01'], ['Preserve & pack', 'PACK-01']],
            'BUSHING' => [['Bar setup', 'BARFEED-01'], ['Turn & bore', 'LATHE-03'], ['Ream bore H7', 'LATHE-03'], ['Lubrication grooves', 'LATHE-03'], ['Part-off & face', 'LATHE-03'],
                ['Deburr & inspect', 'DEBUR-01'], ['Wash', 'DEBUR-01'], ['Dimensional check', 'CMM-01'], ['Oil & bag', 'PACK-01'], ['Pack', 'PACK-01']],
            'MANIFOLD' => [['Saw & square', 'MILL-04'], ['Cross drilling', 'MILL-01'], ['Port machining', 'MILL-01'], ['Deburr cross holes', 'DEBUR-01'], ['Thread check', 'CMM-01'],
                ['Wash & blow-out', 'DEBUR-01'], ['Flush & test', 'CMM-01'], ['Laser marking', 'PACK-01'], ['Final visual', 'CMM-01'], ['Pack', 'PACK-01']],
            'BASEPLATE' => [['Blank check', 'DEBUR-01'], ['Face both sides', 'MILL-02'], ['Drill pattern', 'MILL-04'], ['Tap M12', 'MILL-04'], ['Deburr', 'DEBUR-01'],
                ['Flatness check', 'CMM-01'], ['Inspection', 'CMM-01'], ['Wash', 'DEBUR-01'], ['Corrosion protection', 'PACK-01'], ['Pack', 'PACK-01']],
            'GUIDEPIN' => [['Saw to length', 'SAW-02'], ['Turn & chamfer', 'LATHE-02'], ['Harden', 'FURN-01'], ['Quench', 'QUENCH-01'], ['Rough grind Ø20', 'GRIND-CYL-01'],
                ['Grind end faces', 'GRIND-SUR-01'], ['Finish grind Ø20 g6', 'GRIND-CYL-01'], ['Wash', 'DEBUR-01'], ['CMM inspection', 'CMM-01'], ['Preserve & pack', 'PACK-01']],
            'KIT40' => [['Pick & verify components', 'CMM-01'], ['Check serials against order', 'CMM-01'], ['Visual check', 'CMM-01'], ['Oil components', 'PACK-01'],
                ['VCI wrap', 'PACK-01'], ['Kit assembly & pack', 'PACK-01'], ['Label box', 'PACK-01'], ['Final check & photo', 'CMM-01']],
        ];
        $ws = Workstation::pluck('id', 'code');

        foreach ($routings as $productCode => $steps) {
            $template = ProcessTemplate::where('product_type_id', ProductType::where('code', $productCode)->value('id'))
                ->where('is_active', true)->orderByDesc('version')->first();
            if (! $template) {
                continue;
            }
            $live = DB::table('template_steps')->where('process_template_id', $template->id)->whereNull('deleted_at');
            // Out of the way first: (template, step_number) is unique.
            (clone $live)->update(['step_number' => DB::raw('step_number + 1000')]);
            $existing = (clone $live)->get()->keyBy('name');

            foreach ($steps as $i => [$name, $wsCode]) {
                $row = $existing->pull($name);
                if ($row) {
                    DB::table('template_steps')->where('id', $row->id)->update(['step_number' => $i + 1]);
                } else {
                    DB::table('template_steps')->insert([
                        'process_template_id' => $template->id, 'step_number' => $i + 1, 'name' => $name,
                        'workstation_id' => $wsCode ? ($ws[$wsCode] ?? null) : null, 'created_at' => now(),
                    ]);
                }
            }
            // Anything the routing did not name stays, at the end.
            foreach ($existing->values() as $j => $row) {
                DB::table('template_steps')->where('id', $row->id)->update(['step_number' => count($steps) + $j + 1]);
            }
        }
    }

    /** BOMs that name what each step really uses (and the materials they need). */
    private function enrichBoms(): void
    {
        $type = fn (string $c) => DB::table('material_types')->where('code', $c)->value('id');
        foreach ([
            ['MAT-BAR-100CR6', 'Bearing steel bar 100Cr6, Ø22', 'raw_material', 'm', 19.50, 'Ovako'],
            ['MAT-INSERT-GROOVE', 'Grooving insert MGMN 300-M', 'auxiliary', 'pcs', 21.50, 'Sandvik Polska'],
            ['MAT-ABRASIVE', 'Deburring abrasive pad, medium', 'auxiliary', 'pcs', 2.10, 'Mirka'],
            ['MAT-CARTON', 'Carton box 300x200x150', 'packaging', 'pcs', 3.40, 'Smurfit Kappa'],
            ['MAT-LABEL', 'Product label 100x50, thermal', 'packaging', 'pcs', 0.08, 'Zebra Supplies'],
        ] as [$code, $name, $t, $uom, $price, $supplier]) {
            Material::firstOrCreate(['code' => $code], [
                'name' => $name, 'material_type_id' => $type($t), 'unit_of_measure' => $uom, 'tracking_type' => 'batch',
                'is_active' => true, 'is_manufactured' => false, 'unit_price' => $price, 'price_currency' => 'PLN',
                'supplier_name' => $supplier, 'stock_quantity' => 0,
            ]);
        }

        $this->addBom('BUSHING', [
            ['MAT-COOLANT', 0.02, 'during', 'Turn & bore', 0], ['MAT-INSERT-GROOVE', 0.02, 'during', 'Lubrication grooves', 0],
            ['MAT-ABRASIVE', 0.05, 'during', 'Deburr & inspect', 0], ['MAT-CARTON', 1 / 12, 'end', 'Pack', 0], ['MAT-LABEL', 1 / 12, 'end', 'Pack', 0],
        ]);
        $this->addBom('GUIDEPIN', [
            ['MAT-BAR-100CR6', 0.065, 'start', 'Saw to length', 2], ['MAT-INSERT-CNMG', 0.01, 'during', 'Turn & chamfer', 0],
            ['MAT-QUENCH-OIL', 0.05, 'during', 'Quench', 0], ['MAT-COOLANT', 0.01, 'during', 'Rough grind Ø20', 0],
            ['MAT-VCI-BAG', 0.1, 'end', 'Preserve & pack', 0], ['MAT-CARTON', 0.02, 'end', 'Preserve & pack', 0], ['MAT-LABEL', 0.02, 'end', 'Preserve & pack', 0],
        ]);
    }

    /** The kit is made of finished goods, so its BOM waits for their materials. */
    private function kitBom(): void
    {
        $this->addBom('KIT40', [
            ['FG-SHAFT40', 1, 'start', 'Pick & verify components', 0], ['FG-PINION18', 1, 'start', 'Pick & verify components', 0],
            ['FG-BUSHING', 2, 'start', 'Pick & verify components', 0], ['MAT-VCI-BAG', 3, 'end', 'VCI wrap', 0],
            ['MAT-CARTON', 1, 'end', 'Kit assembly & pack', 0], ['MAT-LABEL', 1, 'end', 'Label box', 0],
        ]);
    }

    /** @param array<int, array{0: string, 1: float, 2: string, 3: string, 4: float}> $items step given by name */
    private function addBom(string $productCode, array $items): void
    {
        $template = $this->activeTemplate(ProductType::where('code', $productCode)->value('id'));
        if (! $template) {
            return;
        }
        $sort = (int) DB::table('bom_items')->where('process_template_id', $template->id)->max('sort_order');
        foreach ($items as [$code, $qpu, $at, $stepName, $scrap]) {
            $materialId = Material::where('code', $code)->value('id');
            if (! $materialId) {
                continue;
            }
            BomItem::updateOrCreate(['process_template_id' => $template->id, 'material_id' => $materialId], [
                'template_step_id' => DB::table('template_steps')->where('process_template_id', $template->id)->whereNull('deleted_at')->where('name', $stepName)->value('id'),
                'quantity_per_unit' => round($qpu, 4), 'scrap_percentage' => $scrap, 'consumed_at' => $at, 'per' => 'unit', 'sort_order' => ++$sort,
            ]);
        }
    }

    /**
     * Orders for the new products, slotted relative to the real clock so a
     * reseed on any day of the week gives finished, running and upcoming work.
     */
    private function newOrders(): void
    {
        $customers = DB::table('customers')->pluck('id')->all();
        $plan = [
            'GUIDEPIN' => [500, [[-50, 60], [-30, 40], [-14, 50], [-3, 60], [6, 80], [28, 40], [52, 60], [76, 50], [100, 60]]],
            'KIT40' => [600, [[-6, 12], [-3, 10], [-0.4, 15], [4, 15], [24, 10], [48, 12], [72, 10]]],
            // Make-to-stock replenishment, so saw and heat treatment have work on the floor right now.
            'SA_BILLET' => [700, [[-1.5, 40], [30, 60]]],
            'SA_HARD' => [710, [[-1.2, 12], [50, 30]]],
        ];
        foreach ($plan as $code => [$base, $slots]) {
            $pt = ProductType::where('code', $code)->firstOrFail();
            $revisionId = ProductRevision::where('product_type_id', $pt->id)->where('lifecycle_status', 'released')->value('id');
            foreach ($slots as $i => [$hours, $qty]) {
                $start = $this->realNow->copy()->addMinutes((int) round($hours * 60));
                $created = $start->copy()->subDays(3);
                Carbon::setTestNow($created->lt($this->realNow) ? $created : $this->realNow);
                $customer = str_starts_with($code, 'SA_') ? null : ($customers[($i + ($code === 'KIT40' ? 2 : 0)) % max(1, count($customers))] ?? null);
                $this->orders->createWorkOrder([
                    'order_no' => sprintf('WO-MS-%04d', $base + $i + 1),
                    'product_type_id' => $pt->id,
                    'product_revision_id' => $revisionId,
                    'planned_qty' => $qty,
                    'priority' => mt_rand(1, 5),
                    'customer_id' => $customer,
                    'customer_order_no' => str_starts_with($code, 'SA_') ? null : sprintf('PO-2026-%d', ($code === 'KIT40' ? 3600 : 3500) + $i + 1),
                    'due_date' => $start->copy()->addDays(2)->toDateString(),
                    'planned_start_at' => $start,
                    'description' => match ($code) {
                        'KIT40' => 'Service kits for the aftermarket channel',
                        'GUIDEPIN' => 'Guide pins for press tooling',
                        default => 'Replenish semi-finished stock',
                    },
                    'extra_data' => ['fixed_slot' => true],
                ]);
            }
        }
        Carbon::setTestNow();
    }

    private function isSubassembly(?Material $material): bool
    {
        return $material && $material->is_manufactured && str_starts_with($material->code, 'SA-');
    }

    /** @var array<int, int> serial units already built into a kit */
    private array $usedUnits = [];

    /**
     * A kit carries the very shaft and pinion that went into it — by serial,
     * taken from the finished lots picked for the kit batch — plus the bushing lot.
     */
    private function bindKitComponents(SerialUnit $kit, BatchStep $step, User $operator): void
    {
        $picks = DB::table('allocation_lot_picks as p')
            ->join('material_allocations as a', 'a.id', '=', 'p.material_allocation_id')
            ->join('material_lots as l', 'l.id', '=', 'p.material_lot_id')
            ->join('materials as m', 'm.id', '=', 'l.material_id')
            ->where('a.batch_id', $step->batch_id)
            ->whereIn('m.code', ['FG-SHAFT40', 'FG-PINION18', 'FG-BUSHING'])
            ->orderBy('p.id')->get(['m.code', 'l.lot_number', 'l.source_batch_id']);
        $ctx = ['batch_step_id' => $step->id, 'workstation_id' => $step->workstation_id];

        foreach ($picks->groupBy('code') as $code => $rows) {
            if ($code === 'FG-BUSHING') {
                $this->serials->bindComponent($kit, $rows->first()->lot_number, $operator, $ctx + ['quantity' => 2]);

                continue;
            }
            $unit = SerialUnit::whereIn('batch_id', $rows->pluck('source_batch_id')->filter())
                ->where('status', SerialUnit::STATUS_COMPLETED)
                ->whereNotIn('id', $this->usedUnits)->orderBy('id')->first();
            if ($unit) {
                $this->usedUnits[] = $unit->id;
                $this->serials->bindComponent($kit, $unit->serial_no, $operator, $ctx);
                $unit->update(['pallet_id' => null]);
            } else {
                $this->serials->bindComponent($kit, $rows->first()->lot_number, $operator, $ctx);
            }
        }
    }

    // ── weekly plan ──────────────────────────────────────────────────────────

    /**
     * Re-plan every order onto this week: one lane per product, back to back in
     * the 06:00–22:00 window. The real clock then decides what is finished, what
     * is running and what is still to come.
     *
     * The backlog stays a backlog: an order the order book left undated ("bar
     * stock on order, cannot be dated until it lands") is waiting on something
     * the plan cannot promise, and dating it would contradict its own note.
     */
    private function planWeek(): void
    {
        $orders = WorkOrder::where('order_no', 'like', 'WO-MS-%')
            ->whereNotNull('planned_start_at')
            ->orderBy('planned_start_at')
            ->orderByDesc('priority')->orderBy('id')
            ->get();
        $lanes = $orders->pluck('product_type_id')->unique()->values();
        $cursor = [];
        foreach ($lanes as $i => $pt) {
            $cursor[$pt] = $this->weekStart->copy()->addMinutes($i * 20 + mt_rand(0, 10));
        }

        /** @var array<int, array{wo: WorkOrder, start: Carbon, end: Carbon, minutes: float}> $plan */
        $plan = [];
        foreach ($orders as $wo) {
            $template = $this->activeTemplate($wo->product_type_id);
            $qty = (float) $wo->planned_qty;
            $nBatches = max(1, (int) ceil($qty / self::MAX_BATCH));
            $minutes = ($this->estimateTemplate($template, $qty) + ($nBatches - 1) * $this->estimateTemplate($template, 0)) * 1.1;

            // Orders with a slot of their own start there (or when the lane frees up).
            $from = $cursor[$wo->product_type_id];
            if (! empty($wo->extra_data['fixed_slot']) && $wo->planned_start_at?->gt($from)) {
                $from = $wo->planned_start_at->copy();
            }
            [$start, $end] = $this->place($from, $minutes);
            $cursor[$wo->product_type_id] = $end->copy()->addMinutes(mt_rand(15, 45));
            $plan[$wo->id] = ['wo' => $wo, 'start' => $start, 'end' => $end, 'minutes' => $minutes];
        }

        $this->keepLivePartsRunning($plan);

        $counts = [];
        foreach ($plan as ['wo' => $wo, 'start' => $start, 'end' => $end, 'minutes' => $minutes]) {
            $status = match (true) {
                $end->lte($this->realNow->copy()->subMinutes(30)) => WorkOrder::STATUS_DONE,
                $start->lte($this->realNow->copy()->subMinutes(10)) => WorkOrder::STATUS_IN_PROGRESS,
                $start->lt($this->realNow->copy()->addDays(2)) => WorkOrder::STATUS_ACCEPTED,
                default => WorkOrder::STATUS_PENDING,
            };
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            DB::table('work_orders')->where('id', $wo->id)->update([
                'status' => $status,
                'status_before_block' => null,
                'produced_qty' => 0,
                'completed_at' => null,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'shift_number' => $start->hour < 14 ? 1 : 2,
                'end_date' => $end->toDateString(),
                'end_shift_number' => $end->hour < 14 ? 1 : 2,
                'due_date' => $end->copy()->addDays(mt_rand(1, 3))->setTime(16, 0),
                'week_number' => (int) $start->isoWeek(),
                'month_number' => $start->month,
                'production_year' => $start->year,
            ]);

            DB::table('work_order_placements')->where('work_order_id', $wo->id)->delete();
            foreach ($this->secondaryLines[$wo->product_type_id] ?? [] as $i => $lineId) {
                $at = $start->copy()->addMinutes((int) ($minutes * ($i + 1) / (count($this->secondaryLines[$wo->product_type_id]) + 1)));
                DB::table('work_order_placements')->insert([
                    'work_order_id' => $wo->id, 'line_id' => $lineId,
                    'due_date' => $at->copy()->startOfDay(), 'shift_number' => $at->hour < 14 ? 1 : 2,
                    'created_at' => $this->realNow, 'updated_at' => $this->realNow,
                ]);
            }
        }
        $this->log('week planned: '.json_encode($counts));
    }

    /**
     * Keep an order of every part with a live problem running right now — and
     * running long enough to have reached the step the problem names.
     *
     * One lane per part leaves a part between orders now and then (and before its
     * first order on a Monday morning). The live problems then had nothing running
     * to be live on: they turned into history and nothing was blocked. Where that
     * is the case, the part's order around now is kept running instead — the one
     * that last started, or the one before it if that started too recently to have
     * reached the step — by starting it earlier and/or finishing it later, and the
     * orders after it in the lane move back behind it.
     *
     * @param  array<int, array{wo: WorkOrder, start: Carbon, end: Carbon, minutes: float}>  $plan
     */
    private function keepLivePartsRunning(array &$plan): void
    {
        $now = $this->realNow;
        $productIds = ProductType::pluck('id', 'code');

        foreach (MachineShopDemoSeeder::ISSUES as [, , $product, $stepName, , , $status]) {
            $productTypeId = $productIds[$product] ?? null;
            if (! $productTypeId || ! in_array($status, [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED], true)) {
                continue;
            }
            $lane = collect($plan)->filter(fn ($p) => $p['wo']->product_type_id === $productTypeId)
                ->sortBy(fn ($p) => $p['start']->getTimestamp())->keys()->values();
            if ($lane->isEmpty()) {
                continue;
            }

            // Long enough for a first batch to reach the step, with room for a stoppage.
            $lead = (int) ceil($this->minutesToStep($this->activeTemplate($productTypeId), $stepName, 10) * 1.25) + 90;
            $startBy = $now->copy()->subMinutes($lead);
            $runningUntil = $now->copy()->addHour();
            if ($lane->contains(fn ($id) => $plan[$id]['start']->lte($startBy) && $plan[$id]['end']->gt($runningUntil))) {
                continue;
            }

            $between = ! $lane->contains(fn ($id) => $plan[$id]['start']->lte($now) && $plan[$id]['end']->gt($now->copy()->subMinutes(30)));
            $i = $lane->filter(fn ($id) => $plan[$id]['start']->lte($now))->keys()->last() ?? 0;
            if ($i > 0 && $plan[$lane[$i]]['start']->gt($startBy)) {
                $i--;
            }
            $id = $lane[$i];
            $plan[$id]['start'] = $plan[$id]['start']->lte($startBy) ? $plan[$id]['start'] : $startBy->copy();
            $plan[$id]['end'] = $plan[$id]['end']->gt($runningUntil) ? $plan[$id]['end'] : $now->copy()->addHours(3);
            $this->log(sprintf('  kept %s (%s) running for its live problem — the lane was %s', $plan[$id]['wo']->order_no, $product, $between ? 'between orders' : 'not far enough in'));

            $cursor = $plan[$id]['end']->copy()->addMinutes(20);
            foreach ($lane->slice($i + 1) as $next) {
                if ($plan[$next]['start']->lt($cursor)) {
                    [$plan[$next]['start'], $plan[$next]['end']] = $this->place($cursor, $plan[$next]['minutes']);
                }
                $cursor = $plan[$next]['end']->copy()->addMinutes(20);
            }
        }
    }

    /** Planned minutes from a batch's start to the start of the named step. */
    private function minutesToStep(?ProcessTemplate $template, string $stepName, float $qty): float
    {
        $minutes = 0.0;
        foreach (($template?->steps ?? collect())->sortBy('step_number') as $step) {
            if ($step->name === $stepName) {
                break;
            }
            [$s, $r] = $this->stationTimes($step->workstation_id);
            $minutes += $s + $r * $qty + 8;
        }

        return $minutes;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function place(Carbon $cursor, float $minutes): array
    {
        $start = $this->intoShift($cursor->copy());
        $t = $start->copy();
        $left = $minutes;
        while (true) {
            $windowEnd = $t->copy()->setTime(self::SHIFT_END, 0);
            $avail = $t->diffInMinutes($windowEnd, false);
            if ($left <= $avail) {
                return [$start, $t->copy()->addSeconds((int) round($left * 60))];
            }
            $left -= max(0, $avail);
            $t = $t->copy()->addDay()->setTime(self::SHIFT_START, 0);
        }
    }

    private function intoShift(Carbon $t): Carbon
    {
        if ($t->hour < self::SHIFT_START) {
            return $t->setTime(self::SHIFT_START, 0);
        }
        if ($t->hour >= self::SHIFT_END) {
            return $t->addDay()->setTime(self::SHIFT_START, 0);
        }

        return $t;
    }

    private function estimateTemplate(?ProcessTemplate $template, float $qty): float
    {
        $minutes = 0;
        foreach ($template?->steps ?? [] as $step) {
            [$s, $r] = $this->stationTimes($step->workstation_id);
            $minutes += $s + $r * $qty + 8;
        }

        return $minutes;
    }

    /**
     * The order book's issues were written against order numbers whose state the
     * re-plan has changed. Move each issue onto an order of the part it names
     * (MachineShopDemoSeeder::ISSUES) whose state fits it: open problems onto
     * running orders (blocking ones then stop them), closed history onto finished
     * ones. Among those, an order on the issue's own line comes first, so that
     * line's shift monitor shows it — though the re-plan puts every order of a part
     * on the part's primary line, so this only decides anything for parts that run
     * on more than one.
     *
     * One issue at a time, not one order's worth at a time: issues that shared an
     * order in the order book are about different parts, and moving them as a
     * group carried a pinion's hardness failure onto a housing.
     *
     * Nothing is left behind on an order whose state the re-plan rewrote. A live
     * problem with no running order of its part free to carry it (planWeek keeps
     * one running, so this is a fallback) becomes resolved history on the part's
     * most recent finished order; history with no finished order of its part to
     * belong to — on a Monday morning, before anything has finished — is dropped,
     * as there is no run it could have happened on.
     */
    private function reassignIssues(): void
    {
        $used = [];
        $products = ProductType::pluck('id', 'code');
        $lines = Line::pluck('id', 'code');
        $running = [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED];
        $live = [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED];

        // Orders of the part in one of the states: on the issue's line first, then
        // ones no other issue has taken, then the most recent.
        $pick = function (array $statuses, int $productTypeId, ?int $lineId, bool $share) use (&$used): ?WorkOrder {
            return WorkOrder::whereIn('status', $statuses)->where('order_no', 'like', 'WO-MS-%')
                ->where('product_type_id', $productTypeId)
                ->orderByDesc('planned_start_at')->get()
                ->reject(fn (WorkOrder $wo) => ! $share && in_array($wo->id, $used, true))
                ->sortBy(fn (WorkOrder $wo) => [
                    $wo->line_id === $lineId ? 0 : 1,
                    in_array($wo->id, $used, true) ? 1 : 0,
                ])
                ->first();
        };

        $issues = Issue::whereNotNull('work_order_id')->orderBy('id')->get()
            ->sortByDesc(fn (Issue $i) => $i->isBlocking());

        foreach ($issues as $issue) {
            $context = $this->issueContext($issue);
            $productTypeId = $products[$context['product'] ?? ''] ?? null;
            if (! $productTypeId) {
                continue;
            }
            $lineId = $lines[$context['line']] ?? null;
            $blocking = $issue->isBlocking();

            if (in_array($issue->status, $live, true)) {
                // A live problem gets a running order to itself: an order stops on one
                // step, so two problems on different operations cannot both be live on it.
                $target = $pick($running, $productTypeId, $lineId, share: false);
                if (! $target) {
                    $target = $pick([WorkOrder::STATUS_DONE], $productTypeId, $lineId, share: true);
                    if ($target) {
                        Issue::whereKey($issue->id)->update(['status' => Issue::STATUS_RESOLVED, 'acknowledged_at' => $this->realNow, 'resolved_at' => $this->realNow]);
                        $blocking = false;
                        $this->log("  ! no running {$context['product']} order: \"{$issue->title}\" filed as resolved history");
                    }
                }
            } else {
                $target = $pick([WorkOrder::STATUS_DONE], $productTypeId, $lineId, share: true);
            }

            if (! $target) {
                Issue::whereKey($issue->id)->forceDelete();
                $this->log("  ! no finished {$context['product']} order yet: \"{$issue->title}\" dropped");

                continue;
            }
            $used[] = $target->id;
            Issue::whereKey($issue->id)->update(['work_order_id' => $target->id, 'batch_step_id' => null]);
            if ($blocking) {
                DB::table('work_orders')->where('id', $target->id)->update(['status' => WorkOrder::STATUS_BLOCKED]);
            }
        }
    }

    /**
     * Where a seeded issue happened: its line, part and the routing step its text names.
     *
     * @return array{line: string, product: string, step: string}|null
     */
    private function issueContext(?Issue $issue): ?array
    {
        $this->issueContexts ??= MachineShopDemoSeeder::issueContexts();

        return $issue ? ($this->issueContexts[$issue->title] ?? null) : null;
    }

    /** @var array<string, array{line: string, product: string, step: string}>|null */
    private ?array $issueContexts = null;

    private function warehouses(): void
    {
        $this->raw = Warehouse::create(['code' => 'WH-RAW', 'name' => 'Raw material store', 'kind' => Warehouse::KIND_RAW_MATERIAL, 'is_default' => true, 'is_active' => true, 'erp_code' => 'MAG-01']);
        $this->wip = Warehouse::create(['code' => 'WH-WIP', 'name' => 'Semi-finished store', 'kind' => Warehouse::KIND_RAW_MATERIAL, 'is_default' => false, 'is_active' => true, 'erp_code' => 'MAG-02', 'description' => 'Billets, rough-turned and hardened blanks, flange blanks']);
        $this->fg = Warehouse::create(['code' => 'WH-FG', 'name' => 'Finished goods', 'kind' => Warehouse::KIND_FINISHED_GOODS, 'is_default' => true, 'is_active' => true, 'erp_code' => 'MAG-03']);
    }

    private function lotSequences(): void
    {
        foreach (ProductType::all() as $pt) {
            LotSequence::create([
                'name' => "{$pt->code} lot", 'product_type_id' => $pt->id, 'purpose' => LotSequence::PURPOSE_LOT,
                'pattern' => '[product]-[year2][doy]-[seq]', 'prefix' => '', 'pad_size' => 3, 'reset_period' => 'none',
                'next_number' => 1, 'year_prefix' => false,
            ]);
            if (in_array($pt->code, self::SERIALISED, true)) {
                LotSequence::create([
                    'name' => "{$pt->code} unit serial", 'product_type_id' => $pt->id, 'purpose' => LotSequence::PURPOSE_UNIT_SERIAL,
                    'pattern' => 'SN-[product]-[year2]-[seq]', 'prefix' => '', 'pad_size' => 5, 'reset_period' => 'yearly',
                    'next_number' => 1, 'year_prefix' => false,
                ]);
            }
        }
    }

    // ── stock ────────────────────────────────────────────────────────────────

    private function openingStock(): void
    {
        DB::table('materials')->update(['stock_quantity' => 0, 'reserved_quantity' => 0]);

        $demand = [];
        $orders = WorkOrder::where('order_no', 'like', 'WO-MS-%')
            ->where(fn ($q) => $q->whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_ACCEPTED])
                ->orWhere('planned_start_at', '<=', $this->weekEnd))
            ->get();
        foreach ($orders as $wo) {
            $template = $this->activeTemplate($wo->product_type_id);
            if ($template) {
                $this->explode($template, (float) $wo->planned_qty * 1.15, $demand);
            }
        }

        $existing = MaterialLot::get()->keyBy('material_id');
        $deliveries = [
            $this->weekStart->copy()->subDays(8)->setTime(7, 30),
            $this->weekStart->copy()->subDays(5)->setTime(8, 15),
        ];
        $docLines = [0 => [], 1 => []];

        foreach (Material::where('is_manufactured', false)->get() as $material) {
            $need = $demand[$material->id] ?? 0;
            if ($need <= 0) {
                continue;
            }
            $total = $this->nice($need * 1.2, $material->unit_of_measure);
            $split = [$this->nice($total * 0.5, $material->unit_of_measure), 0];
            $split[1] = max($this->nice($total - $split[0], $material->unit_of_measure), 1);

            foreach ($split as $i => $qty) {
                Carbon::setTestNow($deliveries[$i]->copy()->addMinutes(mt_rand(0, 90)));
                $old = $i === 0 ? ($existing[$material->id] ?? null) : null;
                $lotNo = $old?->lot_number ?? $this->purchasedLotNumber($material, $i);
                $lot = $this->receiveLot($material, $lotNo, $qty, $old, pass: true);
                if ($lot) {
                    $docLines[$i][] = $lot;
                }
            }
        }

        foreach ($docLines as $i => $lots) {
            Carbon::setTestNow($deliveries[$i]->copy()->addHours(3));
            $this->postReceipt($this->raw, $lots, 'PO-2026-'.(4100 + $i * 37), 'Supplier delivery');
        }

        // This week's bar delivery fails goods-in: quarantined, NC raised, never consumed.
        if ($bar = Material::where('code', 'MAT-BAR-42CRMO4')->first()) {
            Carbon::setTestNow($this->weekStart->copy()->setTime(9, 40));
            $this->receiveLot($bar, 'HEAT-42CRMO4-9017', 60, null, pass: false);
        }
    }

    private function activeTemplate(?int $productTypeId): ?ProcessTemplate
    {
        return $productTypeId
            ? ProcessTemplate::where('product_type_id', $productTypeId)->where('is_active', true)->orderByDesc('version')->with(['bomItems.material', 'steps'])->first()
            : null;
    }

    /** @param array<int, float> $acc */
    private function explode(ProcessTemplate $template, float $qty, array &$acc): void
    {
        foreach ($template->bomItems as $item) {
            if (! $item->material) {
                continue;
            }
            $need = $qty * (float) $item->quantity_per_unit * (1 + (float) $item->scrap_percentage / 100);
            if ($item->material->is_manufactured && $item->material->producing_process_template_id) {
                if ($child = ProcessTemplate::with('bomItems.material')->find($item->material->producing_process_template_id)) {
                    $this->explode($child, $need * 1.12, $acc);
                }

                continue;
            }
            $acc[$item->material_id] = ($acc[$item->material_id] ?? 0) + $need;
        }
    }

    private function nice(float $qty, ?string $unit): float
    {
        if (in_array($unit, ['pcs', 'szt', 'pc'], true) || $qty >= 50) {
            return (float) (ceil($qty / 10) * 10);
        }

        return round(max($qty, 1), 1);
    }

    private function purchasedLotNumber(Material $material, int $i): string
    {
        $base = strtoupper(preg_replace('/^MAT-/', '', $material->code));
        $metal = (bool) preg_match('/BAR|PLATE|BRONZE|CAST/', $base);

        return ($metal ? 'HEAT-' : 'LOT-').$base.'-'.(7000 + mt_rand(100, 999) + $i);
    }

    private function receiveLot(Material $material, string $lotNo, float $qty, ?MaterialLot $old, bool $pass): ?MaterialLot
    {
        $old?->forceDelete();
        $supplierLot = 'SUP-'.strtoupper(substr(md5($lotNo), 0, 6));
        $plan = $this->inspections->applicablePlan($material);

        if ($plan) {
            $inspection = $this->inspections->start($material, $lotNo, $qty, $plan, $this->inspector, $supplierLot, 'CERT-3.1-'.mt_rand(10000, 99999));
            foreach ($inspection->results as $idx => $result) {
                Carbon::setTestNow(now()->addMinutes(4));
                $ok = $pass || $idx !== 0;
                $this->inspections->recordResult($result, [
                    'value_boolean' => $ok,
                    'notes' => $ok ? null : 'Mill certificate does not match the heat number stamped on the bars.',
                ]);
            }
            Carbon::setTestNow(now()->addMinutes(10));
            $disposer = $this->supervisor->can('disposition', $inspection) ? $this->supervisor : $this->admin;
            $inspection = $this->inspections->complete($inspection, $pass ? 'Accepted at goods-in.' : 'Rejected at goods-in — supplier informed.', $disposer);
            $lot = MaterialLot::where('inspection_id', $inspection->id)->first();
            if (! $lot) {
                return null;
            }
            if (! $pass) {
                $lot->update(['warehouse_id' => $this->raw->id, 'hold_reason' => 'Failed inbound inspection: certificate mismatch', 'held_at' => now(), 'held_by_id' => $disposer->id, 'supplier_reference' => $material->supplier_name]);

                return null;
            }
        } else {
            $lot = MaterialLot::create([
                'lot_number' => $lotNo, 'material_id' => $material->id,
                'quantity_received' => $qty, 'quantity_available' => $qty,
                'unit_of_measure' => $material->unit_of_measure, 'received_at' => now(),
                'status' => MaterialLot::STATUS_RELEASED, 'supplier_lot_no' => $supplierLot,
                'created_by_id' => $this->inspector->id,
            ]);
        }

        $lot->update([
            'quantity_available' => 0, // the posted receipt brings it in
            'status' => MaterialLot::STATUS_RELEASED,
            'released_at' => now(),
            'released_by_id' => $this->supervisor->id,
            'warehouse_id' => $this->raw->id,
            'supplier_reference' => $material->supplier_name,
            'expiry_date' => in_array($material->code, ['MAT-COOLANT', 'MAT-QUENCH-OIL'], true) ? now()->addMonths(9)->toDateString() : null,
        ]);

        return $lot;
    }

    /** @param array<int, MaterialLot> $lots */
    private function postReceipt(Warehouse $warehouse, array $lots, string $reference, string $notes, ?WorkOrder $wo = null): ?StockDocument
    {
        if ($lots === []) {
            return null;
        }
        $doc = $this->docs->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
            'warehouse_id' => $warehouse->id,
            'work_order_id' => $wo?->id,
            'erp_reference' => $reference,
            'notes' => $notes,
            'lines' => collect($lots)->map(fn (MaterialLot $lot) => [
                'material_id' => $lot->material_id,
                'material_lot_id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'quantity' => (float) $lot->quantity_received,
                'unit_of_measure' => $lot->unit_of_measure,
            ])->all(),
        ], $this->supervisor);

        return $this->docs->post($doc, $this->supervisor);
    }

    // ── order book ───────────────────────────────────────────────────────────

    private function snapshotOrderBook(): void
    {
        foreach (WorkOrder::orderBy('id')->get() as $wo) {
            $released = ($wo->planned_start_at ?? $wo->created_at ?? $this->realNow)->copy()->subDays(3)->setTime(9, mt_rand(0, 59));
            if ($released->gt($this->realNow)) {
                $released = $this->realNow->copy()->subHours(2);
            }
            Carbon::setTestNow($released);

            $revisionId = ProductRevision::where('product_type_id', $wo->product_type_id)->where('lifecycle_status', 'released')->value('id');
            $snapshot = $this->orders->rebuildProcessSnapshot($wo->product_type_id, [], $revisionId);
            if (! $snapshot) {
                continue;
            }
            DB::table('work_orders')->where('id', $wo->id)->update([
                'process_snapshot' => json_encode($snapshot),
                'product_revision_id' => $revisionId,
                'created_at' => $released,
            ]);
            $wo->refresh();
            $this->orders->syncBomSelection($wo, $snapshot['bom_template_ids'] ?? []);
            $this->snapshots->ensureBaseline($wo, $this->supervisor);
        }
    }

    // ── replay ───────────────────────────────────────────────────────────────

    private function replayHistory(): void
    {
        $orders = WorkOrder::whereIn('status', [WorkOrder::STATUS_DONE, WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_BLOCKED])
            ->where('order_no', 'like', 'WO-MS-%')->where('order_no', 'not like', 'WO-MS-S%')
            ->orderBy('planned_start_at')->get();

        foreach ($orders as $i => $wo) {
            $this->flushShipments($wo->planned_start_at);
            if ($wo->productType?->code === 'KIT40' || str_starts_with($wo->productType?->code ?? '', 'SA_')) {
                try {
                    DB::transaction(fn () => $this->replayOrder($wo->fresh()));
                } catch (\App\Exceptions\InsufficientStockException $e) {
                    DB::table('work_orders')->where('id', $wo->id)->update(['status' => WorkOrder::STATUS_ACCEPTED, 'produced_qty' => 0, 'completed_at' => null]);
                    $this->log("  ! {$wo->order_no} components not in stock yet — left unstarted");
                }
            } else {
                $this->replayOrder($wo->fresh());
            }
            $wo->refresh();
            $this->log(sprintf('%d/%d %s %s %s %s/%s', $i + 1, $orders->count(), $wo->order_no, $wo->productType?->code, $wo->status, (float) $wo->produced_qty, (float) $wo->planned_qty));
        }
        $this->flushShipments($this->realNow);
        $this->reconcilePallets();
    }

    private function replayOrder(WorkOrder $wo): void
    {
        $finalStatus = $wo->status;
        $start = $wo->planned_start_at->copy()->addMinutes(mt_rand(2, 12));

        $cutoff = null;
        $halt = null;
        $haltStep = null;
        $this->haltedAt = null;
        if ($finalStatus === WorkOrder::STATUS_BLOCKED) {
            foreach ($wo->openBlockingIssues() as $issue) {
                $this->parkedIssues[] = ['issue_id' => $issue->id, 'wo' => $wo->id, 'status' => $issue->status];
                DB::table('issues')->where('id', $issue->id)->update(['status' => 'CLOSED']);
                $haltStep ??= $this->issueContext($issue)['step'] ?? null;
            }
            $span = max(20, $start->diffInMinutes($this->realNow->copy()->subMinutes(15), false));
            $soft = $start->copy()->addMinutes((int) max(20, $span * 0.65));
            if ($haltStep) {
                // The order stops on the operation its blocking problem names — a
                // broken turning insert stops it at turning, not wherever the clock
                // happened to run out: past the soft cutoff it halts the first time a
                // batch starts that step. Should no batch reach it before the hard
                // cutoff, blockAt pins the issue to the last time the step ran.
                $halt = ['step' => $haltStep, 'from' => $soft];
                $cutoff = $this->realNow->copy()->subMinutes(15);
            } else {
                $cutoff = $soft;
            }
        } elseif ($finalStatus === WorkOrder::STATUS_IN_PROGRESS) {
            $cutoff = $this->realNow->copy()->subMinutes(3);
        }

        $template = $this->activeTemplate($wo->product_type_id);
        $planned = (float) $wo->planned_qty;

        foreach ($template?->bomItems ?? [] as $item) {
            if ($this->isSubassembly($item->material) && $item->consumed_at === 'start') {
                $need = $planned * (float) $item->quantity_per_unit * (1 + (float) $item->scrap_percentage / 100) * 1.2;
                $this->produceSubassembly($item->material, $need, $start->copy()->subMinutes(mt_rand(40, 120)), $wo, run: true);
            }
        }

        DB::table('work_orders')->where('id', $wo->id)->update(['status' => WorkOrder::STATUS_ACCEPTED, 'produced_qty' => 0, 'completed_at' => null]);
        $wo->refresh();

        // A running order works in smaller batches, so part of it is already finished.
        $plan = $this->batchPlan($planned, $finalStatus === WorkOrder::STATUS_DONE ? self::MAX_BATCH : 10);
        $work = $this->estimate($wo, $planned) + (count($plan) - 1) * $this->estimate($wo, 0);
        if ($finalStatus === WorkOrder::STATUS_DONE) {
            $latestEnd = min($wo->planned_end_at->copy()->addHours(2), $this->realNow->copy()->subMinutes(40));
            $factor = min(1.0, max(30, $start->diffInMinutes($latestEnd, false)) / ($work * 1.2 + 60));
        } else {
            // A running order must still be running now. Steps run at their normal
            // pace and the slack goes between batches, so finished batches — and the
            // produced count — build up steadily through the order's window.
            $factor = 1.0;
            $slack = $start->diffInMinutes($wo->planned_end_at, false) - $work * 1.1;
            $gap = max(0, (int) ($slack / max(1, count($plan))));
        }

        $this->runOrder($wo, $plan, $start, $cutoff, $factor, scrap: true, gap: $gap ?? 0, halt: $halt);
        $wo->refresh();

        if ($finalStatus === WorkOrder::STATUS_DONE) {
            $this->finishOrder($wo);
        } else {
            foreach ($wo->batches()->orderBy('batch_number')->get() as $batch) {
                if ($batch->status === Batch::STATUS_DONE) {
                    $this->afterFinishedBatch($wo, $batch, orderDone: false, ship: null);
                } elseif ($this->isSerialised($wo)) {
                    $this->serialise($wo, $batch, null, null);
                }
            }
        }

        if ($finalStatus === WorkOrder::STATUS_BLOCKED) {
            // Reported a couple of minutes into the step it stopped on.
            $this->blockAt($wo->fresh(), $this->haltedAt?->copy()->addMinutes(2) ?? $cutoff);
        }
    }

    /** When a blocked order's replay halted on the step its blocking issue names. */
    private ?Carbon $haltedAt = null;

    /** @return array<int, float> */
    private function batchPlan(float $qty, int $max = self::MAX_BATCH): array
    {
        $n = max(1, (int) ceil($qty / $max));
        $base = floor($qty / $n);
        $plan = array_fill(0, $n, $base);
        $plan[$n - 1] += $qty - $base * $n;

        return $plan;
    }

    private function estimate(WorkOrder $wo, float $qty): float
    {
        $minutes = 0;
        foreach ($wo->process_snapshot['steps'] ?? [] as $step) {
            $minutes += (float) ($step['setup_time_minutes'] ?? 0) + (float) ($step['run_time_per_unit_minutes'] ?? 0) * $qty + 8;
        }

        return $minutes;
    }

    /**
     * @param  array<int, float>  $plan
     * @param  array{step: string, from: Carbon}|null  $halt  stop on this step once past this time
     */
    private function runOrder(WorkOrder $wo, array $plan, Carbon $t, ?Carbon $cutoff, float $factor, bool $scrap, int $gap = 0, ?array $halt = null): Carbon
    {
        foreach ($plan as $qty) {
            [$t, $stopped] = $this->runBatch($wo, $qty, $t, $cutoff, $factor, $scrap, $halt);
            if ($stopped) {
                return $t;
            }
            $t = $t->copy()->addMinutes(mt_rand(5, 15) + $gap);
        }

        for ($i = 0; $i < 2; $i++) {
            $wo->refresh();
            $short = (float) $wo->planned_qty - (float) $wo->produced_qty;
            if ($short <= 0 || $wo->status === WorkOrder::STATUS_DONE) {
                break;
            }
            try {
                // A make-up batch the semi-finished stock cannot cover is simply not run.
                [$t, $stopped] = DB::transaction(fn () => $this->runBatch($wo, $short, $t, $cutoff, $factor * 0.8, false, $halt));
            } catch (\App\Exceptions\InsufficientStockException $e) {
                $this->log("  ! {$wo->order_no} make-up batch of {$short} skipped: ".$e->getMessage());
                break;
            }
            if ($stopped) {
                break;
            }
        }

        return $t;
    }

    /**
     * @param  array{step: string, from: Carbon}|null  $halt
     * @return array{0: Carbon, 1: bool}
     */
    private function runBatch(WorkOrder $wo, float $qty, Carbon $t, ?Carbon $cutoff, float $factor, bool $scrap, ?array $halt = null): array
    {
        if ($cutoff && $t->gte($cutoff)) {
            return [$t, true];
        }

        Carbon::setTestNow($t);
        $batch = $this->orders->createBatch($wo->fresh(), $qty, null, $this->lots->generateLot($wo->productType));
        $steps = $batch->steps()->where('status', '!=', BatchStep::STATUS_SKIPPED)->orderBy('step_number')->get();
        $scrapped = 0.0;

        foreach ($steps as $step) {
            $step = $step->fresh();
            if ($cutoff && $t->gte($cutoff)) {
                return [$t, true];
            }

            $line = $this->lineOfStation[$step->workstation_id] ?? null;
            $user = $line ? ($this->operators[$line] ?? $this->supervisor) : $this->supervisor;
            $prefix = $this->prefixOf($step->workstation_id);

            if ($halt && $step->name === $halt['step'] && $t->gte($halt['from'])) {
                // The problem happens here: the step starts and the order stops on it.
                Carbon::setTestNow($t);
                $this->batches->startStep($step, $user);
                $this->haltedAt = $t->copy();

                return [$t, true];
            }

            if ($line && mt_rand(1, 100) <= 8) {
                $t = $this->downtimeAt($line, $step->workstation_id, $user, $t, $cutoff);
                if ($cutoff && $t->gte($cutoff)) {
                    return [$t, true];
                }
            }

            Carbon::setTestNow($t);
            $this->batches->startStep($step, $user);

            $remaining = $qty - $scrapped;
            $minutes = max(3, ((float) ($step->setup_time_minutes ?? 0) + (float) ($step->run_time_per_unit_minutes ?? 0) * $remaining) * mt_rand(92, 118) / 100 * $factor);
            $end = $t->copy()->addSeconds((int) round($minutes * 60));

            if ($scrap && isset(self::SCRAP_BY_STEP[$step->name]) && mt_rand(1, 100) <= 28) {
                $mid = $t->copy()->addSeconds((int) round($minutes * 30));
                if (! $cutoff || $mid->lt($cutoff)) {
                    Carbon::setTestNow($mid);
                    $s = max(1, (int) round($remaining * mt_rand(5, 30) / 1000));
                    $this->batches->recordQuantity($step->fresh(), $user, 0, $s, $this->scrapNote($step->name));
                    ScrapEntry::where('batch_step_id', $step->id)->whereNull('scrap_reason_id')->update(['scrap_reason_id' => $this->scrapReasonFor($step->name)]);
                    $scrapped += $s;
                }
            }

            if ($cutoff && $end->gt($cutoff)) {
                return [$cutoff->copy(), true];
            }

            Carbon::setTestNow($end);
            $this->batches->completeStep($step->fresh(), $user, [
                'produced_qty' => $qty - $scrapped,
                'actual_elapsed_minutes' => (int) round($minutes),
            ]);

            if ($prefix === 'CMM') {
                $this->qualityCheck($batch->fresh(), $step, $end, $qty - $scrapped);
            }

            $t = $end->copy()->addMinutes(mt_rand(2, 10));
        }

        $batch->refresh();
        if ($batch->status === Batch::STATUS_DONE && str_starts_with($wo->productType?->code ?? '', 'SA_')) {
            $this->subassemblyOutput($wo, $batch);
        }

        return [$t, false];
    }

    /**
     * What a scrapped part looks like at each operation that cuts, forms or
     * heat-treats metal: step name => [note, reason code | null = by cause].
     *
     * Keyed by the step, not by the kind of station: a note per station put
     * "diameter undersize after finishing pass" on centre drilling and grooving,
     * "bore position" on keyway and tooth cutting, and a "journal" on a pinion
     * bore. Steps missing here (loading, set-up, checks) scrap nothing.
     */
    private const SCRAP_BY_STEP = [
        'Saw to length' => ['Cut length short', null],
        'Cut blank' => ['Blank cut under the machining allowance', null],
        'Rough turn' => ['Diameter undersize after roughing pass', null],
        'Flip & rough turn side B' => ['Diameter undersize after roughing pass', null],
        'Centre drill' => ['Centre hole drilled off-axis', null],
        'Turn journals to pre-grind size' => ['Journal undersize, no grinding stock left', null],
        'Turn keyway seat' => ['Keyway seat diameter undersize', null],
        'Finish turning' => ['Diameter undersize after finishing pass', null],
        'Face & bore' => ['Bore oversize after boring', null],
        'Chamfer bore' => ['Bore chamfer oversize', null],
        'Sealing face' => ['Sealing face serration torn', 'SURF-DEF'],
        'Turn blank' => ['Blank diameter undersize, no grinding stock left', null],
        'Bore & face' => ['Bore oversize after boring', null],
        'Turn & bore' => ['Bore oversize after boring', null],
        'Ream bore H7' => ['Bore oversize after reaming', null],
        'Lubrication grooves' => ['Groove position out of tolerance', null],
        'Part-off & face' => ['Length short after parting off', null],
        'Turn & chamfer' => ['Diameter undersize after turning', null],
        'Keyway milling' => ['Keyway width oversize', null],
        'Datum faces' => ['Datum face out of flat', null],
        'Bore machining' => ['Bore position out of tolerance', null],
        'Drill & tap' => ['Tapped hole out of gauge', null],
        'Bolt circle' => ['Bolt circle position out of tolerance', null],
        'Tooth cutting' => ['Tooth thickness out of tolerance', null],
        'Saw & square' => ['Block out of square', null],
        'Cross drilling' => ['Cross hole broke out of position', null],
        'Port machining' => ['Port thread out of gauge', null],
        'Face both sides' => ['Plate out of flat after unclamping', null],
        'Drill pattern' => ['Hole position out of tolerance', null],
        'Tap M12' => ['Tapped hole out of gauge', null],
        'Cylindrical grinding' => ['Grinding burn on bearing seat', 'SURF-DEF'],
        'Bore grinding' => ['Bore ground oversize', null],
        'Rough grind Ø20' => ['Grinding burn on the diameter', 'SURF-DEF'],
        'Grind end faces' => ['End faces out of parallel', null],
        'Finish grind Ø20 g6' => ['Diameter ground undersize', null],
        'Austenitise soak' => ['Hardness below specification', 'WRONG-MAT'],
        'Harden' => ['Hardness below specification', 'WRONG-MAT'],
        'Case hardening' => ['Case depth below specification', 'WRONG-MAT'],
        'Quench' => ['Quench crack found', 'SURF-DEF'],
    ];

    private function scrapReasonFor(string $stepName): ?int
    {
        $code = self::SCRAP_BY_STEP[$stepName][1] ?? match (true) {
            mt_rand(1, 100) <= 15 => 'MACH-FAIL',
            mt_rand(1, 100) <= 15 => 'OP-ERR',
            default => 'DIM-OOS',
        };

        return DB::table('scrap_reasons')->where('code', $code)->value('id');
    }

    private function scrapNote(string $stepName): string
    {
        return self::SCRAP_BY_STEP[$stepName][0] ?? 'Part failed the in-process check';
    }

    /**
     * Downtime as each kind of station has it. A furnace has no spindle, a CMM no
     * chip conveyor, a bar feeder neither, and a packing bench no offsets to correct.
     */
    private function downtimeNote(string $code, string $prefix): string
    {
        $kind = match ($prefix) {
            'LATHE', 'MILL' => 'cnc',
            'FURN', 'TEMPER', 'QUENCH' => 'heat',
            default => strtolower($prefix),
        };

        return match ($code) {
            'no_material' => 'Waiting for material from the store',
            'breakdown' => match ($kind) {
                'cnc', 'grind' => 'Spindle drive fault — maintenance called',
                'saw' => 'Blade drive tripped — maintenance called',
                'barfeed' => 'Bar jammed in the feeder channel — maintenance called',
                'heat' => $prefix === 'QUENCH' ? 'Quench bath agitator failed — maintenance called' : 'Burner fault — maintenance called',
                'cmm' => 'Probe head fault — service called',
                'debur' => 'Wash unit pump failed — maintenance called',
                default => 'Strapping tool broke — replacement fetched',
            },
            'quality_issue' => match ($kind) {
                'cnc', 'grind' => 'First-off part rejected, offsets corrected',
                'saw' => 'First cut out of length, stop reset',
                'barfeed' => 'Bar pushed past the stop, feed length reset',
                'heat' => 'Test piece under hardness, cycle repeated',
                'cmm' => 'Results drifting, machine re-calibrated',
                'debur' => 'Burrs found at inspection, parts re-deburred',
                default => 'Labels did not match the order, boxes relabelled',
            },
            'cleaning' => match ($kind) {
                'cnc', 'saw' => 'Chip conveyor cleared',
                'barfeed' => 'Feeder channel cleaned and re-oiled',
                'grind' => 'Swarf tray and coolant filter cleaned',
                'heat' => $prefix === 'QUENCH' ? 'Quench tank skimmed' : 'Hearth and basket cleaned',
                'cmm' => 'Table and stylus cleaned',
                'debur' => 'Bench and wash tank cleaned',
                default => 'Packing bench cleared',
            },
            default => match ($kind) {
                'cnc', 'grind' => 'Fixture and program change',
                'saw' => 'Blade and length stop changed',
                'barfeed' => 'Guide tube changed for the next bar size',
                'heat' => 'Cycle changed for the next grade',
                'cmm' => 'Fixture and measuring program change',
                'debur' => 'Bench reset for the next part',
                default => 'Packing material changed for the next order',
            },
        };
    }

    private function downtimeAt(string $lineCode, ?int $workstationId, User $user, Carbon $t, ?Carbon $cutoff): Carbon
    {
        $line = Line::where('code', $lineCode)->first();
        if (! $line || $this->downtime->getActive($line->id)) {
            return $t;
        }
        $roll = mt_rand(1, 100);
        $code = $roll <= 55 ? 'changeover' : ($roll <= 70 ? 'breakdown' : ($roll <= 80 ? 'no_material' : ($roll <= 90 ? 'quality_issue' : 'cleaning')));
        Carbon::setTestNow($t);
        $dt = $this->downtime->start($line, DB::table('downtime_reasons')->where('code', $code)->value('id'), $user, $workstationId,
            $this->downtimeNote($code, $this->prefixOf($workstationId)));
        $end = $t->copy()->addMinutes($code === 'breakdown' ? mt_rand(25, 70) : mt_rand(8, 30));
        if ($cutoff && $end->gte($cutoff)) {
            $end = $cutoff->copy()->subMinute();
        }
        Carbon::setTestNow($end);
        $this->downtime->stop($dt);

        return $end;
    }

    private function qualityCheck(Batch $batch, BatchStep $step, Carbon $at, float $qty): void
    {
        $code = $batch->workOrder->productType->code;
        $samples = match ($code) {
            'SA_HARD' => [['Surface hardness [HRC]', 'numeric', 52 + mt_rand(0, 40) / 10], ['Case depth [mm]', 'numeric', 0.8 + mt_rand(0, 3) / 10]],
            'SHAFT40' => [['Journal Ø40 h6 [mm]', 'numeric', 39.990 + mt_rand(0, 14) / 1000], ['Runout [mm]', 'numeric', mt_rand(3, 18) / 1000], ['Keyway width [mm]', 'numeric', 12.0 + mt_rand(0, 40) / 1000]],
            'PINION18' => [['Tooth thickness [mm]', 'numeric', 4.70 + mt_rand(0, 6) / 100], ['Bore Ø25 H7 [mm]', 'numeric', 25.0 + mt_rand(0, 18) / 1000]],
            'FLANGE150' => [['Bolt circle Ø240 [mm]', 'numeric', 240 + mt_rand(-10, 10) / 100], ['Sealing face flatness [mm]', 'numeric', mt_rand(5, 30) / 1000]],
            default => [['Critical dimension [mm]', 'numeric', 50 + mt_rand(-20, 20) / 1000], ['Surface roughness Ra [µm]', 'numeric', 0.8 + mt_rand(0, 8) / 10]],
        };
        $samples[] = ['Visual inspection', 'boolean', true];

        Carbon::setTestNow($at->copy()->subMinutes(2));
        $this->quality->performCheck($batch, $this->inspector, collect($samples)->map(fn ($s, $i) => [
            'sample_number' => $i + 1, 'parameter_name' => $s[0], 'parameter_type' => $s[1],
            'value_numeric' => $s[1] === 'numeric' ? $s[2] : null,
            'value_boolean' => $s[1] === 'boolean' ? $s[2] : null,
            'is_passed' => true,
        ])->all(), $qty, null, "Sample inspection at {$step->name}");
    }

    // ── sub-assemblies ───────────────────────────────────────────────────────

    private function produceSubassembly(Material $material, float $need, Carbon $finishBy, WorkOrder $parent, bool $run): void
    {
        $template = ProcessTemplate::with(['bomItems.material', 'productType', 'steps'])->find($material->producing_process_template_id);
        if (! $template) {
            return;
        }
        $qty = (float) ceil($need);
        $pt = $template->productType;
        $batches = max(1, (int) ceil($qty / self::MAX_BATCH));
        // Every batch repeats the set-ups; leave room for scrap make-up and the
        // gaps between steps, so the chain is done before its parent starts.
        $minutes = ($this->estimateTemplate($template, $qty) + $batches * $this->estimateTemplate($template, 0)) * 1.3 + $batches * 15 + 30;
        $start = $finishBy->copy()->subMinutes((int) $minutes);
        // Planned ahead of an upcoming order but already due to have started:
        // it is on the floor right now (or, if its window has passed, done).
        $live = ! $run && $start->lt($this->realNow);

        foreach ($template->bomItems as $item) {
            if ($this->isSubassembly($item->material) && $item->consumed_at === 'start') {
                $this->produceSubassembly($item->material, $qty * (float) $item->quantity_per_unit * (1 + (float) $item->scrap_percentage / 100) * 1.2, $start->copy()->subMinutes(mt_rand(30, 90)), $parent, $run);
            }
        }

        $created = $start->copy()->subDay()->setTime(10, mt_rand(0, 59));
        Carbon::setTestNow($created->gt($this->realNow) ? $this->realNow : $created);
        $wo = $this->orders->createWorkOrder([
            'order_no' => sprintf('WO-MS-S%03d', ++$this->saCounter),
            'line_id' => DB::table('line_product_type')->where('product_type_id', $pt->id)->value('line_id'),
            'product_type_id' => $pt->id,
            'planned_qty' => $qty,
            'priority' => $parent->priority,
            'due_date' => $finishBy->toDateString(),
            'planned_start_at' => $start,
            'description' => "{$pt->name} for {$parent->order_no}",
            'customer_order_no' => null,
        ]);
        DB::table('work_orders')->where('id', $wo->id)->update([
            'status' => $run || $live || $start->lt($this->realNow->copy()->addDays(2)) ? WorkOrder::STATUS_ACCEPTED : WorkOrder::STATUS_PENDING,
            'planned_end_at' => $finishBy,
            'shift_number' => $start->hour < 14 ? 1 : 2,
            'week_number' => (int) $start->isoWeek(), 'month_number' => $start->month, 'production_year' => $start->year,
            'extra_data' => json_encode(['parent_order' => $parent->order_no]),
        ]);
        $wo->refresh();
        $this->snapshots->ensureBaseline($wo, $this->supervisor);

        if ($run || $live) {
            $this->runOrder($wo, $this->batchPlan($qty), $start, $live ? $this->realNow->copy()->subMinutes(3) : null, 1.0, scrap: true);
            if ($live) {
                $wo->refresh();
                $this->log("  live sub-assembly {$wo->order_no} {$pt->code} {$wo->status} {$wo->produced_qty}/{$wo->planned_qty}");
            }
        }
    }

    private function subassemblyOutput(WorkOrder $wo, Batch $batch): void
    {
        $material = Material::where('producing_process_template_id', $wo->process_snapshot['bom_template_ids'][0] ?? 0)->where('code', 'like', 'SA-%')->first();
        if (! $material || (float) $batch->produced_qty <= 0) {
            return;
        }

        Carbon::setTestNow($batch->completed_at->copy()->addMinutes(10));
        $this->release->release($batch->fresh(), $this->inspector, Batch::RELEASE_FOR_PRODUCTION);

        $lot = MaterialLot::create([
            'lot_number' => $batch->fresh()->lot_number,
            'material_id' => $material->id,
            'quantity_received' => $batch->produced_qty,
            'quantity_available' => 0,
            'unit_of_measure' => $material->unit_of_measure,
            'received_at' => now(),
            'manufacturing_date' => $batch->completed_at->toDateString(),
            'status' => MaterialLot::STATUS_RELEASED,
            'released_at' => now(),
            'released_by_id' => $this->inspector->id,
            'source_batch_id' => $batch->id,
            'warehouse_id' => $this->wip->id,
            'created_by_id' => $this->inspector->id,
        ]);
        Carbon::setTestNow(now()->addMinutes(5));
        $this->postReceipt($this->wip, [$lot], $wo->order_no, "Semi-finished output of {$wo->order_no} batch #{$batch->batch_number}", $wo);
    }

    /** Orders still to run this week get their sub-assembly orders planned ahead of them. */
    private function planUpcomingSubassemblies(): void
    {
        $orders = WorkOrder::whereIn('status', [WorkOrder::STATUS_ACCEPTED, WorkOrder::STATUS_PENDING])
            ->where('order_no', 'like', 'WO-MS-%')->where('order_no', 'not like', 'WO-MS-S%')
            ->where('planned_start_at', '<=', $this->weekEnd)
            ->orderBy('planned_start_at')->get();

        foreach ($orders as $wo) {
            foreach ($this->activeTemplate($wo->product_type_id)?->bomItems ?? [] as $item) {
                if ($this->isSubassembly($item->material) && $item->consumed_at === 'start') {
                    $need = (float) $wo->planned_qty * (float) $item->quantity_per_unit * (1 + (float) $item->scrap_percentage / 100) * 1.1;
                    $this->produceSubassembly($item->material, $need, $wo->planned_start_at->copy()->subMinutes(60), $wo, run: false);
                }
            }
        }
        $this->log('upcoming sub-assembly orders planned');
    }

    // ── finished goods ───────────────────────────────────────────────────────

    private function isSerialised(WorkOrder $wo): bool
    {
        return in_array($wo->productType?->code, self::SERIALISED, true);
    }

    private function finishOrder(WorkOrder $wo): void
    {
        if ($wo->status !== WorkOrder::STATUS_DONE) {
            $this->log("  ! {$wo->order_no} did not reach DONE ({$wo->status})");

            return;
        }

        // Shipped the next day once a day has passed; otherwise still in the FG store.
        $shipAt = $wo->completed_at->copy()->addHours(mt_rand(16, 20));
        if ($shipAt->hour < 7 || $shipAt->hour >= 19) {
            $shipAt = ($shipAt->hour >= 19 ? $shipAt->copy()->addDay() : $shipAt->copy())->setTime(mt_rand(7, 10), mt_rand(0, 59));
        }
        $ship = $shipAt->lt($this->realNow->copy()->subMinutes(30)) ? $shipAt : null;

        foreach ($wo->batches()->where('status', Batch::STATUS_DONE)->orderBy('batch_number')->get() as $batch) {
            $this->afterFinishedBatch($wo, $batch, orderDone: true, ship: $ship);
        }

        Carbon::setTestNow($wo->completed_at->copy()->addMinutes(50));
        if ($receipt = $this->woDocs->generateProductReceipt($wo)) {
            $receipt->update(['warehouse_id' => $this->fg->id]);
            $this->docs->post($receipt, $this->supervisor);
        }

        if ($ship) {
            // Booked when the truck actually leaves — after any kit picking that
            // happened in between has taken pieces off these pallets.
            $this->shipments[] = ['at' => $ship->copy()->addMinutes(5), 'kind' => 'issue', 'wo' => $wo->id];
        }
    }

    /** @var array<int, array<string, mixed>> shipping events, executed in time order */
    private array $shipments = [];

    /** @var array<int, array<int, int>> pallet id => finished lots on it */
    private array $palletLots = [];

    /** Ship everything due before $until, in time order. */
    private function flushShipments(Carbon $until): void
    {
        usort($this->shipments, fn ($a, $b) => $a['at'] <=> $b['at']);
        while ($this->shipments !== [] && $this->shipments[0]['at']->lte($until)) {
            $e = array_shift($this->shipments);
            Carbon::setTestNow($e['at']);
            $e['kind'] === 'pallet' ? $this->shipPallet($e) : $this->postProductIssue($e['wo']);
        }
    }

    /** @param array<string, mixed> $e */
    private function shipPallet(array $e): void
    {
        $pallet = Pallet::find($e['pallet']);
        $wo = WorkOrder::find($pallet->work_order_id);
        $lots = MaterialLot::whereIn('id', $this->palletLots[$pallet->id] ?? [])->get();
        $qty = (int) round($lots->sum(fn ($l) => (float) $l->quantity_available));
        if ($qty <= 0) {
            return; // emptied by kit picking — nothing left to load
        }
        foreach ($lots as $lot) {
            $avail = (float) $lot->quantity_available;
            if ($avail > 0) {
                $this->movements->record($lot->material, StockMovement::TYPE_CONSUME, -$avail, $this->supervisor, StockMovement::SOURCE_PALLET, $pallet->id, 'Shipped on pallet '.$pallet->pallet_no, $this->fg->id);
                $lot->update(['quantity_available' => 0, 'status' => MaterialLot::STATUS_CONSUMED]);
            }
        }
        $customer = $wo->customer?->name ?? 'Customer';
        $from = $pallet->location;
        $pallet->update(['qty' => $qty, 'status' => 'shipped', 'location' => 'DOCK-2', 'destination' => $customer, 'erp_reference' => 'WZ-'.$wo->customer_order_no]);
        $this->movePallet($pallet, $from, 'DOCK-2', $customer, 'Loaded for '.$wo->customer_order_no);
        SerialUnit::where('pallet_id', $pallet->id)->where('status', SerialUnit::STATUS_COMPLETED)
            ->update(['status' => SerialUnit::STATUS_SHIPPED, 'shipped_at' => now()]);
    }

    private function postProductIssue(int $woId): void
    {
        $wo = WorkOrder::find($woId);
        $qty = (float) Pallet::where('work_order_id', $wo->id)->where('status', 'shipped')->sum('qty');
        if ($qty <= 0) {
            return;
        }
        $issue = $this->docs->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_ISSUE,
            'warehouse_id' => $this->fg->id,
            'work_order_id' => $wo->id,
            'erp_reference' => 'WZ-'.$wo->customer_order_no,
            'notes' => 'Shipped to '.($wo->customer?->name ?? 'customer').' — '.$wo->customer_order_no,
            'lines' => [[
                'product_type_id' => $wo->product_type_id,
                'quantity' => $qty,
                'unit_of_measure' => $wo->productType?->unit_of_measure,
            ]],
        ], $this->supervisor);
        $this->docs->post($issue, $this->supervisor);
    }

    /** Pallets still in the store carry what is left of their lots after kit picking. */
    private function reconcilePallets(): void
    {
        foreach ($this->palletLots as $palletId => $lotIds) {
            $pallet = Pallet::find($palletId);
            if (! $pallet || $pallet->status === 'shipped') {
                continue;
            }
            $left = (int) round((float) MaterialLot::whereIn('id', $lotIds)->sum('quantity_available'));
            if ($left !== (int) $pallet->qty) {
                $pallet->update(['qty' => $left]);
            }
        }
    }

    /**
     * A finished batch: released for sale, booked as a finished-goods lot, put on a
     * pallet (open at the line end while the order runs; closed into the FG store
     * when the order is done; shipped the next day), units serialised onto it.
     */
    private function afterFinishedBatch(WorkOrder $wo, Batch $batch, bool $orderDone, ?Carbon $ship): void
    {
        if (str_starts_with($wo->productType?->code ?? '', 'SA_') || (float) $batch->produced_qty <= 0) {
            return;
        }
        $produced = (float) $batch->produced_qty;
        $lineCode = $wo->line?->code ?? 'LINE';

        Carbon::setTestNow($batch->completed_at->copy()->addMinutes(15));
        if (! $batch->isReleased()) {
            $this->release->release($batch->fresh(), $this->inspector, Batch::RELEASE_FOR_SALE);
        }

        $material = Material::find($this->fgMaterial[$wo->product_type_id] ?? 0);
        $lot = null;
        if ($material) {
            $lot = MaterialLot::create([
                'lot_number' => $batch->lot_number,
                'material_id' => $material->id,
                'quantity_received' => $produced,
                'quantity_available' => $produced,
                'unit_of_measure' => 'pcs',
                'received_at' => now(),
                'manufacturing_date' => $batch->completed_at->toDateString(),
                'expiry_date' => $batch->fresh()->expiry_date,
                'status' => MaterialLot::STATUS_RELEASED,
                'released_at' => now(),
                'released_by_id' => $this->inspector->id,
                'source_batch_id' => $batch->id,
                'warehouse_id' => $this->fg->id,
                'created_by_id' => $this->inspector->id,
            ]);
            $this->movements->record($material, StockMovement::TYPE_RECEIPT, $produced, $this->inspector, StockMovement::SOURCE_BATCH, $batch->id, "Output of {$wo->order_no} batch #{$batch->batch_number}", $this->fg->id);
        }

        // A small make-up batch tops up the order's last pallet instead of opening one.
        $existing = $produced <= 5 ? Pallet::where('work_order_id', $wo->id)->orderByDesc('id')->first() : null;
        DB::table('work_orders')->where('id', $wo->id)->increment('packed_qty', (int) $produced);
        if ($existing) {
            $existing->increment('qty', (int) $produced);
            if ($lot) {
                $this->palletLots[$existing->id][] = $lot->id;
            }
            if ($this->isSerialised($wo)) {
                $this->serialise($wo, $batch->fresh(), $existing, null);
            }

            return;
        }

        $pallet = Pallet::create([
            'work_order_id' => $wo->id,
            'batch_id' => $batch->id,
            'qty' => (int) $produced,
            'status' => 'open',
            'quality_status' => 'pending',
            'location' => 'LINE-END-'.$lineCode,
        ]);
        Carbon::setTestNow($batch->completed_at->copy()->addMinutes(25));
        $this->quality->performCheck($batch->fresh(), $this->inspector, [
            ['sample_number' => 1, 'parameter_name' => 'Packing & labelling', 'parameter_type' => 'boolean', 'value_boolean' => true, 'is_passed' => true],
            ['sample_number' => 2, 'parameter_name' => 'Quantity matches label', 'parameter_type' => 'boolean', 'value_boolean' => true, 'is_passed' => true],
        ], $produced, null, 'Pallet release check', $pallet);

        if ($lot) {
            $this->palletLots[$pallet->id][] = $lot->id;
        }
        if ($this->isSerialised($wo)) {
            $this->serialise($wo, $batch->fresh(), $pallet, null);
        }

        if ($orderDone) {
            $closeAt = max($wo->completed_at->copy()->addMinutes(30), $batch->completed_at->copy()->addMinutes(35));
            Carbon::setTestNow($closeAt);
            $fgLoc = 'FG-'.chr(65 + mt_rand(0, 3)).'-'.str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
            $pallet->update(['status' => 'closed', 'location' => $fgLoc]);
            $this->movePallet($pallet, 'LINE-END-'.$lineCode, $fgLoc, null, 'Closed pallet to finished-goods store');

            if ($ship) {
                $this->shipments[] = ['at' => $ship->copy(), 'kind' => 'pallet', 'pallet' => $pallet->id];
            }
        }
    }

    private function movePallet(Pallet $pallet, string $from, string $to, ?string $destination, string $notes): void
    {
        PalletMovement::create([
            'pallet_id' => $pallet->id,
            'from_location' => $from,
            'to_location' => $to,
            'to_destination' => $destination,
            'moved_at' => now(),
            'notes' => $notes,
            'performed_by' => $this->supervisor->id,
        ]);
    }

    /**
     * Every unit of the batch gets a serial, the semi-finished lot it was made from,
     * and a record at each station it passed — with its own cycle start and end, so
     * traceability shows where it was and how long each step took. Units lost to
     * scrap at a step are scrapped there; the good ones are packed onto the pallet.
     */
    private function serialise(WorkOrder $wo, Batch $batch, ?Pallet $pallet, ?Carbon $ship): void
    {
        $steps = $batch->steps()->where('status', BatchStep::STATUS_DONE)->orderBy('step_number')->get();
        if ($steps->isEmpty()) {
            return;
        }
        $first = $steps->first();

        $pool = BatchStepLotConsumption::query()
            ->whereIn('batch_step_id', $batch->steps()->pluck('id'))
            ->whereHas('materialLot.material', fn ($q) => $q->where('is_manufactured', true))
            ->with('materialLot')->orderBy('id')->get()
            ->map(fn ($c) => ['lot' => $c->materialLot->lot_number, 'left' => (int) ceil((float) $c->quantity_consumed)])
            ->all();
        if ($pool === []) {
            // Still running: genealogy is written at batch completion, the lots are
            // already picked for it — the units carry what was issued to the batch.
            $pool = DB::table('allocation_lot_picks as p')
                ->join('material_allocations as a', 'a.id', '=', 'p.material_allocation_id')
                ->join('material_lots as l', 'l.id', '=', 'p.material_lot_id')
                ->join('materials as m', 'm.id', '=', 'l.material_id')
                ->where('a.batch_id', $batch->id)->where('m.is_manufactured', true)
                ->orderBy('p.id')->get(['l.lot_number', 'p.picked_qty'])
                ->map(fn ($p) => ['lot' => $p->lot_number, 'left' => (int) ceil((float) $p->picked_qty)])
                ->all();
        }
        $lastLot = $pool !== [] ? end($pool)['lot'] : null;

        $units = [];
        $n = (int) $batch->target_qty;
        $starter = $this->operators[$this->lineOfStation[$first->workstation_id] ?? ''] ?? $this->supervisor;
        for ($i = 0; $i < $n; $i++) {
            Carbon::setTestNow($first->started_at->copy()->addSeconds($i * 5));
            $unit = $this->serials->registerUnit($this->lots->generate($wo->productType, LotSequence::PURPOSE_UNIT_SERIAL), [
                'work_order_id' => $wo->id,
                'batch_id' => $batch->id,
                'material_id' => $this->fgMaterial[$wo->product_type_id] ?? null,
                'status' => SerialUnit::STATUS_IN_PRODUCTION,
            ]);
            while ($pool !== [] && $pool[0]['left'] <= 0) {
                array_shift($pool);
            }
            $lotNo = $pool !== [] ? $pool[0]['lot'] : $lastLot;
            if ($wo->productType?->code === 'KIT40') {
                $this->bindKitComponents($unit, $first, $starter);
            } elseif ($lotNo) {
                $this->serials->bindComponent($unit, $lotNo, $starter, ['batch_step_id' => $first->id, 'workstation_id' => $first->workstation_id]);
                if ($pool !== []) {
                    $pool[0]['left']--;
                }
            }
            $units[] = $unit;
        }

        foreach ($steps as $step) {
            $operator = $this->operators[$this->lineOfStation[$step->workstation_id] ?? ''] ?? $this->supervisor;
            $prefix = $this->prefixOf($step->workstation_id);
            $alive = count($units);
            $scrapN = min($alive, (int) $step->scrap_qty);
            $scrapAt = $scrapN > 0 ? array_flip((array) array_rand(range(0, $alive - 1), $scrapN)) : [];
            $span = max(60, $step->started_at->diffInSeconds($step->completed_at, true));
            $setup = min($span * 0.4, (float) ($step->setup_time_minutes ?? 0) * 60);
            $cycle = ($span - $setup) / max(1, $alive);
            $survivors = [];

            foreach ($units as $i => $unit) {
                $from = $step->started_at->copy()->addSeconds((int) ($setup + $cycle * $i));
                $to = $from->copy()->addSeconds((int) max(1, $cycle));
                Carbon::setTestNow($to);
                $scrapped = isset($scrapAt[$i]);
                $params = ['event' => 'step', 'station' => $this->stationCode[$step->workstation_id] ?? null, 'started_at' => $from->toIso8601String(), 'ended_at' => $to->toIso8601String()];
                if ($prefix === 'CMM') {
                    $params['measured_ok'] = ! $scrapped;
                }
                $this->serials->recordStep($unit, $operator, $step, [
                    'result' => $scrapped ? 'fail' : 'pass',
                    'parameters' => $scrapped ? array_merge($params, ['event' => 'scrapped']) : $params,
                    'processed_at' => $to,
                    'notes' => $scrapped ? $this->scrapNote($step->name) : null,
                ]);
                if ($scrapped) {
                    $unit->update(['status' => SerialUnit::STATUS_SCRAPPED]);
                } else {
                    if ($prefix === 'PACK' && $pallet) {
                        $unit->update(['pallet_id' => $pallet->id, 'packed_at' => $to]);
                    }
                    $survivors[] = $unit;
                }
            }
            $units = $survivors;
        }

        if ($batch->status === Batch::STATUS_DONE) {
            SerialUnit::whereIn('id', collect($units)->pluck('id'))->update([
                'status' => $ship ? SerialUnit::STATUS_SHIPPED : SerialUnit::STATUS_COMPLETED,
                'produced_at' => $batch->completed_at,
                'pallet_id' => $pallet?->id,
                'shipped_at' => $ship,
            ]);
        }
    }

    // ── issues ───────────────────────────────────────────────────────────────

    private function blockAt(WorkOrder $wo, Carbon $at): void
    {
        $batchIds = $wo->batches()->pluck('id');
        $current = BatchStep::whereIn('batch_id', $batchIds)->where('status', BatchStep::STATUS_IN_PROGRESS)->orderByDesc('id')->first()
            ?? BatchStep::whereIn('batch_id', $batchIds)->whereNotNull('started_at')->orderByDesc('started_at')->first();

        foreach ($this->parkedIssues as $parked) {
            if ($parked['wo'] !== $wo->id) {
                continue;
            }
            $step = $current;
            $reportedAt = $at;
            $context = $this->issueContext(Issue::find($parked['issue_id']));
            if ($context && $current?->name !== $context['step']) {
                // The order did not stop on the named operation (it had not been
                // running long enough to reach it again): pin the issue to the
                // last time it ran that operation, or to no step at all — never
                // to whichever step happened to be running.
                $step = BatchStep::whereIn('batch_id', $batchIds)->where('name', $context['step'])
                    ->whereNotNull('started_at')->orderByDesc('started_at')->first();
                if ($step) {
                    $reportedAt = $step->started_at->copy()->addMinutes(5);
                }
            }
            DB::table('issues')->where('id', $parked['issue_id'])->update([
                'status' => $parked['status'],
                'batch_step_id' => $step?->id,
                'reported_at' => $reportedAt,
                'created_at' => $reportedAt,
                'acknowledged_at' => $parked['status'] === 'ACKNOWLEDGED' ? $reportedAt->copy()->addMinutes(12) : null,
                'resolved_at' => null,
                'closed_at' => null,
                'updated_at' => $at,
            ]);
        }

        DB::table('work_orders')->where('id', $wo->id)->update([
            'status' => WorkOrder::STATUS_BLOCKED,
            'status_before_block' => WorkOrder::STATUS_IN_PROGRESS,
            'updated_at' => $at,
        ]);
    }

    /**
     * Orders nobody has touched yet were entered some time before their slot and
     * not updated since — not "a minute ago", which is when the seeder wrote them.
     */
    private function settleTimestamps(): void
    {
        foreach (WorkOrder::whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED])->whereDoesntHave('batches')->get() as $wo) {
            $start = $wo->planned_start_at ?? $this->realNow;
            $created = $start->copy()->subDays(3)->setTime(mt_rand(8, 15), mt_rand(0, 59));
            if ($created->gt($this->realNow->copy()->subHours(3))) {
                // Entered on one of the last few working days, never "just now".
                $created = $this->realNow->copy()->subDays(mt_rand(1, 4))->setTime(mt_rand(8, 15), mt_rand(0, 59));
            }
            DB::table('work_orders')->where('id', $wo->id)->update(['created_at' => $created, 'updated_at' => $created]);
            // The release baseline was written when the order book was snapshotted;
            // it cannot be older than the order it belongs to. An undated backlog
            // order has no planned start to anchor either to, which is how it came
            // out days earlier.
            DB::table('work_order_snapshots')->where('work_order_id', $wo->id)->where('created_at', '<', $created)
                ->update(['created_at' => $created, 'updated_at' => $created]);
        }
    }

    /**
     * Maintenance from the base demo: completed jobs get the times they ran, and
     * the one in progress started a short while ago instead of at seed time.
     */
    private function settleMaintenance(): void
    {
        foreach (DB::table('maintenance_events')->get() as $e) {
            $at = Carbon::parse($e->scheduled_at);
            $upd = match ($e->status) {
                'completed' => ['started_at' => $at, 'completed_at' => $at->copy()->addMinutes(mt_rand(45, 110))],
                'in_progress' => ['scheduled_at' => $this->realNow->copy()->subMinutes(25), 'started_at' => $this->realNow->copy()->subMinutes(25), 'completed_at' => null],
                default => [],
            };
            if ($upd) {
                DB::table('maintenance_events')->where('id', $e->id)->update($upd);
            }
        }
    }

    /**
     * Every other issue is pinned to a step of its order and timed inside it —
     * a step of the operation the issue names, never one picked by position: the
     * middle step of a pinion's routing is a turning step, whatever went wrong.
     *
     * An order that has not run the named step yet (a live problem on an order
     * still early in its routing) gets the issue on the batch step it concerns all
     * the same, reported just after the order last moved; with no such step at
     * all the issue is left off any step, but still timed by the order's own run.
     * Either way the seed-time timestamps never survive.
     */
    private function pinHistoricalIssues(): void
    {
        $parkedIds = array_column($this->parkedIssues, 'issue_id');
        foreach (Issue::whereNull('batch_step_id')->whereNotNull('work_order_id')->whereNotIn('id', $parkedIds)->with('issueType')->get() as $issue) {
            $context = $this->issueContext($issue);
            $orderSteps = BatchStep::whereHas('batch', fn ($q) => $q->where('work_order_id', $issue->work_order_id))->get();
            $steps = $orderSteps->filter(fn ($s) => $s->started_at && $s->workstation_id)
                ->filter(fn ($s) => $context
                    ? $s->name === $context['step']
                    : (in_array($this->prefixOf($s->workstation_id), self::SCRAP_STATIONS, true) || $issue->issueType?->code === 'MATERIAL_CERT'))
                ->sortBy('started_at')->values();
            $lastMoved = $orderSteps->filter(fn ($s) => $s->started_at)->sortByDesc('started_at')->first()?->started_at;

            if ($steps->isNotEmpty()) {
                $step = $issue->issueType?->code === 'MATERIAL_CERT' ? $steps->first() : $steps[intdiv($steps->count(), 2)];
                $at = $step->started_at->copy()->addMinutes(mt_rand(5, 25));
            } else {
                $step = $context ? $orderSteps->where('name', $context['step'])->sortByDesc('id')->first() : null;
                $at = ($lastMoved ?? $this->realNow->copy()->subMinutes(30))->copy()->addMinutes(mt_rand(5, 25));
            }
            if ($at->gt($this->realNow->copy()->subMinutes(5))) {
                $at = $this->realNow->copy()->subMinutes(5);
            }
            $updates = ['batch_step_id' => $step?->id, 'reported_at' => $at, 'created_at' => $at, 'updated_at' => $at];
            foreach (['acknowledged_at' => 10, 'resolved_at' => 55, 'closed_at' => 120] as $col => $offset) {
                if ($issue->{$col} !== null) {
                    $stamp = $at->copy()->addMinutes($offset);
                    $updates[$col] = $stamp->gt($this->realNow) ? $this->realNow : $stamp;
                }
            }
            DB::table('issues')->where('id', $issue->id)->update($updates);
        }
    }
}
