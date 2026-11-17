<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery attempt-set for an outgoing webhook (#20). The row is created
 * pending, then updated in place by the queued DeliverWebhookJob as it
 * succeeds or exhausts its retries.
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /** Signed "{timestamp}.{body}" (X-OpenMES-Timestamp + X-OpenMES-Signature). */
    public const SIGNATURE_V1 = 'v1';

    /** Signed the body only (webhook has legacy_signature on). */
    public const SIGNATURE_LEGACY = 'legacy';

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'signature_timestamp',
        'signature_scheme',
        'response_code',
        'response_body',
        'error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'signature_timestamp' => 'integer',
            'response_code' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
