<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeActivityCustomType extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'code', 'label', 'color', 'icon', 'is_active', 'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
