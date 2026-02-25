<?php

namespace Database\Seeders;

use App\Models\LineStatus;
use Illuminate\Database\Seeder;

class LineStatusSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Todo',        'color' => '#6B7280', 'sort_order' => 1, 'is_default' => true,  'is_done_status' => false],
            ['name' => 'In Progress', 'color' => '#3B82F6', 'sort_order' => 2, 'is_default' => false, 'is_done_status' => false],
            ['name' => 'Done',        'color' => '#22C55E', 'sort_order' => 3, 'is_default' => false, 'is_done_status' => true],
        ];

        foreach ($defaults as $data) {
            LineStatus::firstOrCreate(
                ['line_id' => null, 'name' => $data['name']],
                $data
            );
        }

        // Ensure existing "Done" rows have is_done_status = true
        LineStatus::where('name', 'Done')->whereNull('line_id')->update(['is_done_status' => true]);
    }
}
