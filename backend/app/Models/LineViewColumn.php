<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineViewColumn extends Model
{
    protected $fillable = [
        'line_id',
        'label',
        'source',
        'key',
        'sort_order',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
