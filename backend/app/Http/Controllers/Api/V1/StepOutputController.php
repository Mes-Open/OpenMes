<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStepOutputValue;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Read API for operator-recorded typed step outputs (#B), so an external system
 * (ERP, reporting) can pull the values + pictures a shop floor recorded against a
 * work order. Read-only; gated by the work-order view policy.
 */
class StepOutputController extends Controller
{
    /** All recorded output values for a work order, grouped by batch step. */
    public function forWorkOrder(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        $values = BatchStepOutputValue::query()
            ->whereHas('batchStep.batch', fn ($q) => $q->where('work_order_id', $workOrder->id))
            ->with(['output:id,key,label,value_type,unit', 'recordedBy:id,name', 'batchStep:id,step_number,name'])
            ->orderBy('batch_step_id')
            ->get()
            ->map(fn (BatchStepOutputValue $v) => [
                'id' => $v->id,
                'step_number' => $v->batchStep?->step_number,
                'batch_step_id' => $v->batch_step_id,
                'key' => $v->output?->key,
                'label' => $v->output?->label,
                'value_type' => $v->output?->value_type,
                'unit' => $v->output?->unit,
                'value' => $v->typedValue(),
                'file_url' => $v->file_path
                    ? route('api.v1.batch-step-outputs.file', $v)
                    : null,
                'recorded_by' => $v->recordedBy?->name,
                'recorded_at' => $v->recorded_at?->toISOString(),
            ]);

        return response()->json([
            'data' => $values,
            'meta' => ['work_order_id' => $workOrder->id],
        ]);
    }

    /** Serve a recorded output picture (safe inline mime + nosniff). */
    public function file(BatchStepOutputValue $batchStepOutputValue)
    {
        $batchStepOutputValue->loadMissing('batchStep.batch.workOrder');
        $workOrder = $batchStepOutputValue->batchStep?->batch?->workOrder;
        abort_unless($workOrder !== null, 404);
        $this->authorize('view', $workOrder);

        abort_unless($batchStepOutputValue->file_path && Storage::exists($batchStepOutputValue->file_path), 404);

        $inlineSafe = ['image/png', 'image/jpeg', 'image/webp'];
        $mime = $batchStepOutputValue->mime_type ?? 'application/octet-stream';
        $disposition = in_array($mime, $inlineSafe, true) ? 'inline' : 'attachment';

        return response()->file(Storage::path($batchStepOutputValue->file_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($batchStepOutputValue->original_name ?? 'output').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
