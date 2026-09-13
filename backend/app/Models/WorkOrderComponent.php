<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One frozen BOM occurrence. A generated child's output is dedicated to its parent. */
class WorkOrderComponent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['specification' => 'array', 'stock_qty' => 'decimal:4', 'required_qty' => 'decimal:4', 'planned_qty' => 'decimal:4'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ComponentStockReservation::class);
    }

    public function parentWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'parent_work_order_id');
    }

    public function childWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'child_work_order_id');
    }

    public function goodQuantity(): float
    {
        $child = $this->childWorkOrder;
        if (! $child || $child->isBlocked() || in_array($child->status, [WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_REJECTED], true)) {
            return 0;
        }

        // Only completed batches without outstanding blocking quality controls
        // supply assembly. A machine counter alone is not released component output.
        $quantity = (float) $child->batches()->where('status', Batch::STATUS_DONE)->get()
            ->reject(fn (Batch $batch) => QualityControlTask::hasOpenBlockingForBatch($batch->id)
                || $batch->qualityChecks()->orderByDesc('checked_at')->orderByDesc('id')->first()?->all_passed === false
                || $batch->outputLots()->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED])->exists())
            ->sum(fn (Batch $batch) => max(0, (float) $batch->produced_qty - self::batchScrapQuantity($batch)));

        return max(0, $quantity - (float) $child->scrapEntries()->whereNull('batch_step_id')->sum('quantity'));
    }

    public static function batchScrapQuantity(Batch $batch): float
    {
        $reported = (float) ScrapEntry::whereHas('batchStep', fn ($q) => $q->where('batch_id', $batch->id))->sum('quantity');

        return max((float) $batch->scrap_qty, $reported);
    }

    public function scrapQuantity(): float
    {
        $child = $this->childWorkOrder;

        return $child ? $child->batches->sum(fn (Batch $batch) => self::batchScrapQuantity($batch))
            + (float) $child->scrapEntries()->whereNull('batch_step_id')->sum('quantity') : 0;
    }
}
