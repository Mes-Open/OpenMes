<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkstationState extends Model
{
    use HasFactory;

    public const RUNNING = 'RUNNING';

    public const IDLE = 'IDLE';

    public const STOPPED = 'STOPPED';

    public const FAULT = 'FAULT';

    public const SETUP = 'SETUP';

    public const STATES = [self::RUNNING, self::IDLE, self::STOPPED, self::FAULT, self::SETUP];

    /** States that count as availability loss (drive downtime). */
    public const LOSS_STATES = [self::STOPPED, self::FAULT];

    protected $fillable = [
        'workstation_id',
        'state',
        'started_at',
        'ended_at',
        'duration_seconds',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function isLoss(): bool
    {
        return in_array($this->state, self::LOSS_STATES, true);
    }
}
