<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Line extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'division_id',
        'code',
        'name',
        'description',
        'is_active',
        'tenant_id',
        'view_template_id',
        'default_operator_view',
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
     * Get the view template assigned to this line.
     */
    public function viewTemplate(): BelongsTo
    {
        return $this->belongsTo(ViewTemplate::class);
    }

    /**
     * Get the view columns configured for this line's workstation view.
     */
    public function viewColumns(): HasMany
    {
        return $this->hasMany(LineViewColumn::class)->orderBy('sort_order');
    }

    /**
     * Get effective view columns: from template first, fallback to line-specific.
     */
    public function getEffectiveViewColumns(): \Illuminate\Support\Collection
    {
        if ($this->viewTemplate) {
            return collect($this->viewTemplate->columns ?? []);
        }
        return $this->viewColumns->map(fn($c) => ['label' => $c->label, 'key' => $c->key, 'source' => $c->source]);
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
