<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'shift_id',
        'line_id',
        'business_date',
        'shift_start',
        'shift_end',
        'produced_qty',
        'scrap_qty',
        'good_qty',
        'packed_qty',
        'wip_open_pallets_qty',
        'wip_unpacked_qty',
        'shipped_qty',
        'discrepancies',
        'breakdown',
        'notes',
        'confirmed_by',
        'confirmed_at',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'shift_start' => 'datetime',
            'shift_end' => 'datetime',
            'confirmed_at' => 'datetime',
            'produced_qty' => 'integer',
            'scrap_qty' => 'integer',
            'good_qty' => 'integer',
            'packed_qty' => 'integer',
            'wip_open_pallets_qty' => 'integer',
            'wip_unpacked_qty' => 'integer',
            'shipped_qty' => 'integer',
            'discrepancies' => 'array',
            'breakdown' => 'array',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
