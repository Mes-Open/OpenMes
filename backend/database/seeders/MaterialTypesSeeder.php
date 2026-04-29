<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'raw_material', 'name' => 'Raw Material'],
            ['code' => 'semi_finished', 'name' => 'Semi-Finished Product'],
            ['code' => 'packaging', 'name' => 'Packaging Material'],
            ['code' => 'auxiliary', 'name' => 'Auxiliary Material'],
        ];

        foreach ($types as $type) {
            DB::table('material_types')->updateOrInsert(
                ['code' => $type['code'], 'tenant_id' => null],
                $type
            );
        }
    }
}
