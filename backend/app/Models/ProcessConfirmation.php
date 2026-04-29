<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessConfirmation extends Model
{
    use HasTenant;

    const TYPE_PARAMETERS = 'parameters';

    const TYPE_DRYING = 'drying';

    const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'batch_id',
        'batch_step_id',
        'confirmed_by',
        'confirmed_at',
        'confirmation_type',
        'notes',
        'value',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function batchStep(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
