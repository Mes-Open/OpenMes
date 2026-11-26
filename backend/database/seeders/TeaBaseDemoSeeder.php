<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\TeaBase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Four tea bases that map to the demo raw teas. Green and jasmine are usable;
 * Dongfang is waiting on sensory confirmation; Oolong is frozen because its
 * moisture and sensory scores fall outside the formula window.
 */
class TeaBaseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $qa = User::where('username', 'qa.lead')->firstOrFail();
        Auth::login($admin);

        $materials = Material::query()
            ->whereIn('code', ['NT-RM-TEA-GREEN', 'NT-RM-TEA-JASMINE', 'NT-RM-TEA-DONGFANG', 'NT-RM-TEA-OOLONG'])
            ->get()
            ->keyBy('code');

        $rows = [
            [
                'code' => 'TB-GREEN-01',
                'name' => '贵州绿茶毛茶 · 一级',
                'material_code' => 'NT-RM-TEA-GREEN',
                'origin' => '贵州 · 遵义湄潭',
                'variety' => '福鼎大白茶',
                'grade' => '一级',
                'season' => '2026 春',
                'screen_size' => '16 目',
                'supplier_name' => '黔南绿源茶业',
                'moisture_percent' => 5.80,
                'pesticide_value' => 0.050,
                'pesticide_limit' => 0.100,
                'pesticide_status' => TeaBase::QUALITY_PASS,
                'heavy_metal_value' => 0.180,
                'heavy_metal_limit' => 0.500,
                'heavy_metal_status' => TeaBase::QUALITY_PASS,
                'sensory_score' => 88.00,
                'sensory_status' => TeaBase::QUALITY_PASS,
                'status' => TeaBase::STATUS_AVAILABLE,
                'last_inspected_at' => now()->subHour(),
                'notes' => '冷萃茶底主料；本批含水率与农残都在拼配窗口内。',
            ],
            [
                'code' => 'TB-JASMINE-01',
                'name' => '广西茉莉花茶胚 · 特级',
                'material_code' => 'NT-RM-TEA-JASMINE',
                'origin' => '广西 · 横州',
                'variety' => '双瓣茉莉花茶胚',
                'grade' => '特级',
                'season' => '2026 夏',
                'screen_size' => '12 目',
                'supplier_name' => '横州茉莉花合作社',
                'moisture_percent' => 6.20,
                'pesticide_value' => 0.080,
                'pesticide_limit' => 0.100,
                'pesticide_status' => TeaBase::QUALITY_PASS,
                'heavy_metal_value' => 0.120,
                'heavy_metal_limit' => 0.500,
                'heavy_metal_status' => TeaBase::QUALITY_PASS,
                'sensory_score' => 91.00,
                'sensory_status' => TeaBase::QUALITY_PASS,
                'status' => TeaBase::STATUS_AVAILABLE,
                'last_inspected_at' => now()->subHours(2),
                'notes' => '窨制茶胚，香气评分 91；进入拼配前核对窨次。',
            ],
            [
                'code' => 'TB-DONGFANG-01',
                'name' => '凤凰单丛鸭屎香 · 一级',
                'material_code' => 'NT-RM-TEA-DONGFANG',
                'origin' => '广东 · 潮州凤凰山',
                'variety' => '鸭屎香单丛',
                'grade' => '一级',
                'season' => '2026 春',
                'screen_size' => '10 目',
                'supplier_name' => '闽南单丛茶业',
                'moisture_percent' => 5.40,
                'pesticide_value' => 0.060,
                'pesticide_limit' => 0.100,
                'pesticide_status' => TeaBase::QUALITY_PASS,
                'heavy_metal_value' => 0.160,
                'heavy_metal_limit' => 0.500,
                'heavy_metal_status' => TeaBase::QUALITY_PASS,
                'sensory_score' => null,
                'sensory_status' => TeaBase::QUALITY_PENDING,
                'status' => TeaBase::STATUS_PENDING,
                'last_inspected_at' => now()->subHours(3),
                'notes' => '农残与重金属已过，感官复评待 QA 确认；确认前不进入拼配。',
            ],
            [
                'code' => 'TB-OOLONG-01',
                'name' => '武夷焙火乌龙 · 二级',
                'material_code' => 'NT-RM-TEA-OOLONG',
                'origin' => '福建 · 武夷山',
                'variety' => '水仙',
                'grade' => '二级',
                'season' => '2025 秋',
                'screen_size' => '14 目',
                'supplier_name' => '闽南单丛茶业',
                'moisture_percent' => 6.80,
                'pesticide_value' => 0.090,
                'pesticide_limit' => 0.100,
                'pesticide_status' => TeaBase::QUALITY_PASS,
                'heavy_metal_value' => 0.220,
                'heavy_metal_limit' => 0.500,
                'heavy_metal_status' => TeaBase::QUALITY_PASS,
                'sensory_score' => 76.00,
                'sensory_status' => TeaBase::QUALITY_FAIL,
                'status' => TeaBase::STATUS_FROZEN,
                'last_inspected_at' => now()->subHours(4),
                'freeze_reason' => '含水率 6.8% 超出 5.0–6.5% 拼配窗口，感官评分 76 低于标准 80。',
                'frozen_at' => now()->subHours(2),
                'frozen_by_id' => $qa->id,
                'notes' => '焙火乌龙备选茶底；待复检或降级处置后再评估。',
            ],
        ];

        foreach ($rows as $row) {
            $material = $materials->get($row['material_code']);
            if (! $material) {
                continue;
            }

            $materialCode = $row['material_code'];
            unset($row['material_code']);

            TeaBase::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['material_id' => $material->id])
            );
        }

        $this->command?->info('Tea base demo seeded: '.TeaBase::count().' dossiers');
    }
}
