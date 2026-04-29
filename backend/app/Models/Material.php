<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'code',
        'name',
        'description',
        'material_type_id',
        'unit_of_measure',
        'tracking_type',
        'default_scrap_percentage',
        'extra_data',
        'external_code',
        'external_system',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_scrap_percentage' => 'decimal:2',
            'extra_data' => 'array',
        ];
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(MaterialSource::class);
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByExternalCode($query, string $code, ?string $system = null)
    {
        $query->where('external_code', $code);

        if ($system) {
            $query->where('external_system', $system);
        }

        return $query;
    }
}
