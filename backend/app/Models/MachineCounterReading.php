<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Retained machine evidence; review fields record an explicit supervisor decision. */
class MachineCounterReading extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'raw_value' => 'decimal:2', 'delta' => 'decimal:2', 'applied_qty' => 'decimal:2', 'observed_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
