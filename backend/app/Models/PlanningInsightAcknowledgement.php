<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a planner did about one Production Planner insight, and who did it.
 * Undo soft-deletes the row (after reverting the move or the substitute it
 * recorded), which reopens the insight.
 */
class PlanningInsightAcknowledgement extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    public const ACTION_ACKNOWLEDGE = 'acknowledge';

    public const ACTION_MOVE = 'move';

    public const ACTION_SUBSTITUTE = 'substitute';

    protected $fillable = [
        'insight_key',
        'insight_type',
        'action',
        'title',
        'summary',
        'signature',
        'payload',
        'acknowledged_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }
}
