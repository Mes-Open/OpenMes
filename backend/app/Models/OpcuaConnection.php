<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcuaConnection extends Model
{
    use HasFactory;

    protected $table = 'opcua_connections';

    protected $fillable = [
        'machine_connection_id',
        'endpoint_url',
        'security_policy',
        'security_mode',
        'auth_mode',
        'username',
        'password_encrypted',
        'client_cert',
        'client_key_encrypted',
        'publishing_interval_ms',
    ];

    protected $hidden = ['password_encrypted', 'client_key_encrypted'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }
}
