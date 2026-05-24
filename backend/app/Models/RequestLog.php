<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestLog extends Model
{
    use HasFactory;

    /**
     * Request logs are write-once: no updated_at column.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'method',
        'path',
        'route_name',
        'status',
        'duration_ms',
        'ip_address',
        'user_agent',
        'sampled',
    ];

    protected function casts(): array
    {
        return [
            'created_at'  => 'datetime',
            'sampled'     => 'boolean',
            'status'      => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * Enforce immutability per audit_logs pattern.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Request logs are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Request logs are immutable (use admin retention command if needed).');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeErrors($query)
    {
        return $query->where('status', '>=', 400);
    }

    public function scopeMutating($query)
    {
        return $query->whereIn('method', ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
