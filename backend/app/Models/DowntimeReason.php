<?php

namespace App\Models;

use App\Enums\DowntimeKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DowntimeReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'kind',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DowntimeKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
