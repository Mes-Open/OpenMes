<?php

namespace Database\Seeders;

use App\Enums\RevisionLifecycle;
use App\Models\Batch;
use App\Models\BatchLabTest;
use App\Models\BatchReleaseCheck;
use App\Models\BatchStep;
use App\Models\BatchWeighingRecord;
use App\Models\BomItem;
use App\Models\Customer;
use App\Models\CustomerFlavorStandard;
use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\PackagingChecklist;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Models\QualityCheck;
use App\Models\QualityCheckSample;
use App\Models\QualityCheckTemplate;
use App\Models\Shift;
use App\Models\StockDocument;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Quality\CoaService;
use App\Services\Quality\DispositionService;
use App\Services\Quality\InboundInspectionService;
use App\Services\Quality\MaterialLotDisposalService;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 新茶饮演示库：先清空业务数据，再建立一套“品牌—产品—配方—工艺—批次—质量—COA”的完整演示。
 *
 * 保留 users / roles / permissions / system_settings / tenants / 基础字典，
 * 以便登录和系统配置不受影响。所有编码以 NT- 开头。
 */
class NewTeaDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->resetBusinessData();
            $this->normalizeReferenceData();

            $admin = User::where('username', 'admin')->firstOrFail();
            $qa = User::where('username', 'qa.lead')->firstOrFail();
            Auth::login($admin);

            $data = $this->seedMasters($admin);
            $this->call(TeaBaseDemoSeeder::class);
            $this->seedInbound($data, $admin);
            $this->seedBatchesAndQuality($data, $admin, $qa);
            $this->call(FlowerLotDemoSeeder::class);
            $this->call(BlendFormulaDemoSeeder::class);

            Auth::logout();

            $this->command?->info('New tea beverage demo seeded: '
                .count($data['products']).' products / '
                .Batch::count().' batches / '
                .StockDocument::count().' stock docs');
        });
    }

    private function resetBusinessData(): void
    {
        $keep = [
            'migrations', 'users', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
            'model_has_permissions', 'tenants', 'system_settings', 'sessions', 'password_reset_tokens',
            'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'unit_aliases', 'material_types', 'issue_types', 'downtime_reasons', 'scrap_reasons',
            'line_statuses', 'label_templates', 'dashboard_widgets', 'custom_field_definitions',
            'view_templates', 'priority_rules', 'changeover_rules', 'migrations',
        ];

        // users 表通过 workstation_id / worker_id 反向引用工位与员工。
        // 若直接 TRUNCATE 这两个父表，CASCADE 会连 users 一起清空，
        // 登录账号就没了。因此这几张结构表最后用 DELETE 处理。
        $deleteAfterTruncate = ['workstations', 'workers', 'lines', 'warehouses', 'workstation_types'];

        $tables = DB::table('pg_tables')
            ->where('schemaname', 'public')
            ->pluck('tablename')
            ->reject(fn ($table) => in_array($table, $keep, true) || in_array($table, $deleteAfterTruncate, true))
            ->values()
            ->all();

        if ($tables !== []) {
            $quoted = implode(', ', array_map(fn ($table) => '"'.$table.'"', $tables));
            DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
        }

        DB::table('workers')->delete();
        DB::table('workstations')->delete();
        DB::table('lines')->delete();
        DB::table('warehouses')->delete();
        DB::table('workstation_types')->delete();
    }

    private function normalizeReferenceData(): void
    {
        DB::table('material_types')->where('id', 1)->update(['name' => '原料']);
        DB::table('material_types')->where('id', 2)->update(['name' => '半成品']);
        DB::table('material_types')->where('id', 3)->update(['name' => '包材']);
        DB::table('material_types')->where('id', 4)->update(['name' => '辅料']);

        DB::table('line_statuses')->where('id', 1)->update(['name' => '待生产']);
        DB::table('line_statuses')->where('id', 2)->update(['name' => '生产中']);
        DB::table('line_statuses')->where('id', 3)->update(['name' => '已完成']);

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true), 'updated_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'default_currency'],
            ['value' => json_encode('CNY'), 'updated_at' => now()],
        );
    }

    private function seedMasters(User $admin): array
    {
        $customers = [
            'qinglan' => ['code' => 'NT-CUST-QINGLAN', 'name' => '青岚茶事', 'tier' => 'gold', 'score' => 88, 'notes' => '高端现制茶饮，主打冷萃茶底与鲜果茶。'],
            'shiguang' => ['code' => 'NT-CUST-SHIGUANG', 'name' => '拾光茶社', 'tier' => 'silver', 'score' => 82, 'notes' => '社区型茶饮，主打高香单丛与柠檬茶。'],
            'guoyu' => ['code' => 'NT-CUST-GUOYU', 'name' => '果屿鲜果茶', 'tier' => 'gold', 'score' => 90, 'notes' => '水果茶专门店，强调果肉含量与鲜度。'],
            'yunzhan' => ['code' => 'NT-CUST-YUNZHAN', 'name' => '云盏奶盖', 'tier' => 'silver', 'score' => 84, 'notes' => '奶盖茶品牌，关注乳脂与茶汤分层。'],
            'chashanxu' => ['code' => 'NT-CUST-CHASHANXU', 'name' => '茶山序', 'tier' => 'bronze', 'score' => 76, 'notes' => '区域茶饮集合店，作为备用客户。'],
        ];
        $customerModels = [];
        foreach ($customers as $key => $row) {
            $customerModels[$key] = Customer::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'tier' => $row['tier'],
                'payment_score' => $row['score'],
                'notes' => $row['notes'],
                'is_active' => true,
            ]);
        }

        $materials = [
            'green' => ['code' => 'NT-RM-TEA-GREEN', 'name' => '贵州绿茶毛茶（一级）', 'type' => 1, 'uom' => 'kg', 'supplier' => '黔南绿源茶业'],
            'jasmine' => ['code' => 'NT-RM-TEA-JASMINE', 'name' => '广西茉莉花茶胚', 'type' => 1, 'uom' => 'kg', 'supplier' => '黔南绿源茶业'],
            'dongfang' => ['code' => 'NT-RM-TEA-DONGFANG', 'name' => '凤凰单丛鸭屎香', 'type' => 1, 'uom' => 'kg', 'supplier' => '闽南单丛茶业'],
            'oolong' => ['code' => 'NT-RM-TEA-OOLONG', 'name' => '武夷焙火乌龙', 'type' => 1, 'uom' => 'kg', 'supplier' => '闽南单丛茶业'],
            'mango' => ['code' => 'NT-RM-FRUIT-MANGO', 'name' => '芒果果泥', 'type' => 1, 'uom' => 'kg', 'supplier' => '鲜果集配'],
            'grape' => ['code' => 'NT-RM-FRUIT-GRAPE', 'name' => '葡萄果肉', 'type' => 1, 'uom' => 'kg', 'supplier' => '鲜果集配'],
            'lemon' => ['code' => 'NT-RM-FRUIT-LEMON', 'name' => '香水柠檬原浆', 'type' => 1, 'uom' => 'kg', 'supplier' => '鲜果集配'],
            'milk' => ['code' => 'NT-RM-DAIRY-MILK', 'name' => '鲜牛奶', 'type' => 1, 'uom' => 'L', 'supplier' => '清源乳业'],
            'cream' => ['code' => 'NT-RM-DAIRY-CREAM', 'name' => '淡奶油', 'type' => 1, 'uom' => 'L', 'supplier' => '清源乳业'],
            'sugar' => ['code' => 'NT-RM-SUGAR', 'name' => '冰糖糖浆', 'type' => 1, 'uom' => 'kg', 'supplier' => '甜源食品'],
            'cup' => ['code' => 'NT-PKG-CUP-700', 'name' => '700ml 注塑杯', 'type' => 3, 'uom' => 'pcs', 'supplier' => '包材联盟'],
            'lid' => ['code' => 'NT-PKG-LID-700', 'name' => '700ml 杯盖', 'type' => 3, 'uom' => 'pcs', 'supplier' => '包材联盟'],
            'straw' => ['code' => 'NT-PKG-STRAW', 'name' => '纸吸管', 'type' => 3, 'uom' => 'pcs', 'supplier' => '包材联盟'],
            'label' => ['code' => 'NT-PKG-LABEL', 'name' => '杯身标签', 'type' => 3, 'uom' => 'pcs', 'supplier' => '包材联盟'],
            'carton' => ['code' => 'NT-PKG-CARTON', 'name' => '成品外箱', 'type' => 3, 'uom' => 'pcs', 'supplier' => '包材联盟'],
        ];
        $materialModels = [];
        foreach ($materials as $key => $row) {
            $materialModels[$key] = Material::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'material_type_id' => $row['type'],
                'unit_of_measure' => $row['uom'],
                'tracking_type' => $row['type'] === 3 ? 'none' : 'batch',
                'supplier_name' => $row['supplier'],
                'is_active' => true,
            ]);
        }

        $warehouses = [
            'raw' => ['code' => 'NT-WH-RM', 'name' => '新茶饮原料仓', 'kind' => Warehouse::KIND_RAW_MATERIAL, 'default' => true],
            'finished' => ['code' => 'NT-WH-FG', 'name' => '新茶饮成品仓', 'kind' => Warehouse::KIND_FINISHED_GOODS, 'default' => false],
        ];
        $warehouseModels = [];
        foreach ($warehouses as $key => $row) {
            $warehouseModels[$key] = Warehouse::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'kind' => $row['kind'],
                'is_default' => $row['default'],
                'is_active' => true,
            ]);
        }

        $lines = [
            'brew' => ['code' => 'NT-LINE-BREW', 'name' => '茶底萃取线', 'description' => '低温萃取、过滤、冷却、灌装。'],
            'fruit' => ['code' => 'NT-LINE-FRUIT', 'name' => '果茶调配线', 'description' => '果肉称重、茶汤调配、灌装封口。'],
            'milk' => ['code' => 'NT-LINE-MILK', 'name' => '奶盖茶线', 'description' => '茶汤萃取、奶盖打发、分层灌装。'],
        ];
        $lineModels = [];
        foreach ($lines as $key => $row) {
            $lineModels[$key] = Line::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => $row['description'],
                'warehouse_id' => $warehouseModels['raw']->id,
                'is_active' => true,
                'planning_efficiency_pct' => 85,
            ]);
        }

        $workstations = [
            'receive' => ['code' => 'NT-WS-RECEIVE', 'name' => '原料接收与称重', 'type' => 'weighing', 'line' => 'brew'],
            'extract' => ['code' => 'NT-WS-EXTRACT', 'name' => '茶汤萃取', 'type' => 'extraction', 'line' => 'brew'],
            'filter' => ['code' => 'NT-WS-FILTER', 'name' => '过滤冷却', 'type' => 'filtration', 'line' => 'brew'],
            'blend' => ['code' => 'NT-WS-BLEND', 'name' => '果料/奶盖调配', 'type' => 'blending', 'line' => 'fruit'],
            'fill' => ['code' => 'NT-WS-FILL', 'name' => '灌装', 'type' => 'filling', 'line' => 'fruit'],
            'seal' => ['code' => 'NT-WS-SEAL', 'name' => '封口贴标', 'type' => 'packing', 'line' => 'fruit'],
            'pack' => ['code' => 'NT-WS-PACK', 'name' => '装箱入库', 'type' => 'packing', 'line' => 'milk'],
            'qc' => ['code' => 'NT-WS-QC', 'name' => '成品检验', 'type' => 'quality', 'line' => 'milk'],
        ];
        $workstationModels = [];
        foreach ($workstations as $key => $row) {
            $workstationModels[$key] = \App\Models\Workstation::create([
                'line_id' => $lineModels[$row['line']]->id,
                'code' => $row['code'],
                'name' => $row['name'],
                'workstation_type' => $row['type'],
                'ideal_rate_per_hour' => 600,
                'is_active' => true,
            ]);
        }

        foreach ([['NT-SE', '早班', '08:00:00', '16:00:00', 1, 'brew'], ['NT-SL', '中班', '16:00:00', '00:00:00', 2, 'fruit'], ['NT-SN', '晚班', '00:00:00', '08:00:00', 3, 'milk']] as [$code, $name, $start, $end, $sort, $line]) {
            Shift::create([
                'code' => $code,
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'line_id' => $lineModels[$line]->id,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }

        $processes = [
            'brew' => [
                ['number' => 1, 'name' => '原料核对与领料', 'ws' => 'receive', 'minutes' => 15, 'kind' => 'production', 'instruction' => '核对茶叶品名、批号、数量、合格状态与保质期；未放行原料不得上线。'],
                ['number' => 2, 'name' => '拼配称重', 'ws' => 'receive', 'minutes' => 40, 'kind' => 'production', 'instruction' => '按配方称取绿茶与茉莉花茶胚，偏差控制在 ±0.5%。'],
                ['number' => 3, 'name' => '低温萃取', 'ws' => 'extract', 'minutes' => 90, 'kind' => 'production', 'instruction' => '按萃取曲线控制水温、时间与流量。'],
                ['number' => 4, 'name' => '过滤与冷却', 'ws' => 'filter', 'minutes' => 45, 'kind' => 'production', 'instruction' => '过滤后快速冷却至 4–8℃，记录浊度。'],
                ['number' => 5, 'name' => '灌装', 'ws' => 'fill', 'minutes' => 40, 'kind' => 'production', 'instruction' => '按 5kg /桶灌装，封口前检查桶口与内袋。'],
                ['number' => 6, 'name' => '贴标与成品检验', 'ws' => 'qc', 'minutes' => 25, 'kind' => 'production', 'instruction' => '贴批次标签，按客户标准抽样检验并放行。'],
            ],
            'fruit' => [
                ['number' => 1, 'name' => '原料核对与领料', 'ws' => 'receive', 'minutes' => 15, 'kind' => 'production', 'instruction' => '核对果泥、茶汤、糖浆的批号、温度与状态。'],
                ['number' => 2, 'name' => '果料称重', 'ws' => 'blend', 'minutes' => 25, 'kind' => 'production', 'instruction' => '按每杯配方称取果肉/果泥，关键果料双人复核。'],
                ['number' => 3, 'name' => '茶汤调配', 'ws' => 'blend', 'minutes' => 20, 'kind' => 'production', 'instruction' => '按糖度与酸度调整茶汤，记录 Brix。'],
                ['number' => 4, 'name' => '灌装', 'ws' => 'fill', 'minutes' => 35, 'kind' => 'production', 'instruction' => '按杯型灌装，控制净含量与液位。'],
                ['number' => 5, 'name' => '封口贴标', 'ws' => 'seal', 'minutes' => 20, 'kind' => 'production', 'instruction' => '封口后检查杯盖、吸管与标签信息。'],
                ['number' => 6, 'name' => '装箱入库', 'ws' => 'pack', 'minutes' => 20, 'kind' => 'packing', 'instruction' => '按箱规装箱，贴上箱唛并入库待检。'],
                ['number' => 7, 'name' => '成品检验', 'ws' => 'qc', 'minutes' => 25, 'kind' => 'production', 'instruction' => '按客户标准检验糖度、果肉含量、感官、净含量。'],
            ],
            'milk' => [
                ['number' => 1, 'name' => '原料核对与领料', 'ws' => 'receive', 'minutes' => 15, 'kind' => 'production', 'instruction' => '核对茶叶、牛奶、奶油、糖浆的批号与冷链温度。'],
                ['number' => 2, 'name' => '茶汤萃取', 'ws' => 'extract', 'minutes' => 60, 'kind' => 'production', 'instruction' => '按焙火乌龙萃取曲线控制水温与时间。'],
                ['number' => 3, 'name' => '奶盖打发', 'ws' => 'blend', 'minutes' => 20, 'kind' => 'production', 'instruction' => '淡奶油与牛奶按比例打发，记录温度与稠度。'],
                ['number' => 4, 'name' => '分层灌装', 'ws' => 'fill', 'minutes' => 35, 'kind' => 'production', 'instruction' => '先茶汤后奶盖，控制分层高度与净含量。'],
                ['number' => 5, 'name' => '封口贴标', 'ws' => 'seal', 'minutes' => 20, 'kind' => 'production', 'instruction' => '封口后检查杯盖、吸管与标签。'],
                ['number' => 6, 'name' => '装箱入库', 'ws' => 'pack', 'minutes' => 20, 'kind' => 'packing', 'instruction' => '按箱规装箱并入库待检。'],
                ['number' => 7, 'name' => '成品检验', 'ws' => 'qc', 'minutes' => 25, 'kind' => 'production', 'instruction' => '按客户标准检验脂肪、蛋白、糖度、感官与净含量。'],
            ],
        ];

        $qualitySpecs = [
            'tea' => [
                ['name' => '水分', 'type' => 'measurement', 'unit' => '%', 'min' => 5.0, 'max' => 8.0],
                ['name' => '感官-香气', 'type' => 'pass_fail'],
                ['name' => '感官-滋味', 'type' => 'pass_fail'],
                ['name' => '感官-汤色', 'type' => 'pass_fail'],
                ['name' => '拼配比例偏差', 'type' => 'measurement', 'unit' => '%', 'min' => -0.5, 'max' => 0.5],
                ['name' => '异物', 'type' => 'pass_fail'],
                ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 4950, 'max' => 5050],
            ],
            'lemon' => [
                ['name' => '糖度', 'type' => 'measurement', 'unit' => 'Brix', 'min' => 8.0, 'max' => 14.0],
                ['name' => '酸度', 'type' => 'measurement', 'unit' => '%', 'min' => 0.5, 'max' => 1.5],
                ['name' => '香气', 'type' => 'pass_fail'],
                ['name' => '苦涩', 'type' => 'pass_fail'],
                ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 500, 'max' => 520],
            ],
            'milk' => [
                ['name' => '糖度', 'type' => 'measurement', 'unit' => 'Brix', 'min' => 8.0, 'max' => 14.0],
                ['name' => '脂肪', 'type' => 'measurement', 'unit' => '%', 'min' => 3.0, 'max' => 5.0],
                ['name' => '蛋白质', 'type' => 'measurement', 'unit' => '%', 'min' => 2.8, 'max' => 4.0],
                ['name' => '感官-奶盖', 'type' => 'pass_fail'],
                ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 500, 'max' => 520],
                ['name' => '温度', 'type' => 'measurement', 'unit' => '℃', 'min' => 2, 'max' => 6],
            ],
            'mango' => [
                ['name' => '糖度', 'type' => 'measurement', 'unit' => 'Brix', 'min' => 10.0, 'max' => 16.0],
                ['name' => '果肉含量', 'type' => 'measurement', 'unit' => '%', 'min' => 10.0, 'max' => 25.0],
                ['name' => '感官-风味', 'type' => 'pass_fail'],
                ['name' => '异物', 'type' => 'pass_fail'],
                ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 600, 'max' => 620],
            ],
            'grape' => [
                ['name' => '糖度', 'type' => 'measurement', 'unit' => 'Brix', 'min' => 10.0, 'max' => 16.0],
                ['name' => '果肉含量', 'type' => 'measurement', 'unit' => '%', 'min' => 10.0, 'max' => 25.0],
                ['name' => '感官-风味', 'type' => 'pass_fail'],
                ['name' => '异物', 'type' => 'pass_fail'],
                ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 600, 'max' => 620],
            ],
        ];

        $products = [
            'jasmine' => [
                'code' => 'NT-FG-JASMINE-COLD', 'name' => '茉莉冷萃茶底', 'category' => '茶底-冷萃', 'uom' => 'kg',
                'line' => 'brew', 'process' => 'brew', 'quality' => 'tea', 'customer' => 'qinglan',
                'batch_qty' => 500, 'unit_price' => 68.00,
                'bom' => [
                    ['material' => 'green', 'qty' => 0.35, 'step' => 2],
                    ['material' => 'jasmine', 'qty' => 0.65, 'step' => 2],
                    ['material' => 'carton', 'qty' => 0.01, 'step' => 5],
                    ['material' => 'label', 'qty' => 1.0, 'step' => 6],
                ],
                'standard' => [
                    'code' => 'NT-CFS-QINGLAN-JASMINE-V1', 'name' => '青岚茉莉冷萃标准', 'moisture' => [5.0, 8.0],
                    'aroma' => '茉莉花香鲜灵、持久，无闷味', 'taste' => '鲜爽、回甘，无苦涩', 'liquor' => '黄绿明亮',
                    'net' => [4950, 5050], 'lab' => ['农残', '重金属', '微生物'], 'external' => true,
                    'packaging' => '5kg 食品级桶装，内袋热封，标签含品名、批号、净含量、生产日期。',
                ],
            ],
            'lemon' => [
                'code' => 'NT-FG-DONGFANG-LEMON', 'name' => '鸭屎香柠檬茶', 'category' => '果茶-柠檬茶', 'uom' => '杯',
                'line' => 'fruit', 'process' => 'fruit', 'quality' => 'lemon', 'customer' => 'shiguang',
                'batch_qty' => 5000, 'unit_price' => 16.00,
                'bom' => [
                    ['material' => 'dongfang', 'qty' => 0.006, 'step' => 2],
                    ['material' => 'lemon', 'qty' => 0.008, 'step' => 2],
                    ['material' => 'sugar', 'qty' => 0.025, 'step' => 2],
                    ['material' => 'cup', 'qty' => 1.0, 'step' => 4],
                    ['material' => 'lid', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'straw', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'label', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'carton', 'qty' => 0.02, 'step' => 6],
                ],
                'standard' => [
                    'code' => 'NT-CFS-SHIGUANG-LEMON-V1', 'name' => '拾光鸭屎香柠檬茶标准', 'moisture' => [0.0, 100.0],
                    'aroma' => '鸭屎香高香明显，柠檬清香自然', 'taste' => '酸甜平衡，回甘明显，无苦涩', 'liquor' => '浅金黄、清亮',
                    'net' => [500, 520], 'lab' => [], 'external' => false,
                    'packaging' => '700ml 注塑杯，杯盖密封，贴杯身标签，配纸吸管。',
                ],
            ],
            'baked' => [
                'code' => 'NT-FG-OOLONG-CREAM', 'name' => '焙茶奶盖', 'category' => '奶盖茶', 'uom' => '杯',
                'line' => 'milk', 'process' => 'milk', 'quality' => 'milk', 'customer' => 'yunzhan',
                'batch_qty' => 4000, 'unit_price' => 18.00,
                'bom' => [
                    ['material' => 'oolong', 'qty' => 0.005, 'step' => 2],
                    ['material' => 'milk', 'qty' => 0.05, 'step' => 3],
                    ['material' => 'cream', 'qty' => 0.02, 'step' => 3],
                    ['material' => 'sugar', 'qty' => 0.02, 'step' => 3],
                    ['material' => 'cup', 'qty' => 1.0, 'step' => 4],
                    ['material' => 'lid', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'straw', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'label', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'carton', 'qty' => 0.02, 'step' => 6],
                ],
                'standard' => [
                    'code' => 'NT-CFS-YUNZHAN-BAKED-V1', 'name' => '云盏焙茶奶盖标准', 'moisture' => [0.0, 100.0],
                    'aroma' => '焙火香与奶香协调，无焦苦', 'taste' => '奶盖绵密，茶汤醇厚，甜度适中', 'liquor' => '茶褐明亮，奶盖洁白',
                    'net' => [500, 520], 'lab' => ['微生物'], 'external' => true,
                    'packaging' => '700ml 注塑杯，奶盖层不低于 2cm，杯盖密封。',
                ],
            ],
            'mango' => [
                'code' => 'NT-FG-MANGO-SAGO', 'name' => '杨枝甘露', 'category' => '水果茶-芒果', 'uom' => '杯',
                'line' => 'fruit', 'process' => 'fruit', 'quality' => 'mango', 'customer' => 'guoyu',
                'batch_qty' => 3000, 'unit_price' => 20.00,
                'bom' => [
                    ['material' => 'mango', 'qty' => 0.05, 'step' => 2],
                    ['material' => 'milk', 'qty' => 0.03, 'step' => 3],
                    ['material' => 'sugar', 'qty' => 0.02, 'step' => 3],
                    ['material' => 'cup', 'qty' => 1.0, 'step' => 4],
                    ['material' => 'lid', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'straw', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'label', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'carton', 'qty' => 0.02, 'step' => 6],
                ],
                'standard' => [
                    'code' => 'NT-CFS-GUOYU-MANGO-V1', 'name' => '果屿杨枝甘露标准', 'moisture' => [0.0, 100.0],
                    'aroma' => '芒果香浓郁自然，无熟化异味', 'taste' => '果肉感明显，甜酸平衡，奶香柔和', 'liquor' => '金黄色，果肉分布均匀',
                    'net' => [600, 620], 'lab' => ['微生物', '农残'], 'external' => true,
                    'packaging' => '700ml 注塑杯，果肉分层可见，杯盖密封，贴杯身标签。',
                ],
            ],
            'grape' => [
                'code' => 'NT-FG-GRAPE-JASMINE', 'name' => '多肉葡萄', 'category' => '水果茶-葡萄', 'uom' => '杯',
                'line' => 'fruit', 'process' => 'fruit', 'quality' => 'grape', 'customer' => 'qinglan',
                'batch_qty' => 4000, 'unit_price' => 19.00,
                'bom' => [
                    ['material' => 'grape', 'qty' => 0.05, 'step' => 2],
                    ['material' => 'jasmine', 'qty' => 0.004, 'step' => 2],
                    ['material' => 'sugar', 'qty' => 0.03, 'step' => 3],
                    ['material' => 'cup', 'qty' => 1.0, 'step' => 4],
                    ['material' => 'lid', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'straw', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'label', 'qty' => 1.0, 'step' => 5],
                    ['material' => 'carton', 'qty' => 0.02, 'step' => 6],
                ],
                'standard' => [
                    'code' => 'NT-CFS-QINGLAN-GRAPE-V1', 'name' => '青岚多肉葡萄标准', 'moisture' => [0.0, 100.0],
                    'aroma' => '茉莉茶香与葡萄香协调', 'taste' => '果肉饱满，茶感清爽，甜而不腻', 'liquor' => '紫红透亮',
                    'net' => [600, 620], 'lab' => [], 'external' => false,
                    'packaging' => '700ml 注塑杯，果肉层可见，杯盖密封，贴杯身标签。',
                ],
            ],
        ];

        $productModels = [];
        foreach ($products as $key => $row) {
            $productType = ProductType::create([
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => $row['category'].'｜新茶饮演示产品',
                'category' => $row['category'],
                'unit_of_measure' => $row['uom'],
                'is_active' => true,
            ]);

            $template = ProcessTemplate::create([
                'product_type_id' => $productType->id,
                'name' => $row['name'].'标准工艺 v1',
                'version' => 1,
                'ideal_cycle_minutes' => array_sum(array_column($processes[$row['process']], 'minutes')),
                'is_active' => true,
            ]);

            $steps = [];
            foreach ($processes[$row['process']] as $step) {
                $steps[$step['number']] = TemplateStep::create([
                    'process_template_id' => $template->id,
                    'step_number' => $step['number'],
                    'name' => $step['name'],
                    'kind' => $step['kind'],
                    'instruction' => $step['instruction'],
                    'estimated_duration_minutes' => $step['minutes'],
                    'requires_confirmation' => true,
                    'workstation_id' => $workstationModels[$step['ws']]->id,
                    'parameters' => ['产品' => $row['name'], '步骤' => $step['name']],
                    'config' => $step['kind'] === 'packing' ? ['unit' => 'carton', 'carton_capacity' => 12] : null,
                ]);
            }

            foreach ($row['bom'] as $sort => $bom) {
                BomItem::create([
                    'process_template_id' => $template->id,
                    'template_step_id' => $steps[$bom['step']]->id,
                    'material_id' => $materialModels[$bom['material']]->id,
                    'quantity_per_unit' => $bom['qty'],
                    'per' => BomItem::PER_UNIT,
                    'scrap_percentage' => 0.3,
                    'consumed_at' => 'start',
                    'sort_order' => $sort,
                ]);
            }

            ProductRevision::create([
                'product_type_id' => $productType->id,
                'revision_code' => 'NT-'.$key.'-V01',
                'description' => $row['name'].'｜新茶饮演示配方',
                'lifecycle_status' => RevisionLifecycle::Released,
                'process_template_id' => $template->id,
                'external_ref' => 'NT-SKU-'.strtoupper($key).'-V01',
                'effective_from' => now(),
                'released_at' => now(),
                'released_by_id' => $admin->id,
            ]);

            QualityCheckTemplate::create([
                'process_template_id' => $template->id,
                'name' => $row['name'].'成品检验',
                'min_checks_per_batch' => 1,
                'min_checks_per_day' => 1,
                'samples_per_check' => 2,
                'parameters' => $qualitySpecs[$row['quality']],
            ]);

            CustomerFlavorStandard::create([
                'code' => $row['standard']['code'],
                'name' => $row['standard']['name'],
                'version' => 'V1',
                'status' => CustomerFlavorStandard::STATUS_ACTIVE,
                'customer_id' => $customerModels[$row['customer']]->id,
                'product_type_id' => $productType->id,
                'effective_from' => '2026-01-01',
                'moisture_min' => $row['standard']['moisture'][0],
                'moisture_max' => $row['standard']['moisture'][1],
                'sensory_aroma' => $row['standard']['aroma'],
                'sensory_taste' => $row['standard']['taste'],
                'sensory_liquor' => $row['standard']['liquor'],
                'blend_tolerance' => 0.5,
                'foreign_matter_required' => true,
                'requires_external_lab' => $row['standard']['external'],
                'required_lab_items' => $row['standard']['lab'],
                'net_weight_min' => $row['standard']['net'][0],
                'net_weight_max' => $row['standard']['net'][1],
                'packaging_requirement' => $row['standard']['packaging'],
                'notes' => '新茶饮演示标准；正式上线前替换为客户签字版。',
                'created_by_id' => $admin->id,
                'approved_by_id' => $admin->id,
                'approved_at' => now(),
            ]);

            DB::table('line_product_type')->insert([
                'line_id' => $lineModels[$row['line']]->id,
                'product_type_id' => $productType->id,
            ]);

            $productModels[$key] = [
                'type' => $productType,
                'template' => $template,
                'steps' => $steps,
                'revision' => ProductRevision::where('product_type_id', $productType->id)->first(),
                'specs' => $qualitySpecs[$row['quality']],
                'meta' => $row,
            ];
        }

        return [
            'admin' => $admin,
            'customers' => $customerModels,
            'materials' => $materialModels,
            'warehouses' => $warehouseModels,
            'lines' => $lineModels,
            'products' => $productModels,
            'processes' => $processes,
        ];
    }

    private function seedInbound(array $data, User $admin): void
    {
        $materials = $data['materials'];
        $warehouses = $data['warehouses'];
        $stock = app(StockDocumentService::class);
        $inspectionService = app(InboundInspectionService::class);
        $disposition = app(DispositionService::class);
        $disposal = app(MaterialLotDisposalService::class);

        $teaCriteria = [
            ['name' => '水分', 'type' => 'measurement', 'required' => true, 'unit' => '%', 'spec_min' => 5.0, 'spec_max' => 8.0],
            ['name' => '香气', 'type' => 'pass_fail', 'required' => true],
            ['name' => '滋味', 'type' => 'pass_fail', 'required' => true],
            ['name' => '汤色', 'type' => 'pass_fail', 'required' => true],
            ['name' => '农残报告', 'type' => 'pass_fail', 'required' => true],
            ['name' => '重金属报告', 'type' => 'pass_fail', 'required' => true],
            ['name' => '异物', 'type' => 'pass_fail', 'required' => true],
            ['name' => '异味', 'type' => 'pass_fail', 'required' => true],
        ];
        $fruitCriteria = [
            ['name' => '糖度', 'type' => 'measurement', 'required' => true, 'unit' => 'Brix', 'spec_min' => 10.0, 'spec_max' => 20.0],
            ['name' => '酸度', 'type' => 'measurement', 'required' => true, 'unit' => '%', 'spec_min' => 0.3, 'spec_max' => 1.5],
            ['name' => '色泽', 'type' => 'pass_fail', 'required' => true],
            ['name' => '异味', 'type' => 'pass_fail', 'required' => true],
            ['name' => '异物', 'type' => 'pass_fail', 'required' => true],
            ['name' => '微生物报告', 'type' => 'pass_fail', 'required' => true],
        ];
        $dairyCriteria = [
            ['name' => '脂肪', 'type' => 'measurement', 'required' => true, 'unit' => '%', 'spec_min' => 3.0, 'spec_max' => 4.5],
            ['name' => '蛋白质', 'type' => 'measurement', 'required' => true, 'unit' => '%', 'spec_min' => 2.8, 'spec_max' => 4.0],
            ['name' => '温度', 'type' => 'measurement', 'required' => true, 'unit' => '℃', 'spec_min' => 2, 'spec_max' => 6],
            ['name' => '微生物报告', 'type' => 'pass_fail', 'required' => true],
        ];

        $plans = [
            'green' => ['name' => 'NT-绿茶来料验收标准', 'criteria' => $teaCriteria],
            'jasmine' => ['name' => 'NT-茉莉花茶胚验收标准', 'criteria' => $teaCriteria],
            'dongfang' => ['name' => 'NT-鸭屎香单丛验收标准', 'criteria' => $teaCriteria],
            'oolong' => ['name' => 'NT-焙火乌龙验收标准', 'criteria' => $teaCriteria],
            'mango' => ['name' => 'NT-芒果果泥验收标准', 'criteria' => $fruitCriteria],
            'milk' => ['name' => 'NT-鲜牛奶验收标准', 'criteria' => $dairyCriteria],
        ];
        foreach ($plans as $key => $plan) {
            InspectionPlan::create([
                'name' => $plan['name'],
                'description' => '新茶饮演示来料验收，按物料批放行。',
                'material_id' => $materials[$key]->id,
                'criteria' => $plan['criteria'],
                'is_active' => true,
                'version' => 1,
                'published_at' => now(),
            ]);
        }

        $receipts = [
            ['key' => 'green', 'qty' => 300, 'lot' => 'NT-RM-GREEN-20261124', 'supplier' => '黔南绿源茶业', 'values' => ['水分' => 6.4], 'pass' => true, 'note' => '绿茶到货，水分与感官合格。'],
            ['key' => 'jasmine', 'qty' => 450, 'lot' => 'NT-RM-JASMINE-20261124', 'supplier' => '黔南绿源茶业', 'values' => ['水分' => 7.2], 'pass' => true, 'note' => '茉莉花茶胚到货，香气鲜灵。'],
            ['key' => 'oolong', 'qty' => 150, 'lot' => 'NT-RM-OOLONG-20261124', 'supplier' => '闽南单丛茶业', 'values' => ['水分' => 5.8], 'pass' => true, 'note' => '焙火乌龙到货，火功正常。'],
            ['key' => 'dongfang', 'qty' => 120, 'lot' => 'NT-RM-DONGFANG-20261124', 'supplier' => '闽南单丛茶业', 'values' => ['水分' => 6.1], 'pass' => true, 'note' => '鸭屎香单丛到货，高香明显。'],
            ['key' => 'mango', 'qty' => 200, 'lot' => 'NT-RM-MANGO-20261124-A', 'supplier' => '鲜果集配', 'values' => ['糖度' => 13.2, '酸度' => 0.62], 'pass' => true, 'note' => '芒果果泥到货，糖度与色泽合格。'],
            ['key' => 'mango', 'qty' => 20, 'lot' => 'NT-RM-MANGO-20261124-B', 'supplier' => '鲜果集配', 'values' => ['糖度' => 7.5, '酸度' => 0.58], 'pass' => false, 'note' => '芒果果泥糖度偏低，判不合格并退货。'],
            ['key' => 'milk', 'qty' => 400, 'lot' => 'NT-RM-MILK-20261124', 'supplier' => '清源乳业', 'values' => ['脂肪' => 3.6, '蛋白质' => 3.2, '温度' => 4.0], 'pass' => true, 'note' => '鲜牛奶到货，冷链温度正常。'],
        ];

        foreach ($receipts as $index => $row) {
            $material = $materials[$row['key']];
            $doc = $stock->createDraft([
                'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
                'warehouse_id' => $warehouses['raw']->id,
                'supplier_name' => $row['supplier'],
                'notes' => '新茶饮演示入库：'.$row['note'],
                'lines' => [[
                    'material_id' => $material->id,
                    'lot_number' => $row['lot'],
                    'quantity' => $row['qty'],
                    'unit_of_measure' => $material->unit_of_measure,
                ]],
            ], $admin);
            $stock->post($doc, $admin);

            $line = $doc->fresh()->lines()->first();
            $inspection = Inspection::where('stock_document_line_id', $line->id)->first();
            if (! $inspection) {
                $this->command?->warn('No inspection dispatched for '.$row['lot']);
                continue;
            }

            foreach ($inspection->results as $result) {
                $input = $result->criterion_type === 'measurement'
                    ? ['value_numeric' => $row['values'][$result->criterion_name] ?? 0]
                    : ['value_boolean' => ($row['values'][$result->criterion_name] ?? true)];
                $inspectionService->recordResult($result, $input);
            }

            $inspection = $inspectionService->complete($inspection->fresh('results'), $row['note'], $admin);
            $lot = $inspection->fresh('lotsTested')->lotsTested->first();

            if ($row['pass']) {
                $disposition->apply($inspection, Inspection::DISPOSITION_ACCEPT, '到货检验合格，接收使用。', $admin);
            } elseif ($lot) {
                $disposal->dispose($lot, 'return_to_supplier', (float) $row['qty'], '糖度不合格，整批退供应商。', $admin);
            }
        }

        // 非茶叶/果泥的辅料直接入库：建立库存流水，但不进入 IQC 计划。
        $direct = [
            'sugar' => [500, '甜源食品'],
            'grape' => [300, '鲜果集配'],
            'lemon' => [100, '鲜果集配'],
            'cream' => [120, '清源乳业'],
        ];
        foreach ($direct as $key => [$qty, $supplier]) {
            $doc = $stock->createDraft([
                'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
                'warehouse_id' => $warehouses['raw']->id,
                'supplier_name' => $supplier,
                'notes' => '新茶饮演示辅料入库',
                'lines' => [[
                    'material_id' => $materials[$key]->id,
                    'quantity' => $qty,
                    'unit_of_measure' => $materials[$key]->unit_of_measure,
                ]],
            ], $admin);
            $stock->post($doc, $admin);
        }

        $packDoc = $stock->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
            'warehouse_id' => $warehouses['raw']->id,
            'supplier_name' => '包材联盟',
            'notes' => '新茶饮演示包材入库',
            'lines' => collect(['cup', 'lid', 'straw', 'label'])
                ->map(fn ($key) => [
                    'material_id' => $materials[$key]->id,
                    'quantity' => 12000,
                    'unit_of_measure' => 'pcs',
                ])->push([
                    'material_id' => $materials['carton']->id,
                    'quantity' => 500,
                    'unit_of_measure' => 'pcs',
                ])->all(),
        ], $admin);
        $stock->post($packDoc, $admin);
    }

    private function seedBatchesAndQuality(array $data, User $admin, User $qa): void
    {
        $products = $data['products'];
        $coa = app(CoaService::class);

        $orders = [
            'jasmine' => ['no' => 'NT-WO-20261124-01', 'qty' => 500, 'status' => WorkOrder::STATUS_DONE, 'batch' => 3001, 'lot' => 'NT-FG-JASMINE-20261124-01', 'batch_status' => Batch::STATUS_DONE, 'coa' => 'issue'],
            'lemon' => ['no' => 'NT-WO-20261124-02', 'qty' => 5000, 'status' => WorkOrder::STATUS_DONE, 'batch' => 3002, 'lot' => 'NT-FG-LEMON-20261124-01', 'batch_status' => Batch::STATUS_DONE, 'coa' => 'issue'],
            'baked' => ['no' => 'NT-WO-20261124-03', 'qty' => 4000, 'status' => WorkOrder::STATUS_DONE, 'batch' => 3003, 'lot' => 'NT-FG-BAKED-20261124-01', 'batch_status' => Batch::STATUS_DONE, 'coa' => 'approve'],
            'mango' => ['no' => 'NT-WO-20261124-04', 'qty' => 3000, 'status' => WorkOrder::STATUS_DONE, 'batch' => 3004, 'lot' => 'NT-FG-MANGO-20261124-01', 'batch_status' => Batch::STATUS_DONE, 'coa' => 'review'],
            'grape' => ['no' => 'NT-WO-20261124-05', 'qty' => 4000, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'batch' => 3005, 'lot' => 'NT-FG-GRAPE-20261124-01', 'batch_status' => Batch::STATUS_IN_PROGRESS, 'coa' => 'none'],
        ];

        foreach ($orders as $key => $order) {
            $product = $products[$key];
            $meta = $product['meta'];
            $line = $data['lines'][$meta['line']];

            $bomSnapshot = BomItem::with(['material', 'templateStep'])
                ->where('process_template_id', $product['template']->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($item) => [
                    'component_kind' => 'material',
                    'material_id' => $item->material_id,
                    'product_type_id' => null,
                    'material_code' => $item->material?->code,
                    'material_name' => $item->material?->name,
                    'material_type' => $item->material?->material_type_id === 3 ? 'packaging' : 'raw_material',
                    'tracking_type' => $item->material?->tracking_type,
                    'unit_of_measure' => $item->material?->unit_of_measure,
                    'quantity_per_unit' => (float) $item->quantity_per_unit,
                    'per' => 'unit',
                    'quantity_per_basis' => (float) $item->quantity_per_unit,
                    'scrap_percentage' => (float) $item->scrap_percentage,
                    'consumed_at' => $item->consumed_at,
                    'step_number' => $item->templateStep?->step_number,
                    'external_code' => null,
                    'external_system' => null,
                ])->values()->all();

            $stepSnapshot = $product['template']->steps()->with('workstation')->orderBy('step_number')->get()->map(fn ($step) => [
                'step_number' => $step->step_number,
                'name' => $step->name,
                'kind' => $step->kind,
                'config' => $step->config,
                'instruction' => $step->instruction,
                'requires_confirmation' => (bool) $step->requires_confirmation,
                'estimated_duration_minutes' => $step->estimated_duration_minutes,
                'setup_time_minutes' => $step->setup_time_minutes,
                'run_time_per_unit_minutes' => $step->run_time_per_unit_minutes,
                'required_operators' => 1,
                'required_skill_ids' => [],
                'workstation_id' => $step->workstation_id,
                'workstation_name' => $step->workstation?->name,
                'workstation_type_id' => $step->workstation_type_id,
                'parameters' => $step->parameters,
                'is_optional' => false,
                'variant_group' => null,
                'is_default_variant' => false,
            ])->values()->all();

            $wo = WorkOrder::create([
                'order_no' => $order['no'],
                'customer_order_no' => 'PO-'.substr($order['no'], -2),
                'customer_id' => $data['customers'][$meta['customer']]->id,
                'line_id' => $line->id,
                'product_type_id' => $product['type']->id,
                'product_revision_id' => $product['revision']->id,
                'process_snapshot' => [
                    'template_id' => $product['template']->id,
                    'template_name' => $product['template']->name,
                    'template_version' => 1,
                    'steps' => $stepSnapshot,
                    'bom' => $bomSnapshot,
                ],
                'planned_qty' => $order['qty'],
                'produced_qty' => $order['batch_status'] === Batch::STATUS_DONE ? $order['qty'] : 0,
                'status' => $order['status'],
                'priority' => 2,
                'due_date' => now()->addDays(2),
                'planned_start_at' => now()->subHours(8),
                'planned_end_at' => now()->addHours(4),
                'description' => $meta['name'].'｜新茶饮演示工单',
            ]);

            DB::table('work_order_boms')->insert([
                'work_order_id' => $wo->id,
                'process_template_id' => $product['template']->id,
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $batch = Batch::create([
                'work_order_id' => $wo->id,
                'batch_number' => $order['batch'],
                'lot_number' => $order['lot'],
                'lot_assigned_at' => now()->toDateString(),
                'workstation_id' => $line->workstations()->first()?->id,
                'target_qty' => $order['qty'],
                'produced_qty' => $order['batch_status'] === Batch::STATUS_DONE ? $order['qty'] : 0,
                'status' => $order['batch_status'],
                'started_at' => now()->subHours(8),
                'completed_at' => $order['batch_status'] === Batch::STATUS_DONE ? now()->subHours(1) : null,
                'expiry_date' => now()->addYears(1)->toDateString(),
            ]);

            foreach ($stepSnapshot as $step) {
                $done = $order['batch_status'] === Batch::STATUS_DONE || $step['step_number'] <= 2;
                BatchStep::create([
                    'batch_id' => $batch->id,
                    'step_number' => $step['step_number'],
                    'name' => $step['name'],
                    'instruction' => $step['instruction'],
                    'kind' => $step['kind'],
                    'config' => $step['config'],
                    'requires_confirmation' => true,
                    'workstation_id' => $step['workstation_id'],
                    'estimated_duration_minutes' => $step['estimated_duration_minutes'],
                    'status' => $done ? 'DONE' : 'PENDING',
                    'started_at' => $done ? now()->subHours(8) : null,
                    'completed_at' => $done ? now()->subHours(1) : null,
                    'confirmed_at' => $done ? now()->subHours(1) : null,
                    'confirmed_by' => $done ? $admin->id : null,
                    'started_by_id' => $done ? $admin->id : null,
                    'completed_by_id' => $done ? $admin->id : null,
                ]);
            }

            if ($order['batch_status'] !== Batch::STATUS_DONE) {
                continue;
            }

            $this->createQualityEvidence($batch, $product, $admin, $qa);
            $coa->release($batch->fresh(), $admin, '新茶饮演示批：质量门全部通过后放行。');

            $document = $batch->fresh()->coaDocument;
            if ($order['coa'] === 'approve') {
                $coa->inspectionSign($document, $admin);
                $coa->submit($document->fresh(), $admin);
                $coa->approve($document->fresh(), $qa);
            } elseif ($order['coa'] === 'review') {
                $coa->inspectionSign($document, $admin);
                $coa->submit($document->fresh(), $admin);
            } elseif ($order['coa'] === 'issue') {
                $coa->inspectionSign($document, $admin);
                $coa->submit($document->fresh(), $admin);
                $coa->approve($document->fresh(), $qa);
                $coa->issue($document->fresh(), $qa);
            }
        }

        // 批次完成后系统自动生成了领料/入库草稿，演示库不需要半成品单据，直接清理。
        $draftIds = StockDocument::where('status', StockDocument::STATUS_DRAFT)->pluck('id')->all();
        if ($draftIds !== []) {
            DB::table('stock_document_lines')->whereIn('stock_document_id', $draftIds)->delete();
            DB::table('stock_documents')->whereIn('id', $draftIds)->delete();
        }

        // 计划工单：不建批次，只留在排程。
        $planned = $products['jasmine'];
        WorkOrder::create([
            'order_no' => 'NT-WO-20261125-01',
            'customer_order_no' => 'PO-20261125-01',
            'customer_id' => $data['customers']['qinglan']->id,
            'line_id' => $data['lines']['brew']->id,
            'product_type_id' => $planned['type']->id,
            'product_revision_id' => $planned['revision']->id,
            'process_snapshot' => [],
            'planned_qty' => 300,
            'status' => WorkOrder::STATUS_PENDING,
            'priority' => 3,
            'due_date' => now()->addDays(3),
            'planned_start_at' => now()->addDays(1),
            'planned_end_at' => now()->addDays(1)->addHours(6),
            'description' => '次日茉莉冷萃补单，待排产。',
        ]);
    }

    private function createQualityEvidence(Batch $batch, array $product, User $admin, User $qa): void
    {
        $checkedAt = now()->subHours(2);
        $check = QualityCheck::create([
            'batch_id' => $batch->id,
            'quality_check_template_id' => $product['specs'] ? QualityCheckTemplate::where('process_template_id', $product['template']->id)->value('id') : null,
            'checked_by' => $admin->id,
            'checked_at' => $checkedAt,
            'production_quantity' => $batch->produced_qty,
            'all_passed' => true,
            'notes' => '新茶饮演示成品检验，全部项目合格。',
        ]);

        foreach ($product['specs'] as $index => $spec) {
            $value = $spec['type'] === 'measurement'
                ? (($spec['min'] + $spec['max']) / 2)
                : true;
            QualityCheckSample::create([
                'quality_check_id' => $check->id,
                'sample_number' => $index + 1,
                'parameter_name' => $spec['name'],
                'parameter_type' => $spec['type'],
                'value_numeric' => $spec['type'] === 'measurement' ? $value : null,
                'value_boolean' => $spec['type'] === 'pass_fail' ? true : null,
                'is_passed' => true,
            ]);
        }

        PackagingChecklist::create([
            'batch_id' => $batch->id,
            'checked_by' => $admin->id,
            'checked_at' => $checkedAt->copy()->addMinutes(15),
            'udi_readable' => true,
            'packaging_condition' => true,
            'labels_readable' => true,
            'label_matches_product' => true,
            'notes' => '杯盖、吸管、标签与产品一致，包装完好。',
        ]);

        foreach ([
            [BatchReleaseCheck::TYPE_RETENTION_SAMPLE, '留样 2 杯，留样柜 A-01'],
            [BatchReleaseCheck::TYPE_LINE_CLEARANCE, '灌装机、封口机、操作台已清线，无上一批残留'],
            [BatchReleaseCheck::TYPE_LABEL_BALANCE, '领用 5000 / 使用 4980 / 报废 20 / 退回 0'],
        ] as [$type, $evidence]) {
            BatchReleaseCheck::create([
                'batch_id' => $batch->id,
                'check_type' => $type,
                'status' => BatchReleaseCheck::STATUS_PASS,
                'checked_by_id' => $admin->id,
                'checked_at' => $checkedAt->copy()->addMinutes(20),
                'evidence' => $evidence,
            ]);
        }

        $coa = app(CoaService::class);
        foreach ($coa->weighingRequirements($batch->fresh()) as $requirement) {
            $target = (float) $requirement['target_qty'];
            $actual = round($target * 1.001, 4);
            $variance = round($actual - $target, 4);
            BatchWeighingRecord::create([
                'batch_id' => $batch->id,
                'material_id' => $requirement['material_id'],
                'step_number' => $requirement['step_number'],
                'material_code' => $requirement['material_code'],
                'material_name' => $requirement['material_name'],
                'unit_of_measure' => $requirement['unit_of_measure'],
                'target_qty' => $target,
                'actual_qty' => $actual,
                'tolerance_pct' => $requirement['tolerance_pct'],
                'variance_qty' => $variance,
                'variance_pct' => $target > 0 ? round($variance / $target * 100, 3) : 0,
                'status' => BatchWeighingRecord::STATUS_PASS,
                'scale_code' => 'NT-SCALE-01',
                'weighed_by_id' => $admin->id,
                'weighed_at' => $checkedAt->copy()->subMinutes(20),
                'notes' => '新茶饮演示称重记录。',
            ]);
        }

        $meta = $product['meta'];
        if ($meta['standard']['external']) {
            $labItems = $this->labItems($meta['standard']['lab']);
            BatchLabTest::create([
                'batch_id' => $batch->id,
                'report_number' => 'NT-LAB-'.$batch->batch_number,
                'lab_name' => '贵州省食品质量检验检测院（演示）',
                'sample_number' => 'NT-S-'.$batch->batch_number,
                'sampled_at' => now()->subDays(1)->toDateString(),
                'issued_at' => now()->toDateString(),
                'overall_conclusion' => BatchLabTest::CONCLUSION_PASS,
                'items' => $labItems,
                'notes' => '新茶饮演示外检报告，正式试点替换为真实报告与 PDF 原件。',
                'recorded_by_id' => $qa->id,
            ]);
        }
    }

    private function labItems(array $categories): array
    {
        $rows = [
            '农残' => [
                ['category' => '农残', 'name' => '草甘膦', 'result' => '0.05 mg/kg', 'limit' => '≤ 1.0 mg/kg', 'conclusion' => '合格'],
                ['category' => '农残', 'name' => '吡虫啉', 'result' => '0.08 mg/kg', 'limit' => '≤ 0.5 mg/kg', 'conclusion' => '合格'],
            ],
            '重金属' => [
                ['category' => '重金属', 'name' => '铅', 'result' => '0.18 mg/kg', 'limit' => '≤ 5.0 mg/kg', 'conclusion' => '合格'],
                ['category' => '重金属', 'name' => '镉', 'result' => '0.04 mg/kg', 'limit' => '≤ 1.0 mg/kg', 'conclusion' => '合格'],
            ],
            '微生物' => [
                ['category' => '微生物', 'name' => '菌落总数', 'result' => '80 CFU/g', 'limit' => '≤ 1000 CFU/g', 'conclusion' => '合格'],
                ['category' => '微生物', 'name' => '大肠菌群', 'result' => '< 10 MPN/100g', 'limit' => '≤ 10 MPN/100g', 'conclusion' => '合格'],
                ['category' => '微生物', 'name' => '霉菌', 'result' => '< 10 CFU/g', 'limit' => '≤ 100 CFU/g', 'conclusion' => '合格'],
            ],
        ];

        $out = [];
        foreach ($categories as $category) {
            foreach ($rows[$category] ?? [] as $row) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
