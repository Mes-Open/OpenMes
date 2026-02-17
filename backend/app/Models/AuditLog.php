<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    const UPDATED_AT = null; // Audit logs are immutable, no updated_at

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a human-readable entity name.
     */
    public function getEntityNameAttribute(): string
    {
        $parts = explode('\\', $this->entity_type);
        return end($parts);
    }

    /**
     * Scope to filter by entity type.
     */
    public function scopeEntity($query, string $entityType)
    {
        return $query->where('entity_type', 'like', "%{$entityType}%");
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
