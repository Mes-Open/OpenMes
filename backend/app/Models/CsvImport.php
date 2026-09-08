<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One import run — any entity the unified importer (Admin → Import) knows, plus
 * the work-order runs the mobile API still queues through ProcessCsvImport.
 *
 * The per-outcome counters are written chunk by chunk while the job runs; the
 * row is live-synced (`data_imports`), so they are what the progress bar reads.
 */
class CsvImport extends Model
{
    use HasFactory, HasTenant;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING];

    /** Structured errors kept per run; anything past this is counted, not stored. */
    public const MAX_STORED_ERRORS = 1000;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'entity',
        'filename',
        'original_filename',
        'file_path',
        'import_strategy',
        'dry_run',
        'options',
        'mapping_id',
        'total_rows',
        'processed_rows',
        'created_rows',
        'updated_rows',
        'skipped_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'options' => 'array',
            'error_log' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(CsvImportMapping::class);
    }

    /** 0–100, from processed vs total; a finished run always reads 100. */
    public function progress(): int
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            return 100;
        }

        if ((int) $this->total_rows <= 0) {
            return 0;
        }

        return (int) min(100, floor($this->processed_rows * 100 / $this->total_rows));
    }
}
