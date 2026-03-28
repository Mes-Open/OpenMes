<?php

namespace Modules\Packaging\Models;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingScanLog extends Model
{
    protected $table = 'packaging_scan_logs';

    protected $fillable = [
        'user_id',
        'work_order_id',
        'ean',
        'product_name',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
