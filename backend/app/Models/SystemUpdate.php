<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for a single self-update run.
 *
 * One row is created at the start of `UpdateApplier::run()` in the `queued`
 * state and transitioned to one of the terminal states `completed`, `failed`,
 * or `rolled_back` as the run progresses. The row is the only durable record
 * of *who* triggered an update and *what* happened — the `update_apply_status`
 * cache key is ephemeral (24h TTL) and the laravel.log is rotated.
 */
class SystemUpdate extends Model
{
    use HasFactory;

    public const STATE_QUEUED      = 'queued';
    public const STATE_COMPLETED   = 'completed';
    public const STATE_FAILED      = 'failed';
    public const STATE_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'user_id',
        'from_version',
        'to_version',
        'state',
        'started_at',
        'finished_at',
        'duration_seconds',
        'files_copied',
        'error',
        'composer_install_ran',
        'checksum_verified',
    ];

    protected function casts(): array
    {
        return [
            'started_at'           => 'datetime',
            'finished_at'          => 'datetime',
            'duration_seconds'     => 'integer',
            'files_copied'         => 'integer',
            'composer_install_ran' => 'boolean',
            'checksum_verified'    => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
