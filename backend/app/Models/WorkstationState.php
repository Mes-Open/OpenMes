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

    // Added states (#87) for more accurate downtime categorisation.
    public const WAITING = 'WAITING';

    public const CLEANING = 'CLEANING';

    public const MAINTENANCE = 'MAINTENANCE';

    public const STATES = [
        self::RUNNING, self::IDLE, self::STOPPED, self::FAULT, self::SETUP,
        self::WAITING, self::CLEANING, self::MAINTENANCE,
    ];

    /**
     * States that count as UNPLANNED availability loss — entering one opens an
     * unplanned ProductionDowntime that lowers OEE availability. WAITING (the
     * machine is idle waiting for material/operator) is lost time (#87).
     */
    public const LOSS_STATES = [self::STOPPED, self::FAULT, self::WAITING];

    /**
     * States that drive PLANNED downtime — scheduled activities that reduce the
     * planned operating time but are NOT counted as an availability loss (#87).
     */
    public const PLANNED_STATES = [self::CLEANING, self::MAINTENANCE];

    /** Every state that auto-opens a ProductionDowntime when entered. */
    public const DOWNTIME_STATES = [
        self::STOPPED, self::FAULT, self::WAITING, self::CLEANING, self::MAINTENANCE,
    ];

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

    /** Whether entering this state opens a planned (scheduled) downtime (#87). */
    public function isPlanned(): bool
    {
        return in_array($this->state, self::PLANNED_STATES, true);
    }
}
