<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factory extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the divisions for this factory.
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /**
     * Scope to get only active factories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
