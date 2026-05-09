<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DowntimeReason extends Model
{

    protected $fillable = [
        'name',
        'code',
        'is_planned',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_planned' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
