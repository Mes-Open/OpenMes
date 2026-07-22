<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single physical relocation of a pallet, attributable to the logistics
 * operator (Worker) who performed it (#103). Append-only history: rows are
 * never edited or deleted once written, so the movement trail is authoritative.
 */
class PalletMovement extends Model
{
    use HasFactory;
    use HasTenant;

    protected $fillable = [
        'pallet_id',
        'worker_id',
        'from_location',
        'to_location',
        'moved_at',
        'notes',
        'performed_by',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    /** The logistics operator credited with performing the move. */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** The account that recorded the move on the terminal. */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
