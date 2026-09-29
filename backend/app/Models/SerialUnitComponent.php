<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A component bound into a serialised unit. Never deleted: taking a component
 * out closes the row with `unbound_at`, which is how the unit's history keeps
 * saying what was in it and when - the answer a recall needs.
 */
class SerialUnitComponent extends Model
{
    use HasTenant;

    protected $fillable = [
        'serial_unit_id',
        'identifier',
        'component_serial_unit_id',
        'material_lot_id',
        'material_id',
        'quantity',
        'batch_step_id',
        'workstation_id',
        'bound_by_id',
        'bound_at',
        'unbound_at',
        'unbound_by_id',
        'unbind_reason',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'bound_at' => 'datetime',
            'unbound_at' => 'datetime',
        ];
    }

    public function scopeInstalled(Builder $query): Builder
    {
        return $query->whereNull('unbound_at');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    /** The component when it is a serialised unit of its own (a sub-assembly). */
    public function componentUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'component_serial_unit_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function boundBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bound_by_id');
    }
}
