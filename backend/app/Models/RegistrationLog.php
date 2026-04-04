<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'username',
        'ip_address',
        'registered_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'expired_at'    => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
