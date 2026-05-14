<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'username',
        'ip_address',
        'marketing_consent',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'marketing_consent' => 'boolean',
        ];
    }
}
