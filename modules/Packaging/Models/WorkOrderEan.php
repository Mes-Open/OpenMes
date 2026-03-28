<?php

namespace Modules\Packaging\Models;

use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderEan extends Model
{
    protected $table = 'work_order_eans';

    protected $fillable = ['work_order_id', 'ean'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
