<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop-floor PC running the OpenMES Workstation client. Self-registers by IP
 * and heartbeats; the MAIN app shows the roster live and derives online status
 * from the heartbeat clock.
 */
class WorkstationDevice extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    /** A device is considered online if it heartbeat within this many seconds. */
    public const ONLINE_WINDOW_SECONDS = 30;

    protected $fillable = [
        'device_uuid',
        'name',
        'ip_address',
        'hostname',
        'app_version',
        'line_id',
        'last_seen_at',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Online = heartbeat seen within the online window.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    /**
     * Scope to devices that have heartbeat recently.
     */
    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }
}
