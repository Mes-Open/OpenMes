<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Line extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the division this line belongs to.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the workstations for this line.
     */
    public function workstations(): HasMany
    {
        return $this->hasMany(Workstation::class);
    }

    /**
     * Get the work orders for this line.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the users assigned to this line.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'line_user');
    }

    /**
     * Get the custom statuses defined for this line.
     */
    public function lineStatuses(): HasMany
    {
        return $this->hasMany(LineStatus::class);
    }

    /**
     * Get product types assigned to this line.
     */
    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'line_product_type');
    }

    /**
     * Returns active workstations for this line.
     * If none are configured, returns a virtual stand-in representing the line itself.
     */
    public function effectiveWorkstations(): Collection
    {
        $ws = $this->workstations()->where('is_active', true)->get();

        if ($ws->isEmpty()) {
            return collect([(object) [
                'id'             => null,
                'name'           => $this->name,
                'code'           => $this->code,
                'is_active'      => true,
                'is_line_itself' => true,
            ]]);
        }

        return $ws->map(function ($w) {
            $w->is_line_itself = false;
            return $w;
        });
    }

    /**
     * Scope to get only active lines.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
