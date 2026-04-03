<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineMessage extends Model
{
    use HasFactory;

    const STATUS_OK      = 'ok';
    const STATUS_ERROR   = 'error';
    const STATUS_SKIPPED = 'skipped';

    // Append-only log — no updated_at
    public $timestamps = false;

    protected $fillable = [
        'machine_connection_id',
        'topic',
        'raw_payload',
        'parsed_data',
        'actions_triggered',
        'processing_status',
        'processing_error',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_data'       => 'array',
            'actions_triggered' => 'array',
            'received_at'       => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }

    public function statusColor(): string
    {
        return match ($this->processing_status) {
            self::STATUS_OK      => 'green',
            self::STATUS_ERROR   => 'red',
            self::STATUS_SKIPPED => 'yellow',
            default              => 'slate',
        };
    }

    /**
     * Prune messages older than the retention limit.
     * Keeps the last $limit records per connection.
     */
    public static function pruneForConnection(int $connectionId, int $limit = 10000): void
    {
        $oldest = static::where('machine_connection_id', $connectionId)
            ->orderByDesc('id')
            ->skip($limit)
            ->first();

        if ($oldest) {
            static::where('machine_connection_id', $connectionId)
                ->where('id', '<=', $oldest->id)
                ->delete();
        }
    }
}
