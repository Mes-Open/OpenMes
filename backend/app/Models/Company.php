<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, Auditable;

    const TYPE_SUPPLIER = 'supplier';
    const TYPE_CUSTOMER = 'customer';
    const TYPE_BOTH = 'both';

    protected $fillable = [
        'code',
        'name',
        'tax_id',
        'type',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to get only active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only suppliers.
     */
    public function scopeSuppliers($query)
    {
        return $query->whereIn('type', [self::TYPE_SUPPLIER, self::TYPE_BOTH]);
    }

    /**
     * Scope to get only customers.
     */
    public function scopeCustomers($query)
    {
        return $query->whereIn('type', [self::TYPE_CUSTOMER, self::TYPE_BOTH]);
    }
}
