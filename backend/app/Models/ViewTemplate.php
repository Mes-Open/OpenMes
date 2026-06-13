<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViewTemplate extends Model
{
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'description',
        'columns',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }
}
