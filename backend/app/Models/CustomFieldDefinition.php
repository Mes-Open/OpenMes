<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-defined schema for a single custom field on a given entity-type.
 * Values themselves live in each entity's `custom_fields` JSON column; this
 * table only describes them. Tenant-scoped: a null tenant_id is a global
 * definition visible to every tenant (see HasTenant / TenantScope).
 */
class CustomFieldDefinition extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'entity_type',
        'key',
        'label',
        'type',
        'config',
        'required',
        'position',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'required' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'type' => CustomFieldType::class,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }
}
