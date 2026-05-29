<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModbusConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_connection_id',
        'host',
        'port',
        'unit_id',
        'poll_interval_ms',
        'timeout_seconds',
        'byte_order',
        'word_order',
        'max_registers_per_read',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'unit_id' => 'integer',
            'poll_interval_ms' => 'integer',
            'timeout_seconds' => 'integer',
            'max_registers_per_read' => 'integer',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }
}
