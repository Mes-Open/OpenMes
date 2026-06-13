<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderEan extends Model
{
    use SoftDeletesWithAudit;

    protected $table = 'work_order_eans';

    protected $fillable = ['work_order_id', 'ean'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
