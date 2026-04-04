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
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }
}
