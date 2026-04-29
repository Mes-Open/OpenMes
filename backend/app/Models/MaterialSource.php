<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialSource extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'material_id',
        'integration_config_id',
        'external_code',
        'external_name',
        'unit_mapping',
        'conversion_factor',
        'last_synced_at',
        'sync_enabled',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'last_synced_at' => 'datetime',
            'sync_enabled' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function integrationConfig(): BelongsTo
    {
        return $this->belongsTo(IntegrationConfig::class);
    }
}
