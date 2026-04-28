<?php

namespace Database\Seeders;

use App\Models\ViewTemplate;
use Illuminate\Database\Seeder;

class ViewTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ViewTemplate::updateOrCreate(
            ['name' => 'Simple'],
            [
                'description' => 'Basic view with order number, product and status.',
                'columns' => [
                    ['label' => 'Order No',    'key' => 'order_no',     'source' => 'field'],
                    ['label' => 'Product',     'key' => 'product_name', 'source' => 'product_type'],
                    ['label' => 'Status',      'key' => 'status',       'source' => 'field'],
                ],
            ]
        );

        ViewTemplate::updateOrCreate(
            ['name' => 'Advanced'],
            [
                'description' => 'Detailed production view with all imported CSV columns — material, dimensions, color, dye, brand.',
                'columns' => [
                    ['label' => 'Material',          'key' => 'material',          'source' => 'extra_data'],
                    ['label' => 'Diameter',          'key' => 'diameter',          'source' => 'extra_data'],
                    ['label' => 'Weight',            'key' => 'weight',            'source' => 'extra_data'],
                    ['label' => 'Spool',             'key' => 'spool',             'source' => 'extra_data'],
                    ['label' => 'Color',             'key' => 'color',             'source' => 'extra_data'],
                    ['label' => 'Dye',               'key' => 'dye',              'source' => 'extra_data'],
                    ['label' => 'Dosage %',          'key' => 'dosage_pct',        'source' => 'extra_data'],
                    ['label' => 'Brand',             'key' => 'brand',             'source' => 'extra_data'],
                    ['label' => 'Original Name',     'key' => 'original_name',     'source' => 'extra_data'],
                ],
            ]
        );
    }
}
