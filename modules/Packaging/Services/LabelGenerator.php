<?php

namespace Modules\Packaging\Services;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Modules\Packaging\Models\LabelTemplate;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelGenerator
{
    public function pdfForWorkOrders(Collection $workOrders, LabelTemplate $template)
    {
        $labels = $workOrders->map(fn (WorkOrder $wo) => $this->labelDataForWorkOrder($wo, $template))->all();

        return $this->renderPdf('packaging::pdf.labels.work-order', $labels, $template);
    }

    public function pdfForFinishedGoods(Collection $batches, LabelTemplate $template)
    {
        $labels = $batches->map(fn (Batch $batch) => $this->labelDataForFinishedGoods($batch, $template))->all();

        return $this->renderPdf('packaging::pdf.labels.finished-goods', $labels, $template);
    }

    public function pdfForBatchSteps(Collection $steps, LabelTemplate $template)
    {
        $labels = $steps->map(fn (BatchStep $step) => $this->labelDataForBatchStep($step, $template))->all();

        return $this->renderPdf('packaging::pdf.labels.workstation-step', $labels, $template);
    }

    public function zplForWorkOrders(Collection $workOrders, LabelTemplate $template): string
    {
        return $workOrders
            ->map(fn (WorkOrder $wo) => $this->zplLabel($this->labelDataForWorkOrder($wo, $template), $template))
            ->implode("\n");
    }

    public function zplForFinishedGoods(Collection $batches, LabelTemplate $template): string
    {
        return $batches
            ->map(fn (Batch $batch) => $this->zplLabel($this->labelDataForFinishedGoods($batch, $template), $template))
            ->implode("\n");
    }

    public function zplForBatchSteps(Collection $steps, LabelTemplate $template): string
    {
        return $steps
            ->map(fn (BatchStep $step) => $this->zplLabel($this->labelDataForBatchStep($step, $template), $template))
            ->implode("\n");
    }

    private function labelDataForWorkOrder(WorkOrder $wo, LabelTemplate $template): array
    {
        $wo->loadMissing('productType', 'line');
        $barcodeValue = $wo->order_no;
        $qrValue = url("/admin/work-orders/{$wo->id}");

        return [
            'fields' => [
                'wo_number' => $wo->order_no,
                'product' => $wo->productType?->name ?? '—',
                'quantity' => $this->formatQty($wo->planned_qty).' '.($wo->productType?->unit ?? 'pcs'),
                'lot' => null,
                'prod_date' => $wo->created_at?->format('Y-m-d'),
            ],
            'barcode_value' => $barcodeValue,
            'qr_value' => $qrValue,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($barcodeValue, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qrValue) : null,
        ];
    }

    private function labelDataForFinishedGoods(Batch $batch, LabelTemplate $template): array
    {
        $batch->loadMissing('workOrder.productType', 'workOrder.line');
        $wo = $batch->workOrder;
        $barcodeValue = $batch->lot_number ?: $wo->order_no.'-B'.$batch->batch_number;
        $qrValue = url("/admin/batches/{$batch->id}/report");

        return [
            'fields' => [
                'wo_number' => $wo->order_no,
                'product' => $wo->productType?->name ?? '—',
                'quantity' => $this->formatQty($batch->produced_qty).' '.($wo->productType?->unit ?? 'pcs'),
                'lot' => $batch->lot_number ?: '—',
                'prod_date' => ($batch->completed_at ?? $batch->released_at)?->format('Y-m-d'),
            ],
            'barcode_value' => $barcodeValue,
            'qr_value' => $qrValue,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($barcodeValue, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qrValue) : null,
        ];
    }

    private function labelDataForBatchStep(BatchStep $step, LabelTemplate $template): array
    {
        $step->loadMissing('batch.workOrder.productType', 'workstation');
        $batch = $step->batch;
        $wo = $batch->workOrder;
        $barcodeValue = $wo->order_no.'-B'.$batch->batch_number.'-S'.$step->step_number;
        $qrValue = url("/admin/work-orders/{$wo->id}");

        return [
            'fields' => [
                'wo_number' => $wo->order_no,
                'product' => $wo->productType?->name ?? '—',
                'quantity' => $this->formatQty($batch->target_qty).' '.($wo->productType?->unit ?? 'pcs'),
                'lot' => 'Step '.$step->step_number.': '.$step->name,
                'prod_date' => optional($step->workstation)->name,
            ],
            'barcode_value' => $barcodeValue,
            'qr_value' => $qrValue,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($barcodeValue, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qrValue) : null,
        ];
    }

