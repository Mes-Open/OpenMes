<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchLabTest extends Model
{
    use Auditable, HasFactory, HasTenant;

    public const CONCLUSION_PASS = 'PASS';

    public const CONCLUSION_FAIL = 'FAIL';

    public const CONCLUSIONS = [self::CONCLUSION_PASS, self::CONCLUSION_FAIL];

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'lab_name',
        'report_number',
        'sample_number',
        'sampled_at',
        'issued_at',
        'overall_conclusion',
        'items',
        'attachment_path',
        'notes',
        'recorded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'date',
            'issued_at' => 'date',
            'items' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function allPassed(): bool
    {
        return $this->overall_conclusion === self::CONCLUSION_PASS;
    }

    /**
     * 按农残/重金属/微生物等类别汇总，供 PDF 和详情页紧凑展示。
     */
    public function categorySummaries(): array
    {
        return collect($this->items ?? [])
            ->groupBy(fn (array $item) => $item['category'] ?? '其他')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items' => $items->values()->all(),
                'conclusion' => $items->every(fn (array $item) => in_array($item['conclusion'] ?? '合格', ['合格', 'PASS', 'pass'], true))
                    ? '合格'
                    : '不合格',
            ])
            ->values()
            ->all();
    }
}
