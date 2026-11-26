<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\FlowerLot;
use App\Models\FlowerLotAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Today's jasmine arrivals: one safe, one in curing, one critical, one already
 * past the cutoff. Times are relative to seed execution so the countdown is
 * meaningful whenever the demo is reset.
 */
class FlowerLotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $qa = User::where('username', 'qa.lead')->firstOrFail();
        Auth::login($admin);

        $rows = [
            [
                'code' => 'FL-JASMINE-20261124-A',
                'variety' => '双瓣茉莉 · 头花',
                'origin' => '广西 · 横州',
                'supplier_name' => '横州茉莉花合作社',
                'picked_at' => now()->subHours(9),
                'arrived_at' => now()->subHours(6),
                'usable_from' => now()->subHours(3),
                'usable_until' => now()->addHours(5),
                'weight_kg' => 320.000,
                'opening_rate' => 82.00,
                'curing_temperature' => 24.50,
                'storage_location' => '养护间 A · 冷链架 2',
                'status' => FlowerLot::STATUS_AVAILABLE,
                'notes' => '头花开放率 82%，已付窨 120 kg 给 3001，剩余 200 kg。',
            ],
            [
                'code' => 'FL-JASMINE-20261124-B',
                'variety' => '双瓣茉莉 · 二花',
                'origin' => '广西 · 横州',
                'supplier_name' => '横州茉莉花合作社',
                'picked_at' => now()->subHours(5),
                'arrived_at' => now()->subHours(2),
                'usable_from' => now()->addHour(),
                'usable_until' => now()->addHours(9),
                'weight_kg' => 260.000,
                'opening_rate' => 65.00,
                'curing_temperature' => 26.00,
                'storage_location' => '养护间 B · 风口位',
                'status' => FlowerLot::STATUS_CURING,
                'notes' => '二花还在养护，开放率 65%；达到 75% 后再标记可用。',
            ],
            [
                'code' => 'FL-JASMINE-20261124-C',
                'variety' => '双瓣茉莉 · 头花',
                'origin' => '广西 · 横州',
                'supplier_name' => '横州茉莉花合作社',
                'picked_at' => now()->subHours(11),
                'arrived_at' => now()->subHours(8),
                'usable_from' => now()->subHours(5),
                'usable_until' => now()->addHours(2),
                'weight_kg' => 180.000,
                'opening_rate' => 91.00,
                'curing_temperature' => 23.00,
                'storage_location' => '养护间 A · 近门位',
                'status' => FlowerLot::STATUS_AVAILABLE,
                'notes' => '开放率 91%，窗口只剩约 2 小时，优先付窨。',
            ],
            [
                'code' => 'FL-JASMINE-20261124-D',
                'variety' => '双瓣茉莉 · 尾花',
                'origin' => '广西 · 横州',
                'supplier_name' => '横州茉莉花合作社',
                'picked_at' => now()->subHours(15),
                'arrived_at' => now()->subHours(12),
                'usable_from' => now()->subHours(9),
                'usable_until' => now()->subHours(2),
                'weight_kg' => 140.000,
                'opening_rate' => 78.00,
                'curing_temperature' => 22.00,
                'storage_location' => '养护间 B · 尾架',
                'status' => FlowerLot::STATUS_AVAILABLE,
                'notes' => '已超过可用截止 2 小时；不能付窨，只能报废或降级。',
            ],
        ];

        foreach ($rows as $row) {
            FlowerLot::updateOrCreate(['code' => $row['code']], $row);
        }

        $lot = FlowerLot::where('code', 'FL-JASMINE-20261124-A')->first();
        $batch = Batch::where('batch_number', 3001)->first();

        if ($lot && $batch) {
            FlowerLotAllocation::updateOrCreate(
                ['flower_lot_id' => $lot->id, 'batch_id' => $batch->id],
                [
                    'weight_kg' => 120.000,
                    'seep_count' => 1,
                    'allocated_by_id' => $qa->id,
                    'allocated_at' => now()->subMinutes(35),
                    'notes' => '第一窨，供 3001 茉莉冷萃茶底。',
                ]
            );
        }

        $this->command?->info('Flower lot demo seeded: '.FlowerLot::count().' lots / '
            .FlowerLotAllocation::count().' allocations');
    }
}