    private function renderPdf(string $view, array $labels, LabelTemplate $template)
    {
        $widthMm = $template->widthMm();
        $heightMm = $template->heightMm();

        return Pdf::loadView($view, [
            'labels' => $labels,
            'template' => $template,
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
        ])->setPaper([0, 0, $this->mmToPt($widthMm), $this->mmToPt($heightMm)]);
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 2.834645669;
    }

    public function barcodePng(string $value, string $format): string
    {
        $type = match ($format) {
            'code39' => BarcodeGeneratorPNG::TYPE_CODE_39,
            'ean13' => BarcodeGeneratorPNG::TYPE_EAN_13,
            default => BarcodeGeneratorPNG::TYPE_CODE_128,
        };

        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($value, $type, 2, 60);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function qrPng(string $value, int $size = 240): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($value)
            ->size($size)
            ->margin(0)
            ->build();

        return $result->getDataUri();
    }

    private function formatQty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function zplLabel(array $data, LabelTemplate $template): string
    {
        $widthDots = $template->widthMm() * 8;
        $heightDots = $template->heightMm() * 8;
        $fields = $data['fields'];

        $zpl = "^XA\n";
        $zpl .= "^PW{$widthDots}\n";
        $zpl .= "^LL{$heightDots}\n";
        $zpl .= "^LH0,0\n";
        $zpl .= "^CI28\n";

        $y = 10;
        $lineHeight = 30;

        if ($template->hasField('wo_number') && ! empty($fields['wo_number'])) {
            $zpl .= "^FO20,{$y}^A0N,28,28^FD".$this->zplEscape($fields['wo_number'])."^FS\n";
            $y += $lineHeight;
        }
        if ($template->hasField('product') && ! empty($fields['product'])) {
            $zpl .= "^FO20,{$y}^A0N,22,22^FD".$this->zplEscape($fields['product'])."^FS\n";
            $y += $lineHeight;
        }
        if ($template->hasField('quantity') && ! empty($fields['quantity'])) {
            $zpl .= "^FO20,{$y}^A0N,22,22^FDQty: ".$this->zplEscape($fields['quantity'])."^FS\n";
            $y += $lineHeight;
        }
        if ($template->hasField('lot') && ! empty($fields['lot'])) {
            $zpl .= "^FO20,{$y}^A0N,20,20^FDLOT: ".$this->zplEscape($fields['lot'])."^FS\n";
            $y += $lineHeight;
        }
        if ($template->hasField('prod_date') && ! empty($fields['prod_date'])) {
            $zpl .= "^FO20,{$y}^A0N,20,20^FD".$this->zplEscape($fields['prod_date'])."^FS\n";
            $y += $lineHeight;
        }

        if ($template->hasField('barcode') && ! empty($data['barcode_value'])) {
            $barcodeY = max($y + 10, $heightDots - 110);
            $cmd = match ($template->barcode_format) {
                'code39' => '^B3N,N,60,Y,N',
                'ean13' => '^BEN,60,Y,N',
                default => '^BCN,60,Y,N,N',
            };
            $zpl .= "^FO20,{$barcodeY}{$cmd}^FD".$this->zplEscape($data['barcode_value'])."^FS\n";
        }

        if ($template->hasField('qr') && ! empty($data['qr_value'])) {
            $qrX = max(20, $widthDots - 130);
            $zpl .= "^FO{$qrX},10^BQN,2,4^FDQA,".$this->zplEscape($data['qr_value'])."^FS\n";
        }

        $zpl .= "^XZ\n";

        return $zpl;
    }

    private function zplEscape(string $text): string
    {
        return str_replace(['^', '~', '\\'], [' ', ' ', '/'], $text);
    }
}
