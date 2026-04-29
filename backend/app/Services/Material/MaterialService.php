<?php

namespace App\Services\Material;

use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;

class MaterialService
{
    public function list(array $filters = []): Collection
    {
        $query = Material::with('materialType');

        if (isset($filters['material_type_id'])) {
            $query->where('material_type_id', $filters['material_type_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('external_code', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['external_system'])) {
            $query->where('external_system', $filters['external_system']);
        }

        return $query->orderBy('name')->get();
    }

    public function create(array $data): Material
    {
        return Material::create($data);
    }

    public function update(Material $material, array $data): Material
    {
        $material->update($data);

        return $material->fresh('materialType');
    }

    public function delete(Material $material): void
    {
        $material->delete();
    }
}
