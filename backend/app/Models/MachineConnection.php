<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MachineConnection extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    const PROTOCOL_MQTT = 'mqtt';

    const PROTOCOL_OPCUA = 'opcua';

    const PROTOCOL_MODBUS = 'modbus';

    const PROTOCOL_REST = 'rest';

    const STATUS_DISCONNECTED = 'disconnected';

    const STATUS_CONNECTED = 'connected';

    const STATUS_CONNECTING = 'connecting';

    const STATUS_ERROR = 'error';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'protocol',
        'line_id',
        'is_active',
        'status',
        'status_message',
        'last_connected_at',
        'messages_received',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
            'messages_received' => 'integer',
        ];
    }

    /**
     * The production line this device feeds — the default target for its topic
     * mappings (e.g. a break-beam sensor counting units on this line).
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function mqttConnection(): HasOne
    {
        return $this->hasOne(MqttConnection::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(MachineTopic::class);
    }

    public function activeTopics(): HasMany
    {
        return $this->hasMany(MachineTopic::class)->where('is_active', true);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MachineMessage::class);
    }

    public function modbusConnection(): HasOne
    {
        return $this->hasOne(ModbusConnection::class);
    }

    public function opcuaConnection(): HasOne
    {
        return $this->hasOne(OpcuaConnection::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(MachineTag::class);
    }

    public function activeTags(): HasMany
    {
        return $this->hasMany(MachineTag::class)->where('is_active', true);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * The status to show. The stored one is whatever the collector last wrote,
     * and nothing resets it when the collector stops (container killed,
     * connection switched off): a dead Modbus poller would keep drawing a live
     * green "connected". Only a collector heard from recently can vouch for it.
     */
    public function liveStatus(): string
    {
        if (! $this->is_active) {
            return self::STATUS_DISCONNECTED;
        }

        // REST devices push on their own schedule; there is no collector to watch.
        $collected = in_array($this->protocol, [self::PROTOCOL_MQTT, self::PROTOCOL_MODBUS, self::PROTOCOL_OPCUA], true);
        if ($collected && ! app(\App\Services\Machine\RuntimeMonitor::class)->isAlive($this->protocol, $this->id)) {
            return $this->status === self::STATUS_ERROR ? self::STATUS_ERROR : self::STATUS_DISCONNECTED;
        }

        return (string) $this->status;
    }

    /**
     * How many connections exist per protocol — the counts on the
     * Connectivity tabs, which every connection list shows.
     *
     * @return array<string, int>
     */
    public static function protocolCounts(): array
    {
        return static::query()
            ->selectRaw('protocol, count(*) as total')
            ->groupBy('protocol')
            ->pluck('total', 'protocol')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Why the connection is in error, when it is; null otherwise. */
    public function liveStatusMessage(): ?string
    {
        return $this->liveStatus() === self::STATUS_ERROR ? $this->status_message : null;
    }

    public function liveStatusColor(): string
    {
        return self::colorFor($this->liveStatus());
    }

    public function statusColor(): string
    {
        return self::colorFor((string) $this->status);
    }

    private static function colorFor(string $status): string
    {
        return match ($status) {
            self::STATUS_CONNECTED => 'green',
            self::STATUS_CONNECTING => 'yellow',
            self::STATUS_ERROR => 'red',
            default => 'slate',
        };
    }

    public function markConnected(): void
    {
        $this->update([
            'status' => self::STATUS_CONNECTED,
            'status_message' => null,
            'last_connected_at' => now(),
        ]);
    }

    public function markDisconnected(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_DISCONNECTED,
            'status_message' => $reason,
        ]);
    }

    public function markError(string $message): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'status_message' => $message,
        ]);
    }

    public function incrementMessageCount(): void
    {
        $this->increment('messages_received');
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\MachineTopic::class, 'machine_connection_id'],
            [\App\Models\MachineTag::class, 'machine_connection_id'],
            // Protocol-specific config rows had cascadeOnDelete FKs too.
            [\App\Models\MqttConnection::class, 'machine_connection_id'],
            [\App\Models\ModbusConnection::class, 'machine_connection_id'],
            [\App\Models\OpcuaConnection::class, 'machine_connection_id'],
        ];
    }
}
