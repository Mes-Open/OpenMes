<?php

namespace Database\Seeders;

use App\Models\BlendFormula;
use App\Models\BlendFormulaComponent;
use App\Models\Customer;
use App\Models\ProductType;
use App\Models\TeaBase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Four jasmine cold-brew versions: approved baseline, frozen failed scale-up,
 * current large-sample stage, and a draft without a standard sample.
 */
class BlendFormulaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $qa = User::where('username', 'qa.lead')->firstOrFail();
        Auth::login($admin);

        $product = ProductType::where('code', 'NT-FG-JASMINE-COLD')->firstOrFail();
        $customer = Customer::where('code', 'NT-CUST-QINGLAN')->firstOrFail();
        $green = TeaBase::where('code', 'TB-GREEN-01')->firstOrFail();
        $jasmine = TeaBase::where('code', 'TB-JASMINE-01')->firstOrFail();

        $standardSummary = '香气清扬，汤色浅杏，滋味鲜爽，叶底嫩匀；作为茉莉冷萃的冬季标准样。';
        $sampleCode = 'STD-JASMINE-20261120';

        $versions = [
            [
                'version' => 'V01',
                'status' => BlendFormula::STATUS_APPROVED,
                'standard_sample_code' => $sampleCode,
                'standard_sample_approved_at' => now()->subDays(4),
                'standard_sample_summary' => $standardSummary,
                'standard_sample_blind_result' => 88.50,
                'small_sample_result' => BlendFormula::RESULT_PASS,
                'large_sample_result' => BlendFormula::RESULT_PASS,
                'effective_date' => now()->subDays(2)->toDateString(),
                'submitted_at' => now()->subDays(5),
                'submitted_by_id' => $admin->id,
                'reviewed_at' => now()->subDays(5)->addHours(2),
                'reviewed_by_id' => $qa->id,
                'approved_at' => now()->subDays(4)->addHours(1),
                'approved_by_id' => $qa->id,
                'notes' => '冬季茉莉冷萃基线版；小样、大样均已放行。',
                'components' => [
                    ['tea_base' => $green, 'ratio' => 65, 'tolerance' => 2, 'small' => 65.2, 'large' => 65.1],
                    ['tea_base' => $jasmine, 'ratio' => 35, 'tolerance' => 2, 'small' => 34.8, 'large' => 34.9],
                ],
            ],
            [
                'version' => 'V02',
                'status' => BlendFormula::STATUS_FROZEN,
                'standard_sample_code' => $sampleCode,
                'standard_sample_approved_at' => now()->subDays(4),
                'standard_sample_summary' => $standardSummary,
                'standard_sample_blind_result' => 88.50,
                'small_sample_result' => BlendFormula::RESULT_FAIL,
                'large_sample_result' => BlendFormula::RESULT_FAIL,
                'effective_date' => null,
                'submitted_at' => now()->subDays(3),
                'submitted_by_id' => $admin->id,
                'reviewed_at' => now()->subDays(3)->addHours(1),
                'reviewed_by_id' => $qa->id,
                'approved_at' => null,
                'approved_by_id' => null,
                'frozen_at' => now()->subDays(2)->addHours(3),
                'frozen_by_id' => $qa->id,
                'freeze_reason' => '小样绿茶底比例 63% 超出 60±2%，香气偏青；旧版冻结，改走 V03。',
                'notes' => '小样放大时绿茶底偏高，已冻结留档。',
                'components' => [
                    ['tea_base' => $green, 'ratio' => 60, 'tolerance' => 2, 'small' => 63.0, 'large' => 63.0],
                    ['tea_base' => $jasmine, 'ratio' => 40, 'tolerance' => 2, 'small' => 37.0, 'large' => 37.0],
                ],
            ],
            [
                'version' => 'V03',
                'status' => BlendFormula::STATUS_LARGE_SAMPLE,
                'standard_sample_code' => $sampleCode,
                'standard_sample_approved_at' => now()->subDays(4),
                'standard_sample_summary' => $standardSummary,
                'standard_sample_blind_result' => 88.50,
                'small_sample_result' => BlendFormula::RESULT_PASS,
                'large_sample_result' => BlendFormula::RESULT_PENDING,
                'effective_date' => null,
                'submitted_at' => now()->subDays(1),
                'submitted_by_id' => $admin->id,
                'reviewed_at' => now()->subDays(1)->addHours(2),
                'reviewed_by_id' => $qa->id,
                'approved_at' => null,
                'approved_by_id' => null,
                'notes' => '当前试拼版；小样已通过，等待大样对照。',
                'components' => [
                    ['tea_base' => $green, 'ratio' => 65, 'tolerance' => 2, 'small' => 65.4, 'large' => null],
                    ['tea_base' => $jasmine, 'ratio' => 35, 'tolerance' => 2, 'small' => 34.6, 'large' => null],
                ],
            ],
            [
                'version' => 'V04',
                'status' => BlendFormula::STATUS_DRAFT,
                'standard_sample_code' => null,
                'standard_sample_approved_at' => null,
                'standard_sample_summary' => null,
                'standard_sample_blind_result' => null,
                'small_sample_result' => BlendFormula::RESULT_PENDING,
                'large_sample_result' => BlendFormula::RESULT_PENDING,
                'effective_date' => null,
                'submitted_at' => null,
                'submitted_by_id' => null,
                'reviewed_at' => null,
                'reviewed_by_id' => null,
                'approved_at' => null,
                'approved_by_id' => null,
                'notes' => '新一版试拼草稿；标准样待指定，比例先按 V03 复制。',
                'components' => [
                    ['tea_base' => $green, 'ratio' => 65, 'tolerance' => 2, 'small' => null, 'large' => null],
                    ['tea_base' => $jasmine, 'ratio' => 35, 'tolerance' => 2, 'small' => null, 'large' => null],
                ],
            ],
        ];

        foreach ($versions as $index => $version) {
            $formula = BlendFormula::updateOrCreate(
                ['code' => 'BF-JASMINE-'.$version['version']],
                [
                    'product_type_id' => $product->id,
                    'customer_id' => $customer->id,
                    'version' => $version['version'],
                    'status' => $version['status'],
                    'standard_sample_code' => $version['standard_sample_code'],
                    'standard_sample_approved_at' => $version['standard_sample_approved_at'],
                    'standard_sample_summary' => $version['standard_sample_summary'],
                    'standard_sample_blind_result' => $version['standard_sample_blind_result'],
                    'small_sample_result' => $version['small_sample_result'],
                    'large_sample_result' => $version['large_sample_result'],
                    'effective_date' => $version['effective_date'],
                    'submitted_at' => $version['submitted_at'],
                    'submitted_by_id' => $version['submitted_by_id'],
                    'reviewed_at' => $version['reviewed_at'],
                    'reviewed_by_id' => $version['reviewed_by_id'],
                    'approved_at' => $version['approved_at'],
                    'approved_by_id' => $version['approved_by_id'],
                    'frozen_at' => $version['frozen_at'] ?? null,
                    'frozen_by_id' => $version['frozen_by_id'] ?? null,
                    'freeze_reason' => $version['freeze_reason'] ?? null,
                    'notes' => $version['notes'],
                ]
            );

            $formula->components()->delete();
            foreach ($version['components'] as $sort => $component) {
                BlendFormulaComponent::create([
                    'blend_formula_id' => $formula->id,
                    'tea_base_id' => $component['tea_base']->id,
                    'component_name' => $component['tea_base']->name,
                    'standard_ratio' => $component['ratio'],
                    'tolerance' => $component['tolerance'],
                    'small_actual' => $component['small'],
                    'large_actual' => $component['large'],
                    'sort_order' => $sort,
                ]);
            }
        }

        $this->command?->info('Blend formula demo seeded: '.BlendFormula::count().' versions');
    }
}
