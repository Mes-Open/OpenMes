<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Modules\Packaging\Models\LabelTemplate;

class LabelTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(LabelTemplate::class)) {
            return; // Packaging module not installed
        }

        $defaults = [
            LabelTemplate::TYPE_WORK_ORDER => [
                'name' => 'Standard Work Order',
                'size' => '100x50',
            ],
            LabelTemplate::TYPE_FINISHED_GOODS => [
                'name' => 'Standard Finished Goods',
                'size' => '100x50',
            ],
            LabelTemplate::TYPE_WORKSTATION_STEP => [
                'name' => 'Standard Workstation Step',
                'size' => '80x40',
            ],
        ];

        $tenants = Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->seedFor(null, $defaults);

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedFor($tenant->id, $defaults);
        }
    }

    private function seedFor(?int $tenantId, array $defaults): void
    {
        foreach ($defaults as $type => $config) {
            LabelTemplate::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'type' => $type,
                    'is_default' => true,
                ],
                [
                    'name' => $config['name'],
                    'size' => $config['size'],
                    'fields_config' => LabelTemplate::defaultFieldsFor($type),
                    'barcode_format' => 'code128',
                    'is_active' => true,
                ]
            );
        }
    }
}
