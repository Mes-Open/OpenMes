<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Support\Str;

class MaterialSyncService
{
    /**
     * Import materials from an external system.
     * Matches by external_code + source_system. Creates or updates.
     *
     * @return array{created: int, updated: int, errors: array}
     */
    public function importFromExternalSystem(string $sourceSystem, array $materials): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $typeCache = MaterialType::pluck('id', 'code')->toArray();
        $defaultTypeId = $typeCache['raw_material'] ?? null;

        foreach ($materials as $index => $row) {
            try {
                $typeId = $defaultTypeId;
                if (isset($row['type']) && isset($typeCache[$row['type']])) {
                    $typeId = $typeCache[$row['type']];
                }

                $existing = Material::where('external_code', $row['external_code'])
                    ->where('external_system', $sourceSystem)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'name' => $row['name'],
                        'description' => $row['description'] ?? $existing->description,
                        'material_type_id' => $typeId,
                        'unit_of_measure' => $row['unit'] ?? $existing->unit_of_measure,
                        'extra_data' => $row['extra_data'] ?? $existing->extra_data,
                    ]);
                    $result['updated']++;
                } else {
                    $code = $this->generateUniqueCode($row['external_code']);

                    Material::create([
                        'code' => $code,
                        'name' => $row['name'],
                        'description' => $row['description'] ?? null,
                        'material_type_id' => $typeId,
                        'unit_of_measure' => $row['unit'] ?? 'pcs',
                        'external_code' => $row['external_code'],
                        'external_system' => $sourceSystem,
                        'extra_data' => $row['extra_data'] ?? null,
                    ]);
                    $result['created']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'index' => $index,
                    'external_code' => $row['external_code'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function generateUniqueCode(string $externalCode): string
    {
        $code = Str::upper(Str::slug($externalCode, '-'));
        $code = Str::limit($code, 47, '');

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
