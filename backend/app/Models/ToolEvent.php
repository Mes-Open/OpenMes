<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_id',
        'workstation_id',
        'event_type',
        'occurred_at',
        'usage_count_delta',
        'wear_percentage_after',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'usage_count_delta' => 'float',
        'wear_percentage_after' => 'float',
        'metadata' => 'array',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }
}
