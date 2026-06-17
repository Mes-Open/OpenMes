<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A corrective or preventive action (CAPA) attached to an Issue. Lifecycle:
 * OPEN → IN_PROGRESS → DONE → VERIFIED. An issue can only be closed once all of
 * its actions are VERIFIED (enforced in IssueService::closeIssue).
 */
class IssueAction extends Model
{
    use Auditable, HasFactory;
    use SoftDeletesWithAudit;

    const TYPE_CORRECTIVE = 'corrective';

    const TYPE_PREVENTIVE = 'preventive';

    const STATUS_OPEN = 'open';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_DONE = 'done';

    const STATUS_VERIFIED = 'verified';

    public const TYPES = [self::TYPE_CORRECTIVE, self::TYPE_PREVENTIVE];

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_VERIFIED];

    protected $fillable = [
        'issue_id',
        'type',
        'title',
        'description',
        'assigned_to_id',
        'due_date',
        'status',
        'completed_at',
        'completed_by_id',
        'verified_at',
        'verified_by_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function scopeUnverified($query)
    {
        return $query->where('status', '!=', self::STATUS_VERIFIED);
    }
}
