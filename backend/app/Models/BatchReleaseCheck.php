<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchReleaseCheck extends Model
{
    use Auditable, HasFactory, HasTenant;

    public const TYPE_RETENTION_SAMPLE = 'RETENTION_SAMPLE';

    public const TYPE_LINE_CLEARANCE = 'LINE_CLEARANCE';

    public const TYPE_LABEL_BALANCE = 'LABEL_BALANCE';

    public const TYPES = [
        self::TYPE_RETENTION_SAMPLE,
        self::TYPE_LINE_CLEARANCE,
        self::TYPE_LABEL_BALANCE,
    ];

    public const STATUS_PASS = 'PASS';

    public const STATUS_FAIL = 'FAIL';

    public const STATUSES = [self::STATUS_PASS, self::STATUS_FAIL];

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'check_type',
        'status',
        'checked_by_id',
        'checked_at',
        'evidence',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_RETENTION_SAMPLE => '留样登记',
            self::TYPE_LINE_CLEARANCE => '清线检查',
            self::TYPE_LABEL_BALANCE => '标签平衡',
            default => $type,
        };
    }
}
