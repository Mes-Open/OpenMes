<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'widget_id',
        'name',
        'zone',
        'description',
        'source',
        'module_name',
        'enabled',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForZone($query, string $zone)
    {
        return $query->where('zone', $zone)->orderBy('sort_order');
    }
}
