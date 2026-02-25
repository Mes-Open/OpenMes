<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineStatus extends Model
{
    protected $fillable = ['name', 'color', 'sort_order', 'line_id', 'is_default', 'is_done_status'];

    protected function casts(): array
    {
        return [
            'is_default'     => 'boolean',
            'is_done_status' => 'boolean',
        ];
    }

    public function line(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Return statuses available for a given line:
     * global statuses (line_id = null) + line-specific statuses.
     */
    public function scopeForLine($query, ?int $lineId)
    {
        return $query->where(function ($q) use ($lineId) {
            $q->whereNull('line_id');
            if ($lineId) {
                $q->orWhere('line_id', $lineId);
            }
        })->orderBy('sort_order')->orderBy('id');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('line_id')->orderBy('sort_order')->orderBy('id');
    }
}
