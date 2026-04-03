<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MqttConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_connection_id',
        'broker_host',
        'broker_port',
        'client_id',
        'username',
        'password_encrypted',
        'use_tls',
        'ca_cert',
        'keep_alive_seconds',
        'qos_default',
        'clean_session',
        'connect_timeout',
        'reconnect_delay_seconds',
    ];

    protected function casts(): array
    {
        return [
            'use_tls'                  => 'boolean',
            'clean_session'            => 'boolean',
            'broker_port'              => 'integer',
            'qos_default'              => 'integer',
            'keep_alive_seconds'       => 'integer',
            'connect_timeout'          => 'integer',
            'reconnect_delay_seconds'  => 'integer',
        ];
    }

    public function machineConnection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class);
    }

    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password_encrypted'] = $password !== ''
            ? Crypt::encryptString($password)
            : null;
    }

    public function getPassword(): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Exception) {
            return null;
        }
    }

    public function getEffectiveClientId(): string
    {
        return $this->client_id ?: 'openmmes-' . $this->machine_connection_id . '-' . substr(md5(config('app.key')), 0, 8);
    }
}
