<?php

namespace Database\Seeders;

use App\Models\LineStatus;
use Illuminate\Database\Seeder;

class LineStatusSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Todo',        'color' => '#6B7280', 'sort_order' => 1, 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#3B82F6', 'sort_order' => 2, 'is_default' => false],
            ['name' => 'Done',        'color' => '#22C55E', 'sort_order' => 3, 'is_default' => false],
        ];

        foreach ($defaults as $data) {
            LineStatus::firstOrCreate(
                ['line_id' => null, 'name' => $data['name']],
                $data
            );
        }
    }
}
