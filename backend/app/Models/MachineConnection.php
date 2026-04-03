<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MachineConnection extends Model
{
    use HasFactory;

    const PROTOCOL_MQTT   = 'mqtt';
    const PROTOCOL_OPCUA  = 'opcua';
    const PROTOCOL_MODBUS = 'modbus';
    const PROTOCOL_REST   = 'rest';

    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_CONNECTED    = 'connected';
    const STATUS_CONNECTING   = 'connecting';
    const STATUS_ERROR        = 'error';

    protected $fillable = [
        'name',
        'description',
        'protocol',
        'is_active',
        'status',
        'status_message',
        'last_connected_at',
        'messages_received',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'last_connected_at' => 'datetime',
            'messages_received' => 'integer',
        ];
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

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_CONNECTED    => 'green',
            self::STATUS_CONNECTING   => 'yellow',
            self::STATUS_ERROR        => 'red',
            default                   => 'slate',
        };
    }

    public function markConnected(): void
    {
        $this->update([
            'status'           => self::STATUS_CONNECTED,
            'status_message'   => null,
            'last_connected_at' => now(),
        ]);
    }

    public function markDisconnected(string $reason = null): void
    {
        $this->update([
            'status'         => self::STATUS_DISCONNECTED,
            'status_message' => $reason,
        ]);
    }

    public function markError(string $message): void
    {
        $this->update([
            'status'         => self::STATUS_ERROR,
            'status_message' => $message,
        ]);
    }

    public function incrementMessageCount(): void
    {
        $this->increment('messages_received');
    }
}
