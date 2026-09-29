<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintMultipleLabelsRequest;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\LabelTemplate;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\WorkOrder;
use App\Services\Packaging\LabelGenerator;
use App\Support\DownloadName;
use Illuminate\Http\Request;

class LabelPrintController extends Controller
{
    public function __construct(private LabelGenerator $generator) {}

    public function workOrderPdf(Request $request, WorkOrder $workOrder)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORK_ORDER);
        $pdf = $this->generator->pdfForWorkOrders(collect([$workOrder]), $template);

        return $pdf->stream('label-wo-'.DownloadName::safe($workOrder->order_no).'.pdf');
    }

    public function workOrderZpl(Request $request, WorkOrder $workOrder)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_WORK_ORDER);
        $zpl = $this->generator->zplForWorkOrders(collect([$workOrder]), $template);

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => 'attachment; filename=label-wo-'.DownloadName::safe($workOrder->order_no).'.zpl',
        ]);
    }

    public function finishedGoodsPdf(Request $request, Batch $batch)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_FINISHED_GOODS);
        $pdf = $this->generator->pdfForFinishedGoods(collect([$batch]), $template);
        $label = DownloadName::safe($batch->lot_number ?: 'batch-'.$batch->id);

        return $pdf->stream("label-fg-{$label}.pdf");
    }

    public function finishedGoodsZpl(Request $request, Batch $batch)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_FINISHED_GOODS);
        $zpl = $this->generator->zplForFinishedGoods(collect([$batch]), $template);
        $label = DownloadName::safe($batch->lot_number ?: 'batch-'.$batch->id);

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

    public function palletPdf(Request $request, Pallet $pallet)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_PALLET);
        $pdf = $this->generator->pdfForPallets(collect([$pallet]), $template);

        return $pdf->stream('label-pallet-'.DownloadName::safe($pallet->pallet_no).'.pdf');
    }

    public function cartonPdf(Request $request, UnitCarton $carton)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_CARTON);
        $pdf = $this->generator->pdfForCartons(collect([$carton]), $template);

        return $pdf->stream('label-carton-'.DownloadName::safe($carton->carton_no).'.pdf');
    }

    public function palletZpl(Request $request, Pallet $pallet)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_PALLET);
        $zpl = $this->generator->zplForPallets(collect([$pallet]), $template);

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => 'attachment; filename=label-pallet-'.DownloadName::safe($pallet->pallet_no).'.zpl',
        ]);
    }

    public function serialUnitPdf(Request $request, SerialUnit $serialUnit)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_SERIAL_UNIT);
        $pdf = $this->generator->pdfForSerialUnits(collect([$serialUnit]), $template);

        return $pdf->stream('label-unit-'.$this->unitFileName($serialUnit).'.pdf');
    }

    /** A set of unit labels in one file - the batch just issued ahead of production. */
    public function serialUnitsPdf(Request $request)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_SERIAL_UNIT);

        return $this->generator->pdfForSerialUnits($this->unitsFromIds($request), $template)->stream('labels-serial-units-'.date('Ymd-His').'.pdf');
    }

    public function serialUnitsZpl(Request $request)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_SERIAL_UNIT);

        return response($this->generator->zplForSerialUnits($this->unitsFromIds($request), $template), 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => 'attachment; filename=labels-serial-units-'.date('Ymd-His').'.zpl',
        ]);
    }

    /** `ids=1,2,3` (or ids[]=…), at most 500, kept in the order given. */
    private function unitsFromIds(Request $request)
    {
        $ids = collect(is_array($request->query('ids')) ? $request->query('ids') : explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim((string) $v))->filter(fn ($v) => $v > 0)->unique()->take(500)->values();
        abort_if($ids->isEmpty(), 422, __('No units given.'));
        $units = SerialUnit::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $units->get($id))->filter()->values();
    }

    public function serialUnitZpl(Request $request, SerialUnit $serialUnit)
    {
        $template = $this->resolveTemplate($request, LabelTemplate::TYPE_SERIAL_UNIT);
        $zpl = $this->generator->zplForSerialUnits(collect([$serialUnit]), $template);

        return response($zpl, 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => 'attachment; filename=label-unit-'.$this->unitFileName($serialUnit).'.zpl',
        ]);
    }

    public function materialLotPdf(Request $request, MaterialLot $materialLot)
    {
        $template = $this->materialLotTemplate($request);

        return $this->generator->pdfForMaterialLots(collect([$materialLot]), $template)->stream('label-iqc-'.DownloadName::safe($materialLot->lot_number).'.pdf');
    }

    public function materialLotZpl(Request $request, MaterialLot $materialLot)
    {
        $template = $this->materialLotTemplate($request);

        return response($this->generator->zplForMaterialLots(collect([$materialLot]), $template), 200, [
            'Content-Type' => 'application/zpl',
            'Content-Disposition' => 'attachment; filename=label-iqc-'.DownloadName::safe($materialLot->lot_number).'.zpl',
        ]);
    }

    /**
     * The plant's IQC template, else the built-in layout - an install that
     * predates the label type prints without first configuring one.
     */
    private function materialLotTemplate(Request $request): LabelTemplate
    {
        $chosen = $request->integer('template') ? LabelTemplate::find($request->integer('template')) : null;
        if ($chosen && $chosen->type === LabelTemplate::TYPE_MATERIAL_LOT) {
            return $chosen;
        }

        return LabelTemplate::defaultFor(LabelTemplate::TYPE_MATERIAL_LOT) ?? new LabelTemplate([
            'name' => 'Material lot (IQC)',
            'type' => LabelTemplate::TYPE_MATERIAL_LOT,
            'size' => '100x50',
            'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_MATERIAL_LOT),
            'barcode_format' => 'code128',
            'is_active' => true,
        ]);
    }

    public function printMultiple(PrintMultipleLabelsRequest $request)
    {
        $validated = $request->validated();

        $template = $validated['template_id']
            ? LabelTemplate::findOrFail($validated['template_id'])
            : LabelTemplate::defaultFor($validated['type']);

        abort_unless($template, 404, __('No label template configured for this type.'));

        return match ($validated['type']) {
            LabelTemplate::TYPE_WORK_ORDER => $this->multiWorkOrders($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_FINISHED_GOODS => $this->multiFinishedGoods($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_WORKSTATION_STEP => $this->multiBatchSteps($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_PALLET => $this->multiPallets($validated['ids'], $template, $validated['format']),
            LabelTemplate::TYPE_SERIAL_UNIT => $this->multiSerialUnits($validated['ids'], $template, $validated['format']),
        };
    }

    private function multiPallets(array $ids, LabelTemplate $template, string $format)
    {
        $pallets = Pallet::whereIn('id', $ids)->get();
        $filename = 'labels-pallets-'.date('Ymd-His');

        if ($format === 'zpl') {
            return response($this->generator->zplForPallets($pallets, $template), 200, [
                'Content-Type' => 'application/zpl',
                'Content-Disposition' => "attachment; filename={$filename}.zpl",
            ]);
        }

        return $this->generator->pdfForPallets($pallets, $template)->stream("{$filename}.pdf");
    }

    private function multiSerialUnits(array $ids, LabelTemplate $template, string $format)
    {
        $units = SerialUnit::whereIn('id', $ids)->get();
        $filename = 'labels-serial-units-'.date('Ymd-His');

        if ($format === 'zpl') {
            return response($this->generator->zplForSerialUnits($units, $template), 200, [
                'Content-Type' => 'application/zpl',
                'Content-Disposition' => "attachment; filename={$filename}.zpl",
            ]);
        }

        return $this->generator->pdfForSerialUnits($units, $template)->stream("{$filename}.pdf");
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

    private function unitFileName(SerialUnit $unit): string
    {
        return DownloadName::safe($unit->display_id, 'unit');
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
        abort_unless($template, 404, __('No label template configured for type :type. Configure one in Packaging → Label Templates.', ['type' => $type]));

        return $template;
    }
}
