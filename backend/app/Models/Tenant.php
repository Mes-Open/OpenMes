<?php

namespace App\Models;

use App\Support\Units\DefaultUnitAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A tenant reads only its own unit aliases (UnitNormalizer), so it
        // needs its own editable copy of the defaults from the start —
        // otherwise its Settings → Units of Measure page is empty and its ERP
        // imports know no units at all.
        static::created(function (Tenant $tenant): void {
            // Guarded: a migration older than the unit_aliases table may
            // create a tenant before that table exists.
            if (Schema::hasTable('unit_aliases')) {
                DefaultUnitAliases::seedFor($tenant->id);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function processTemplates(): HasMany
    {
        return $this->hasMany(ProcessTemplate::class);
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class);
    }

    public function issueTypes(): HasMany
    {
        return $this->hasMany(IssueType::class);
    }
}
