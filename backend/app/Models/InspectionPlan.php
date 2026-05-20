<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionPlan extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'name',
        'description',
        'material_id',
        'material_type_id',
        'criteria',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApplicableTo($query, Material $material)
    {
        return $query->active()->where(function ($q) use ($material) {
            $q->where('material_id', $material->id)
                ->orWhere('material_type_id', $material->material_type_id)
                ->orWhere(function ($q2) {
                    $q2->whereNull('material_id')->whereNull('material_type_id');
                });
        });
    }
}
