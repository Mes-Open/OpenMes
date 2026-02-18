<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImport extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'import_strategy',
        'mapping_id',
        'total_rows',
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
            'error_log'    => 'array',
            'started_at'   => 'datetime',
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
}
