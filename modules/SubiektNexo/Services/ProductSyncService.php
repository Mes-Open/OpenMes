<?php

namespace Modules\SubiektNexo\Services;

use App\Models\Material;
use App\Models\MaterialType;

class ProductSyncService
{
    public function __construct(private SferaClient $client) {}

    /**
     * Sync products from Subiekt to OpenMES materials.
     * Matches by external_code (Symbol in Subiekt).
     *
     * @return array{synced: int, created: int, updated: int, errors: array}
     */
    public function syncProducts(int $limit = 500): array
    {
        $result = ['synced' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        $response = $this->client->getProducts($limit);
        $items = $response['items'] ?? [];

        if (empty($items)) {
            $result['errors'][] = $response['error'] ?? 'No products returned from Sfera';

            return $result;
        }

        $defaultTypeId = MaterialType::where('code', 'raw_material')->value('id');

        foreach ($items as $item) {
            try {
                $existing = Material::where('external_code', $item['Symbol'])
                    ->where('external_system', 'subiekt_nexo')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'name' => $item['Nazwa'],
                    ]);
                    $result['updated']++;
                } else {
                    Material::create([
                        'code' => $this->generateCode($item['Symbol']),
                        'name' => $item['Nazwa'],
                        'material_type_id' => $defaultTypeId,
                        'unit_of_measure' => 'pcs',
                        'external_code' => $item['Symbol'],
                        'external_system' => 'subiekt_nexo',
                        'is_active' => true,
                    ]);
                    $result['created']++;
                }

                $result['synced']++;
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'symbol' => $item['Symbol'] ?? '?',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Sync single product by Subiekt symbol.
     */
    public function syncProduct(string $symbol): ?Material
    {
        $item = $this->client->getProduct($symbol);

        if (! $item) {
            return null;
        }

        $defaultTypeId = MaterialType::where('code', 'raw_material')->value('id');

        return Material::updateOrCreate(
            ['external_code' => $item['Symbol'], 'external_system' => 'subiekt_nexo'],
            [
                'code' => $this->generateCode($item['Symbol']),
                'name' => $item['Nazwa'],
                'material_type_id' => $defaultTypeId,
                'unit_of_measure' => 'pcs',
                'is_active' => true,
            ]
        );
    }

    private function generateCode(string $symbol): string
    {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9-]/', '', $symbol), 0, 50));

        if (! Material::where('code', $code)->exists()) {
            return $code;
        }

        $i = 1;
        while (Material::where('code', "{$code}-{$i}")->exists()) {
            $i++;
        }

        return "{$code}-{$i}";
    }
}
