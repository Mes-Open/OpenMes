<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialType extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'code',
        'name',
        'tenant_id',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
