<?php

namespace Modules\Packaging\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Packaging\Models\LabelTemplate;
use Modules\Packaging\Services\LabelGenerator;

class LabelPrintController extends Controller
{
    public function __construct(private LabelGenerator $generator) {}

    public function workOrderPdf(Request $request, WorkOrder $workOrder)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORK_ORDER);
        $pdf = $this->generator->pdfForWorkOrders(collect([$workOrder]), $template);

        return $pdf->stream("label-wo-{$workOrder->order_no}.pdf");
    }

    public function workOrderZpl(Request $request, WorkOrder $workOrder)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORK_ORDER);
        $zpl = $this->generator->zplForWorkOrders(collect([$workOrder]), $template);

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => "attachment; filename=label-wo-{$workOrder->order_no}.zpl",
        ]);
    }

    public function finishedGoodsPdf(Request $request, Batch $batch)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_FINISHED_GOODS);
        $pdf = $this->generator->pdfForFinishedGoods(collect([$batch]), $template);
        $label = $batch->lot_number ?: 'batch-'.$batch->id;

        return $pdf->stream("label-fg-{$label}.pdf");
    }

    public function finishedGoodsZpl(Request $request, Batch $batch)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_FINISHED_GOODS);
        $zpl = $this->generator->zplForFinishedGoods(collect([$batch]), $template);
        $label = $batch->lot_number ?: 'batch-'.$batch->id;

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => "attachment; filename=label-fg-{$label}.zpl",
        ]);
    }

    public function batchStepPdf(Request $request, BatchStep $batchStep)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORKSTATION_STEP);
        $pdf = $this->generator->pdfForBatchSteps(collect([$batchStep]), $template);

        return $pdf->stream("label-step-{$batchStep->id}.pdf");
    }

    public function batchStepZpl(Request $request, BatchStep $batchStep)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORKSTATION_STEP);
        $zpl = $this->generator->zplForBatchSteps(collect([$batchStep]), $template);

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => "attachment; filename=label-step-{$batchStep->id}.zpl",
        ]);
    }

    public function printMultiple(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(LabelTemplate::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'zpl'])],
            'template_id' => 'nullable|integer|exists:label_templates,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $template = $validated['template_id']
            ? LabelTemplate::findOrFail($validated['template_id'])
            : LabelTemplate::defaultFor($validated['type']);

        abort_unless($template, 404, 'No label template configured for this type.');

        return match ($validated['type']) {
            LabelTemplate::TYPE_WORK_ORDER => $this->multiWorkOrders($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_FINISHED_GOODS => $this->multiFinishedGoods($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_WORKSTATION_STEP => $this->multiBatchSteps($validated['ids'], $template, $validated['format']),
        };
    }

    private function multiWorkOrders(array $ids, LabelTemplate $template, string $format)
    {
        $workOrders = WorkOrder::whereIn('id', $ids)->get();
        $filename = 'labels-work-orders-'.date('Ymd-His');

        if ($format === 'zpl') {
            return response($this->generator->zplForWorkOrders($workOrders, $template), 200, [
                'Content-Type' => 'application/zpl',
                'Content-Disposition' => "attachment; filename={$filename}.zpl",
            ]);
        }

        return $this->generator->pdfForWorkOrders($workOrders, $template)->stream("{$filename}.pdf");
    }

    private function multiFinishedGoods(array $ids, LabelTemplate $template, string $format)
    {
        $batches = Batch::whereIn('id', $ids)->get();
        $filename = 'labels-finished-goods-'.date('Ymd-His');

        if ($format === 'zpl') {
            return response($this->generator->zplForFinishedGoods($batches, $template), 200, [
                'Content-Type' => 'application/zpl',
                'Content-Disposition' => "attachment; filename={$filename}.zpl",
            ]);
        }

        return $this->generator->pdfForFinishedGoods($batches, $template)->stream("{$filename}.pdf");
    }

    private function multiBatchSteps(array $ids, LabelTemplate $template, string $format)
    {
        $steps = BatchStep::whereIn('id', $ids)->get();
        $filename = 'labels-steps-'.date('Ymd-His');

        if ($format === 'zpl') {
            return response($this->generator->zplForBatchSteps($steps, $template), 200, [
                'Content-Type' => 'application/zpl',
                'Content-Disposition' => "attachment; filename={$filename}.zpl",
            ]);
        }

        return $this->generator->pdfForBatchSteps($steps, $template)->stream("{$filename}.pdf");
    }

    private function resolveTemplate(Request $request, string $type): LabelTemplate
    {
        if ($id = $request->integer('template')) {
            $template = LabelTemplate::find($id);
            if ($template && $template->type === $type) {
                return $template;
            }
        }

        $template = LabelTemplate::defaultFor($type);
        abort_unless($template, 404, "No label template configured for type {$type}. Configure one in Packaging → Label Templates.");

        return $template;
    }
}
