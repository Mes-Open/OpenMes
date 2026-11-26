<?php

namespace Database\Seeders;

use App\Enums\CustomFieldType;
use App\Enums\RevisionLifecycle;
use App\Models\Batch;
use App\Models\BatchLabTest;
use App\Models\BatchWeighingRecord;
use App\Models\BatchReleaseCheck;
use App\Models\BomItem;
use App\Models\Customer;
use App\Models\CustomerFlavorStandard;
use App\Models\CustomFieldDefinition;
use App\Models\DowntimeReason;
use App\Models\InspectionPlan;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Models\QualityCheckTemplate;
use App\Models\ScrapReason;
use App\Models\Shift;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 逅唐茶业拼配茶样板线最小可用主数据。
 *
 * 目标：在不破坏原有演示数据的前提下，建立一套可运行的“客户—产品—配方—工艺—批次—质量”
 * 样板数据，供 90 天试点使用。所有编码均以 HT- 开头，便于识别和后续清理。
 */
class HoutangPilotSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'admin')->first();

        // 茶行业试点必须按物料批追溯：到货检验通过后生成可被 FEFO/FIFO 领用的物料批。
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true), 'updated_at' => now()],
        );

        // QA 复核人：与放行人分离，专门用于 COA 批准/签发（双人复核）。
        $qa = User::firstOrCreate(
            ['username' => 'qa.lead'],
            [
                'name' => 'QA 负责人',
                'email' => 'qa.lead@houtang.local',
                'password' => Hash::make('QA123456!'),
                'account_type' => 'user',
            ]
        );
        $qa->assignRole('QA');

        // ── 1. 客户/品牌（先用一个品牌级客户占位，后续按真实客户替换） ─────────
        $customer = Customer::updateOrCreate(
            ['code' => 'HT-CUST-DEMO-A'],
            [
                'name' => '示例茶饮品牌 A（待替换）',
                'tier' => 'gold',
                'payment_score' => 80,
                'notes' => '样板线试点客户。正式上线时替换为真实客户/品牌，并按“集团—品牌”层级拆分。',
                'is_active' => true,
            ]
        );

        // ── 2. 原料主数据 ────────────────────────────────────────────────────
        $materials = [
            'green' => ['code' => 'HT-RM-TEA-GREEN', 'name' => '贵州绿茶毛茶（一级）', 'type' => 1, 'uom' => 'kg'],
            'jasmine' => ['code' => 'HT-RM-TEA-JASMINE', 'name' => '茉莉花茶胚（一级）', 'type' => 1, 'uom' => 'kg'],
            'bag' => ['code' => 'HT-RM-PKG-BAG', 'name' => '铝箔内袋 5kg', 'type' => 3, 'uom' => 'pcs'],
            'carton' => ['code' => 'HT-RM-PKG-CARTON', 'name' => '茶底纸箱', 'type' => 3, 'uom' => 'pcs'],
            'label' => ['code' => 'HT-RM-PKG-LABEL', 'name' => '成品标签', 'type' => 3, 'uom' => 'pcs'],
        ];
        $materialModels = [];
        foreach ($materials as $key => $row) {
            $materialModels[$key] = Material::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'material_type_id' => $row['type'],
                    'unit_of_measure' => $row['uom'],
                    'tracking_type' => 'batch',
                    'is_active' => true,
                ]
            );
        }

        // ── 3. 产品类型（按产品族建，不按客户建） ─────────────────────────────
        $productType = ProductType::updateOrCreate(
            ['code' => 'HT-FG-JASMINE-GREEN'],
            [
                'name' => '茉莉绿茶茶底',
                'description' => '以贵州绿茶毛茶与茉莉花茶胚拼配的茶底，适用于新茶饮门店。',
                'category' => '茶底-茉莉绿茶',
                'unit_of_measure' => 'kg',
                'is_active' => true,
            ]
        );

        // ── 4. 样板线 + 工位 + 班次 ───────────────────────────────────────────
        $line = Line::updateOrCreate(
            ['code' => 'HT-BLEND-01'],
            [
                'name' => '拼配茶样板线',
                'description' => '90 天试点线：原料接收—拼配称重—混合—筛分/金检—包装—成品放行。',
                'is_active' => true,
                'planning_efficiency_pct' => 85,
            ]
        );

        $workstations = [
            'receive' => ['code' => 'HT-WS-RECEIVE', 'name' => '原料接收与称重', 'type' => 'weighing', 'rate' => 600],
            'blend' => ['code' => 'HT-WS-BLEND', 'name' => '拼配混合', 'type' => 'blending', 'rate' => 300],
            'qc' => ['code' => 'HT-WS-QC', 'name' => '过程与成品检验', 'type' => 'quality', 'rate' => 20],
            'pack' => ['code' => 'HT-WS-PACK', 'name' => '包装与托盘', 'type' => 'packing', 'rate' => 120],
        ];
        $workstationModels = [];
        foreach ($workstations as $key => $row) {
            $workstationModels[$key] = Workstation::updateOrCreate(
                ['code' => $row['code']],
                [
                    'line_id' => $line->id,
                    'name' => $row['name'],
                    'workstation_type' => $row['type'],
                    'ideal_rate_per_hour' => $row['rate'],
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['HT-SE', '早班', '08:00:00', '16:00:00', 1],
            ['HT-SL', '中班', '16:00:00', '00:00:00', 2],
        ] as [$code, $name, $start, $end, $sort]) {
            Shift::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'days_of_week' => [1, 2, 3, 4, 5, 6],
                    'line_id' => $line->id,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }

        // 产品类型分配到样板线
        DB::table('line_product_type')->updateOrInsert(
            ['line_id' => $line->id, 'product_type_id' => $productType->id],
            ['line_id' => $line->id, 'product_type_id' => $productType->id]
        );

        // ── 5. 工艺模板 + 步骤 ────────────────────────────────────────────────
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'name' => '茉莉绿茶茶底标准工艺 v1'],
            [
                'version' => 1,
                'ideal_cycle_minutes' => 240,
                'is_active' => true,
            ]
        );

        $steps = [
            [
                'step_number' => 1,
                'name' => '原料核对与领料',
                'workstation' => 'receive',
                'duration' => 20,
                'requires_confirmation' => true,
                'instruction' => '核对品名、批号、数量、合格状态与保质期；异常原料不得上线。',
                'parameters' => ['核对项' => '品名/批号/数量/合格状态/保质期'],
            ],
            [
                'step_number' => 2,
                'name' => '拼配称重',
                'workstation' => 'blend',
                'duration' => 60,
                'requires_confirmation' => true,
                'instruction' => '按配方比例逐项称重；关键原料双人复核，超差锁批。',
                'parameters' => ['称重精度' => '0.01 g', '单批公差' => '±0.5%', '关键原料' => '茉莉花茶胚'],
            ],
            [
                'step_number' => 3,
                'name' => '拼配混合',
                'workstation' => 'blend',
                'duration' => 45,
                'requires_confirmation' => true,
                'instruction' => '按工艺参数混合，记录实际时间与转速。',
                'parameters' => ['混合时间' => '15 min', '转速' => '按设备SOP', '环境湿度' => '≤65%RH'],
            ],
            [
                'step_number' => 4,
                'name' => '过筛与金属检测',
                'workstation' => 'qc',
                'duration' => 20,
                'requires_confirmation' => true,
                'instruction' => '过筛后全部物料通过金属检测机；剔除物单独记录。',
                'parameters' => ['筛网' => '40目', '金属检测' => 'Fe 1.5mm / Non-Fe 2.0mm'],
            ],
            [
                'step_number' => 5,
                'name' => '过程取样检验',
                'workstation' => 'qc',
                'duration' => 20,
                'requires_confirmation' => true,
                'instruction' => '按批次抽取样品，做水分、感官、拼配比例与异物检查。',
                'parameters' => ['取样量' => '200 g', '检验项目' => '水分/感官/比例/异物'],
            ],
            [
                'step_number' => 6,
                'name' => '包装',
                'workstation' => 'pack',
                'duration' => 40,
                'requires_confirmation' => true,
                'kind' => TemplateStep::KIND_PACKING,
                'instruction' => '按客户包装规格装袋、装箱、贴标；称重并记录箱号/托号。',
                'config' => [
                    'unit' => 'carton',
                    'carton_capacity' => 20,
                    'pallet_capacity' => 40,
                    'weight_expected_g' => 5000,
                    'weight_tolerance_g' => 50,
                ],
                'parameters' => ['单袋净含量' => '5 kg', '装箱数量' => '20 袋/箱', '托盘数量' => '40 箱/托'],
            ],
            [
                'step_number' => 7,
                'name' => '成品检验与放行',
                'workstation' => 'qc',
                'duration' => 30,
                'requires_confirmation' => true,
                'instruction' => 'QA 按客户标准做成品检验；合格后放行并生成 COA。',
                'parameters' => ['检验项目' => '感官/水分/微生物/净含量/标签', '放行角色' => 'QA'],
            ],
            [
                'step_number' => 8,
                'name' => '贴标与托盘入库',
                'workstation' => 'pack',
                'duration' => 20,
                'requires_confirmation' => true,
                'instruction' => '打印批次/托盘标签，扫码入库；待检品不得发运。',
                'parameters' => ['标签' => '批次标签+托盘标签', '库位' => '成品待检区/合格品区'],
            ],
        ];

        foreach ($steps as $step) {
            TemplateStep::updateOrCreate(
                ['process_template_id' => $template->id, 'step_number' => $step['step_number']],
                [
                    'name' => $step['name'],
                    'kind' => $step['kind'] ?? TemplateStep::KIND_PRODUCTION,
                    'instruction' => $step['instruction'],
                    'estimated_duration_minutes' => $step['duration'],
                    'requires_confirmation' => $step['requires_confirmation'],
                    'workstation_id' => $workstationModels[$step['workstation']]->id,
                    'parameters' => $step['parameters'],
                    'config' => $step['config'] ?? null,
                ]
            );
        }

        // ── 6. BOM（每 1 kg 成品） ────────────────────────────────────────────
        foreach ([
            ['material' => 'green', 'qty' => 0.30, 'step' => 2],
            ['material' => 'jasmine', 'qty' => 0.70, 'step' => 2],
            ['material' => 'bag', 'qty' => 0.20, 'step' => 6],
            ['material' => 'carton', 'qty' => 0.01, 'step' => 6],
            ['material' => 'label', 'qty' => 1.00, 'step' => 8],
        ] as $bom) {
            $stepModel = TemplateStep::where('process_template_id', $template->id)
                ->where('step_number', $bom['step'])->first();

            BomItem::updateOrCreate(
                ['process_template_id' => $template->id, 'material_id' => $materialModels[$bom['material']]->id],
                [
                    'template_step_id' => $stepModel?->id,
                    'quantity_per_unit' => $bom['qty'],
                    'per' => BomItem::PER_UNIT,
                    'scrap_percentage' => 0.5,
                    'consumed_at' => 'start',
                    'sort_order' => $bom['step'],
                ]
            );
        }

        // ── 7. 配方版本（客户 SKU/配方） ──────────────────────────────────────
        ProductRevision::updateOrCreate(
            ['product_type_id' => $productType->id, 'revision_code' => 'HT-FRM-DEMO-A-V01'],
            [
                'description' => '示例客户 A / 茉莉绿茶茶底 / 标准糖香型 / 5kg 袋装',
                'lifecycle_status' => RevisionLifecycle::Released,
                'process_template_id' => $template->id,
                'external_ref' => 'DEMO-A-SKU-JASMINE-5KG',
                'effective_from' => now(),
                'released_at' => now(),
                'released_by_id' => $admin?->id,
            ]
        );

        // ── 8. 过程/成品质量检查模板 ──────────────────────────────────────────
        QualityCheckTemplate::updateOrCreate(
            ['process_template_id' => $template->id, 'name' => '茉莉绿茶茶底过程与成品检验'],
            [
                'min_checks_per_batch' => 2,
                'min_checks_per_day' => 2,
                'samples_per_check' => 2,
                'parameters' => [
                    ['name' => '水分', 'type' => 'measurement', 'unit' => '%', 'min' => 5.0, 'max' => 8.0],
                    ['name' => '感官-香气', 'type' => 'pass_fail'],
                    ['name' => '感官-滋味', 'type' => 'pass_fail'],
                    ['name' => '感官-汤色', 'type' => 'pass_fail'],
                    ['name' => '拼配比例偏差', 'type' => 'measurement', 'unit' => '%', 'min' => -0.5, 'max' => 0.5],
                    ['name' => '异物', 'type' => 'pass_fail'],
                    ['name' => '净含量', 'type' => 'measurement', 'unit' => 'g', 'min' => 4950, 'max' => 5050],
                ],
            ]
        );

        // ── 9. 来料检验计划（茶叶原料） ───────────────────────────────────────
        InspectionPlan::updateOrCreate(
            ['name' => 'HT-茶叶来料验收标准 v1'],
            [
                'description' => '适用于绿茶毛茶、茉莉花茶胚等茶叶原料的到货验收。',
                'material_type_id' => 1,
                'criteria' => [
                    ['name' => '水分', 'type' => 'measurement', 'required' => true, 'unit' => '%', 'spec_min' => 5.0, 'spec_max' => 8.0],
                    ['name' => '感官-香气', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '感官-滋味', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '感官-汤色', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '农残报告', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '重金属报告', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '异物', 'type' => 'pass_fail', 'required' => true],
                    ['name' => '异味', 'type' => 'pass_fail', 'required' => true],
                ],
                'is_active' => true,
                'version' => 1,
                'published_at' => now(),
            ]
        );

        // ── 10. 质量问题类型 ──────────────────────────────────────────────────
        foreach ([
            ['HT-Q-SENSORY', '感官异常（香气/滋味/汤色）', 'HIGH'],
            ['HT-Q-MOISTURE', '水分超标', 'HIGH'],
            ['HT-Q-FOREIGN', '异物混入', 'CRITICAL'],
            ['HT-Q-WEIGHT', '称重超差', 'HIGH'],
            ['HT-Q-LABEL', '标签/包装错误', 'MEDIUM'],
            ['HT-Q-COMPLAINT', '客户投诉/退货', 'HIGH'],
            ['HT-Q-SUPPLIER', '供应商来料不合格', 'HIGH'],
        ] as [$code, $name, $severity]) {
            IssueType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'severity' => $severity, 'is_blocking' => true, 'is_active' => true]
            );
        }

        // ── 11. 停机原因（茶行业语言） ────────────────────────────────────────
        foreach ([
            ['HT-DT-CLEAN', '清洗/换型', 'changeover'],
            ['HT-DT-WAIT-QC', '待检/待放行', 'unplanned'],
            ['HT-DT-WAIT-MAT', '待料', 'unplanned'],
            ['HT-DT-SIEVE', '筛网堵塞', 'unplanned'],
            ['HT-DT-SCALE', '电子秤故障', 'unplanned'],
            ['HT-DT-BLEND', '拼配机故障', 'unplanned'],
            ['HT-DT-PACK', '包装机故障', 'unplanned'],
            ['HT-DT-METAL', '金属检测机故障', 'unplanned'],
        ] as [$code, $name, $kind]) {
            DowntimeReason::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'kind' => $kind, 'is_active' => true]
            );
        }

        // ── 12. 报废原因 ─────────────────────────────────────────────────────
        foreach ([
            ['HT-SCR-SENSORY', '感官不合格', ScrapReason::CATEGORY_MATERIAL],
            ['HT-SCR-MOISTURE', '水分不合格', ScrapReason::CATEGORY_MATERIAL],
            ['HT-SCR-FOREIGN', '异物', ScrapReason::CATEGORY_MATERIAL],
            ['HT-SCR-WEIGHT', '重量偏差', ScrapReason::CATEGORY_METHOD],
            ['HT-SCR-PACK', '包装破损', ScrapReason::CATEGORY_MACHINE],
            ['HT-SCR-LABEL', '标签错误', ScrapReason::CATEGORY_MAN],
        ] as [$code, $name, $category]) {
            ScrapReason::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'is_active' => true, 'sort_order' => 10]
            );
        }

        // ── 13. 自定义字段（茶行业属性） ──────────────────────────────────────
        $select = fn (array $options) => ['options' => array_map(fn ($v) => ['value' => $v, 'label' => $v], $options)];

        $fields = [
            ['product_type', 'product_form', '产品形态', CustomFieldType::Select, $select(['原叶茶', '抹茶粉', '冷萃液', '茶包', '浓缩液'])],
            ['product_type', 'tea_category', '茶类', CustomFieldType::Select, $select(['绿茶', '红茶', '乌龙', '茉莉', '抹茶', '拼配'])],
            ['product_type', 'customer_scope', '适用客户/品牌', CustomFieldType::Text, []],
            ['product_type', 'shelf_life_months', '保质期（月）', CustomFieldType::Integer, []],

            ['material', 'tea_garden_code', '茶园/地块代码', CustomFieldType::Text, []],
            ['material', 'tea_season', '茶季', CustomFieldType::Select, $select(['春茶', '夏茶', '秋茶', '冬茶'])],
            ['material', 'tea_grade', '茶叶等级', CustomFieldType::Select, $select(['特级', '一级', '二级', '三级'])],
            ['material', 'origin_region', '产地', CustomFieldType::Text, []],
            ['material', 'certification', '认证', CustomFieldType::Multiselect, $select(['有机', '绿色食品', '雨林联盟', '公平贸易', '无'])],
            ['material', 'moisture_standard_pct', '水分标准（%）', CustomFieldType::Number, []],

            ['work_order', 'customer_sku', '客户 SKU', CustomFieldType::Text, []],
            ['work_order', 'formula_version', '配方版本', CustomFieldType::Text, []],
            ['work_order', 'coa_required', '需要 COA', CustomFieldType::Boolean, []],

            ['batch', 'tea_season', '生产茶季', CustomFieldType::Text, []],
            ['batch', 'customer_lot_ref', '客户批号引用', CustomFieldType::Text, []],

            ['issue', 'customer_complaint', '客户投诉', CustomFieldType::Boolean, []],
            ['issue', 'involved_lots', '涉及批次/原料批', CustomFieldType::Text, []],

            ['line', 'flavor_family', '风味家族', CustomFieldType::Text, []],
            ['line', 'changeover_group', '换型分组', CustomFieldType::Text, []],
        ];

        foreach ($fields as $index => [$entity, $key, $label, $type, $config]) {
            CustomFieldDefinition::updateOrCreate(
                ['entity_type' => $entity, 'key' => $key],
                [
                    'label' => $label,
                    'type' => $type,
                    'config' => $config,
                    'required' => false,
                    'position' => $index + 1,
                    'is_active' => true,
                ]
            );
        }


        // ── 14. 客户风味标准（COA 规格的真实来源） ───────────────────────────
        CustomerFlavorStandard::updateOrCreate(
            ['code' => 'HT-CFS-DEMO-A-JASMINE-V1', 'version' => 'V1'],
            [
                'customer_id' => $customer->id,
                'product_type_id' => $productType->id,
                'name' => '茉莉绿茶客户风味标准',
                'status' => CustomerFlavorStandard::STATUS_ACTIVE,
                'effective_from' => '2026-11-01',
                'moisture_min' => 5.0,
                'moisture_max' => 8.0,
                'sensory_aroma' => '茉莉花香鲜灵、持久，无闷味',
                'sensory_taste' => '鲜爽、回甘，无苦涩',
                'sensory_liquor' => '黄绿明亮',
                'blend_tolerance' => 0.5,
                'foreign_matter_required' => true,
                'requires_external_lab' => true,
                'net_weight_min' => 4950,
                'net_weight_max' => 5050,
                'packaging_requirement' => '5kg 铝箔内袋，热封平整；标签产品名、批次、净含量与实物一致。',
                'notes' => '样板客户标准，正式试点前替换为客户签字确认版。',
                'created_by_id' => $admin?->id,
                'approved_by_id' => $admin?->id,
                'approved_at' => now(),
            ]
        );


        // ── 15. 验收样批的留样/清线/标签平衡记录 ─────────────────────────────
        $acceptance = Batch::query()->where('lot_number', 'HT-COA-ACCEPT-20261123221254')->first();
        if ($acceptance) {
            BatchLabTest::updateOrCreate(
                ['batch_id' => $acceptance->id, 'report_number' => 'LAB-DEMO-20261123-001'],
                [
                    'lab_name' => '贵州省农产品质量安全检测中心（示例）',
                    'sample_number' => 'S-2643-01',
                    'sampled_at' => '2026-11-23',
                    'issued_at' => '2026-11-23',
                    'overall_conclusion' => BatchLabTest::CONCLUSION_PASS,
                    'items' => [
                        ['category' => '农残', 'name' => '草甘膦', 'result' => '0.08 mg/kg', 'limit' => '≤ 1.0 mg/kg', 'conclusion' => '合格'],
                        ['category' => '农残', 'name' => '吡虫啉', 'result' => '0.12 mg/kg', 'limit' => '≤ 0.5 mg/kg', 'conclusion' => '合格'],
                        ['category' => '农残', 'name' => '联苯菊酯', 'result' => '0.05 mg/kg', 'limit' => '≤ 0.5 mg/kg', 'conclusion' => '合格'],
                        ['category' => '重金属', 'name' => '铅', 'result' => '0.32 mg/kg', 'limit' => '≤ 5.0 mg/kg', 'conclusion' => '合格'],
                        ['category' => '重金属', 'name' => '镉', 'result' => '0.08 mg/kg', 'limit' => '≤ 1.0 mg/kg', 'conclusion' => '合格'],
                        ['category' => '重金属', 'name' => '砷', 'result' => '0.15 mg/kg', 'limit' => '≤ 2.0 mg/kg', 'conclusion' => '合格'],
                        ['category' => '微生物', 'name' => '菌落总数', 'result' => '120 CFU/g', 'limit' => '≤ 1000 CFU/g', 'conclusion' => '合格'],
                        ['category' => '微生物', 'name' => '大肠菌群', 'result' => '< 10 MPN/100g', 'limit' => '≤ 10 MPN/100g', 'conclusion' => '合格'],
                        ['category' => '微生物', 'name' => '霉菌', 'result' => '< 10 CFU/g', 'limit' => '≤ 100 CFU/g', 'conclusion' => '合格'],
                    ],
                    'notes' => '示例外检数据，正式上线替换为真实报告号和 PDF 附件。',
                    'recorded_by_id' => $qa?->id,
                ]
            );

            foreach (app(\App\Services\Quality\CoaService::class)->weighingRequirements($acceptance) as $requirement) {
                $target = (float) $requirement['target_qty'];
                $actual = round($target + ($requirement['material_name'] === '贵州绿茶毛茶（一级）' ? 0.02 : -0.03), 4);
                $variance = round($actual - $target, 4);
                $variancePct = $target > 0 ? round($variance / $target * 100, 3) : 0;

                BatchWeighingRecord::updateOrCreate(
                    [
                        'batch_id' => $acceptance->id,
                        'material_id' => $requirement['material_id'],
                        'step_number' => $requirement['step_number'],
                    ],
                    [
                        'material_code' => $requirement['material_code'],
                        'material_name' => $requirement['material_name'],
                        'unit_of_measure' => $requirement['unit_of_measure'],
                        'target_qty' => $target,
                        'actual_qty' => $actual,
                        'tolerance_pct' => $requirement['tolerance_pct'],
                        'variance_qty' => $variance,
                        'variance_pct' => $variancePct,
                        'status' => BatchWeighingRecord::STATUS_PASS,
                        'scale_code' => 'SCALE-BLEND-01',
                        'weighed_by_id' => $admin?->id,
                        'weighed_at' => now(),
                        'notes' => '验收称重示例，实际投产以电子秤读数为准。',
                    ]
                );
            }

            foreach ([
                [BatchReleaseCheck::TYPE_RETENTION_SAMPLE, '验收：留样 2 袋，LY-2643-01，留样柜 A'],
                [BatchReleaseCheck::TYPE_LINE_CLEARANCE, '验收：拼配机、筛分机、包装台已清空，无上一批残留'],
                [BatchReleaseCheck::TYPE_LABEL_BALANCE, '验收：领用 500 / 使用 498 / 报废 2 / 退回 0'],
            ] as [$type, $evidence]) {
                BatchReleaseCheck::firstOrCreate(
                    [
                        'batch_id' => $acceptance->id,
                        'check_type' => $type,
                        'status' => BatchReleaseCheck::STATUS_PASS,
                    ],
                    [
                        'checked_by_id' => $admin?->id,
                        'checked_at' => now(),
                        'evidence' => $evidence,
                    ]
                );
            }
        }

        $this->command?->info('Houtang pilot master data seeded: '.$productType->code.' / '.$line->code);
    }
}
