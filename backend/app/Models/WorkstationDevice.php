<?php

namespace App\Models;

use App\Enums\WorkstationDeviceStatus;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop-floor station connected to the MAIN app: either a PC running the
 * OpenMES Workstation client (self-registers by IP) or a browser running the
 * operator panel (heartbeats while an operator is logged in). Both heartbeat;
 * the MAIN app shows the roster live and derives the active status from the
 * reported status plus the heartbeat clock.
 */
class WorkstationDevice extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    /**
     * A device counts as active only if it heartbeat within this many seconds.
     * The admin roster receives it as a page prop, so client and server agree.
     */
    public const ONLINE_WINDOW_SECONDS = 60;

    /** Prefix of device_uuid for operator-panel rows, so they never collide with client uuids. */
    public const PANEL_UUID_PREFIX = 'panel:';

    /** Name a new operator-panel row gets until an admin renames it. */
    public const DEFAULT_PANEL_NAME = 'Operator panel';

    protected $fillable = [
        'device_uuid',
        'name',
        'ip_address',
        'hostname',
        'app_version',
        'line_id',
        'workstation_id',
        'user_id',
        'status',
        'last_seen_at',
        'registered_at',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkstationDeviceStatus::class,
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** Workstation selected in the operator panel on this device. */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /** The user last logged in to the operator panel on this device. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Active = the device reported "active" and heartbeat within the window.
     * Silence for longer than the window makes it inactive regardless.
     */
    public function isOnline(): bool
    {
        return $this->status === WorkstationDeviceStatus::Active
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    public function isPanel(): bool
    {
        return str_starts_with((string) $this->device_uuid, self::PANEL_UUID_PREFIX);
    }

    /** Operator-panel rows only (the browser panel, not the Workstation client). */
    public function scopePanels($query)
    {
        return $query->where('device_uuid', 'like', self::PANEL_UUID_PREFIX.'%');
    }

    public function effectiveStatus(): WorkstationDeviceStatus
    {
        return $this->isOnline() ? WorkstationDeviceStatus::Active : WorkstationDeviceStatus::Inactive;
    }

    /**
     * Scope to devices that count as active (see isOnline()).
     */
    public function scopeOnline($query)
    {
        return $query
            ->where('status', WorkstationDeviceStatus::Active->value)
            ->where('last_seen_at', '>', now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }
}
