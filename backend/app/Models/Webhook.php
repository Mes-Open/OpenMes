<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An outgoing webhook endpoint (#20). Subscribes to one or more events from
 * WebhookEventRegistry and receives an HMAC-signed POST when they fire.
 *
 * The `secret` is encrypted at rest (cast) and must never be synced to the
 * browser — it is excluded from the synced collection in ShapeRegistry.
 */
class Webhook extends Model
{
    use Auditable, HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'headers',
        'is_active',
        'last_triggered_at',
        'tenant_id',
    ];

    /** Keep the secret out of array/JSON serialization by default. */
    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Whether this endpoint is subscribed to the given event key. */
    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
