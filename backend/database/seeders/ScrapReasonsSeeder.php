<?php

namespace Database\Seeders;

use App\Models\ScrapReason;
use Illuminate\Database\Seeder;

class ScrapReasonsSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'DIM-OOS',   'name' => 'Dimension out of spec', 'category' => ScrapReason::CATEGORY_METHOD],
            ['code' => 'SURF-DEF',  'name' => 'Surface defect',        'category' => ScrapReason::CATEGORY_MATERIAL],
            ['code' => 'WRONG-MAT', 'name' => 'Wrong material',        'category' => ScrapReason::CATEGORY_MATERIAL],
            ['code' => 'MACH-FAIL', 'name' => 'Machine malfunction',   'category' => ScrapReason::CATEGORY_MACHINE],
            ['code' => 'OP-ERR',    'name' => 'Operator error',        'category' => ScrapReason::CATEGORY_MAN],
        ];

        foreach ($reasons as $index => $reason) {
            ScrapReason::firstOrCreate(
                ['code' => $reason['code']],
                [
                    'name' => $reason['name'],
                    'category' => $reason['category'],
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
