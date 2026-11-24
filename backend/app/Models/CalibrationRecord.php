<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weighing scale or instrument with a calibration due date. The stored
 * status only records a manual lock; overdue and due-soon are derived so a
 * page never claims "calibrated" just because nobody updated a flag.
 */
class CalibrationRecord extends Model
{
    use HasFactory, HasTenant;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_LOCKED = 'LOCKED';

    public const DERIVED_ACTIVE = 'ACTIVE';
    public const DERIVED_DUE_SOON = 'DUE_SOON';
    public const DERIVED_OVERDUE = 'OVERDUE';

    public const DUE_SOON_DAYS = 7;

    protected $fillable = [
        'equipment_code',
        'equipment_name',
        'equipment_kind',
        'location',
        'last_calibrated_at',
        'next_due_at',
        'certificate_no',
        'last_result',
        'status',
        'lock_reason',
        'unlock_condition',
        'responsible_user_id',
        'notes',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'last_calibrated_at' => 'datetime',
            'next_due_at' => 'datetime',
        ];
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_LOCKED) {
            return self::STATUS_LOCKED;
        }

        if (! $this->next_due_at) {
            return self::DERIVED_ACTIVE;
        }

        if ($this->next_due_at->isPast()) {
            return self::DERIVED_OVERDUE;
        }

        if ($this->next_due_at->lte(now()->addDays(self::DUE_SOON_DAYS))) {
            return self::DERIVED_DUE_SOON;
        }

        return self::DERIVED_ACTIVE;
    }

    public function isLocked(): bool
    {
        return in_array($this->displayStatus(), [self::STATUS_LOCKED, self::DERIVED_OVERDUE], true);
    }

    public function daysLeft(): ?int
    {
        if (! $this->next_due_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->next_due_at->copy()->startOfDay(), false);
    }

    public function unlockText(): string
    {
        if (! $this->isLocked()) {
            return '无需解锁';
        }

        return $this->unlock_condition
            ?: '完成校准、上传新证书，并由 QA 复核后解锁。';
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->where('status', self::STATUS_LOCKED)
                ->orWhere(function (Builder $inner) {
                    $inner->whereNotNull('next_due_at')->where('next_due_at', '<=', now());
                });
        });
    }

    public function scopeDueSoon(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_LOCKED)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>', now())
            ->where('next_due_at', '<=', now()->addDays(self::DUE_SOON_DAYS));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_LOCKED)
            ->where(function (Builder $builder) {
                $builder->whereNull('next_due_at')
                    ->orWhere('next_due_at', '>', now()->addDays(self::DUE_SOON_DAYS));
            });
    }

    public function statusTone(): string
    {
        return match ($this->displayStatus()) {
            self::STATUS_LOCKED => 'bg-om-blocked-bg text-om-blocked',
            self::DERIVED_OVERDUE => 'bg-om-blocked-bg text-om-blocked',
            self::DERIVED_DUE_SOON => 'bg-om-downtime-bg text-om-downtime',
            default => 'bg-om-running-bg text-om-running',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->displayStatus()) {
            self::STATUS_LOCKED => '已锁定',
            self::DERIVED_OVERDUE => '已过期',
            self::DERIVED_DUE_SOON => '即将到期',
            default => '正常',
        };
    }

    public function statusIcon(): string
    {
        return match ($this->displayStatus()) {
            self::STATUS_LOCKED => 'lock',
            self::DERIVED_OVERDUE => 'alert-triangle',
            self::DERIVED_DUE_SOON => 'clock',
            default => 'check',
        };
    }
}
