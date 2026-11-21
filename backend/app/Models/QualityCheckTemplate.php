<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheckTemplate extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'process_template_id',
        'name',
        'min_checks_per_batch',
        'min_checks_per_day',
        'samples_per_check',
        'parameters',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'min_checks_per_batch' => 'integer',
            'min_checks_per_day' => 'integer',
            'samples_per_check' => 'integer',
            'parameters' => 'array',
        ];
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }

    /**
     * The QC template an in-process check on this batch is recorded against:
     * the first one defined on the process template the batch's work order was
     * released from. Null when the process has none.
     */
    public static function forBatch(Batch $batch): ?self
    {
        return static::forProcessTemplate($batch->workOrder?->process_snapshot['template_id'] ?? null);
    }

    /** The first QC template defined on a process template (see forBatch). */
    public static function forProcessTemplate(?int $processTemplateId): ?self
    {
        return $processTemplateId === null
            ? null
            : static::where('process_template_id', $processTemplateId)->orderBy('id')->first();
    }

    /**
     * The template's parameter definition by name, or null when it has none
     * of that name.
     *
     * @return array{name: string, type: string, unit?: ?string, min?: mixed, max?: mixed}|null
     */
    public function parameter(string $name): ?array
    {
        return collect($this->parameters ?? [])->firstWhere('name', $name);
    }
}
