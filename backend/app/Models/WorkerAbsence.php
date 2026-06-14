<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerAbsence extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /** Supported absence types. */
    public const TYPES = ['vacation', 'sick', 'personal', 'training', 'other'];

    /** Supported statuses (supervisor-recorded → defaults to approved). */
    public const STATUSES = ['approved', 'pending', 'rejected'];

    protected $fillable = [
        'worker_id',
        'type',
        'starts_on',
        'ends_on',
        'all_day',
        'start_time',
        'end_time',
        'status',
        'reason',
        'created_by_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'all_day' => 'boolean',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Only approved absences actually make a worker unavailable. */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /** Absences overlapping the inclusive [$from, $to] date range. */
    public function scopeOverlapping($query, $from, $to)
    {
        return $query->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from);
    }
}
