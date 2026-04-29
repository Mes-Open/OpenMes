<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingChecklist extends Model
{
    protected $fillable = [
        'batch_id',
        'checked_by',
        'checked_at',
        'udi_readable',
        'packaging_condition',
        'labels_readable',
        'label_matches_product',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'udi_readable' => 'boolean',
            'packaging_condition' => 'boolean',
            'labels_readable' => 'boolean',
            'label_matches_product' => 'boolean',
            'all_passed' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
