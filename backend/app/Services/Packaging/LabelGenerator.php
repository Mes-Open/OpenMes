<?php

namespace App\Services\Packaging;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\LabelTemplate;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelGenerator
{
    public function pdfForWorkOrders(Collection $workOrders, LabelTemplate $template)
    {
        $labels = $workOrders->map(fn (WorkOrder $wo) => $this->labelDataForWorkOrder($wo, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.work-order', $labels, $template);
    }

    public function pdfForFinishedGoods(Collection $batches, LabelTemplate $template)
    {
        $labels = $batches->map(fn (Batch $batch) => $this->labelDataForFinishedGoods($batch, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.finished-goods', $labels, $template);
    }

    public function pdfForBatchSteps(Collection $steps, LabelTemplate $template)
    {
        $labels = $steps->map(fn (BatchStep $step) => $this->labelDataForBatchStep($step, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.workstation-step', $labels, $template);
    }

    public function pdfForPallets(Collection $pallets, LabelTemplate $template)
    {
        $labels = $pallets->map(fn (Pallet $pallet) => $this->labelDataForPallet($pallet, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.pallet', $labels, $template);
    }

    public function pdfForSerialUnits(Collection $units, LabelTemplate $template)
    {
        $labels = $units->map(fn (SerialUnit $unit) => $this->labelDataForSerialUnit($unit, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.serial-unit', $labels, $template);
    }

    /** The IQC label of received material lots: the lot, its material and the inspection verdict. */
    public function pdfForMaterialLots(Collection $lots, LabelTemplate $template)
    {
        $labels = $lots->map(fn (MaterialLot $lot) => $this->labelDataForMaterialLot($lot, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.material-lot', $labels, $template);
    }

    public function zplForMaterialLots(Collection $lots, LabelTemplate $template): string
    {
        return $lots
            ->map(fn (MaterialLot $lot) => $this->zplLabel($this->labelDataForMaterialLot($lot, $template), $template))
            ->implode("\n");
    }

    /**
     * The lot number is what the barcode and QR carry - the next scan (issue to
     * production, a component bound into a unit) finds the lot by it. The
     * status line is the incoming-inspection verdict the label exists for.
     */
    private function labelDataForMaterialLot(MaterialLot $lot, LabelTemplate $template): array
    {
        $lot->loadMissing('material', 'inspection');
        $status = __(match ($lot->status) {
            MaterialLot::STATUS_RELEASED => 'IQC: released',
            MaterialLot::STATUS_QUARANTINE => 'IQC: quarantine',
            MaterialLot::STATUS_REJECTED => 'IQC: rejected',
            default => 'IQC: :status',
        }, ['status' => $lot->status]);
        $inspected = $lot->released_at ?? $lot->inspection?->updated_at;

        return [
            'fields' => [
                'lot' => $lot->lot_number,
                'material' => $lot->material ? trim($lot->material->code.' · '.$lot->material->name) : null,
                'quantity' => $this->formatQty($lot->quantity_received).' '.($lot->unit_of_measure ?? $lot->material?->unit_of_measure ?? ''),
                'status' => $inspected ? $status.' · '.$inspected->format('Y-m-d') : $status,
                'supplier_lot' => $lot->supplier_lot_no ? __('Supplier lot: :lot', ['lot' => $lot->supplier_lot_no]) : null,
                'prod_date' => $lot->received_at?->format('Y-m-d'),
            ],
            'barcode_value' => $lot->lot_number,
            'qr_value' => $lot->lot_number,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($lot->lot_number, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($lot->lot_number) : null,
        ];
    }

    public function pdfForCartons(Collection $cartons, LabelTemplate $template)
    {
        $labels = $cartons->map(fn (UnitCarton $carton) => $this->labelDataForCarton($carton, $template))->all();

        return $this->renderPdf('packaging.pdf.labels.carton', $labels, $template);
    }

    /**
     * The template on sample data, for checking a layout before anything real
     * exists to print. Every field the template can show gets a plausible
     * value, so what is ticked is what appears.
     */
    public function pdfPreview(LabelTemplate $template)
    {
        [$view, $label] = $this->sampleLabel($template);

        return $this->renderPdf($view, [$label], $template);
    }

    /** @return array{0: string, 1: array<string, mixed>} the view and one label's data */
    private function sampleLabel(LabelTemplate $template): array
    {
        $today = now()->format('Y-m-d');
        [$view, $fields, $code, $qr, $units] = match ($template->type) {
            LabelTemplate::TYPE_SERIAL_UNIT => ['packaging.pdf.labels.serial-unit',
                ['serial_no' => 'SN-000001', 'psn' => 'PSN-000001', 'wo_number' => 'WO-000001', 'product' => __('Sample product'), 'quantity' => null, 'lot' => null, 'prod_date' => $today],
                'SN-000001', 'SN-000001', []],
            LabelTemplate::TYPE_CARTON => ['packaging.pdf.labels.carton',
                ['carton_no' => 'CTN-000001', 'wo_number' => 'WO-000001', 'product' => __('Sample product'), 'quantity' => __(':count pcs', ['count' => 6]), 'lot' => null, 'location' => 'PAL-000001', 'prod_date' => $today],
                'CTN-000001', 'CTN-000001', ['SN-000001', 'SN-000002', 'SN-000003', 'SN-000004', 'SN-000005', 'SN-000006']],
            LabelTemplate::TYPE_MATERIAL_LOT => ['packaging.pdf.labels.material-lot',
                ['lot' => 'LOT-000001', 'material' => 'MAT-001 · '.__('Sample material'), 'quantity' => '500 pcs', 'status' => __('IQC: released').' · '.$today, 'supplier_lot' => __('Supplier lot: :lot', ['lot' => 'SUP-4711']), 'prod_date' => $today],
                'LOT-000001', 'LOT-000001', []],
            LabelTemplate::TYPE_PALLET => ['packaging.pdf.labels.pallet',
                ['pallet_no' => 'PAL-000001', 'wo_number' => 'WO-000001', 'product' => __('Sample product'), 'quantity' => '48 pcs', 'location' => 'DOCK-1', 'lot' => 'LOT-000001', 'prod_date' => $today],
                'PAL-000034', 'PAL-000034', []],
            LabelTemplate::TYPE_FINISHED_GOODS => ['packaging.pdf.labels.finished-goods',
                ['wo_number' => 'WO-2026-0142', 'product' => __('Sample product'), 'quantity' => '48 pcs', 'lot' => 'LOT-2026-091', 'prod_date' => $today],
                'LOT-2026-091', 'LOT-2026-091', []],
            LabelTemplate::TYPE_WORKSTATION_STEP => ['packaging.pdf.labels.workstation-step',
                ['wo_number' => 'WO-2026-0142', 'product' => __('Sample product'), 'quantity' => '48 pcs', 'lot' => 'Step 2: '.__('Assembly'), 'prod_date' => __('Workstation').' 1'],
                'WO-2026-0142-B1-S2', url('/admin/work-orders'), []],
            default => ['packaging.pdf.labels.work-order',
                ['wo_number' => 'WO-2026-0142', 'product' => __('Sample product'), 'quantity' => '48 pcs', 'lot' => null, 'prod_date' => $today],
                'WO-2026-0142', url('/admin/work-orders'), []],
        };

        return [$view, [
            'fields' => $fields,
            'units' => $units,
            'barcode_value' => $code,
            'qr_value' => $qr,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($code, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qr) : null,
        ]];
    }

    /**
     * The outer-box label: the carton number as barcode/QR, and the serial of
     * every unit inside - what a warehouse or a customer reads to know which
     * exact units are in the box without opening it.
     */
    private function labelDataForCarton(UnitCarton $carton, LabelTemplate $template): array
    {
        $carton->loadMissing('workOrder.productType', 'pallet:id,pallet_no');
        $units = $carton->units()->orderBy('serial_no')->get(['serial_no', 'psn'])->map(fn ($u) => $u->serial_no ?? $u->psn)->all();
        $wo = $carton->workOrder;

        return [
            'fields' => [
                'carton_no' => $carton->carton_no,
                'wo_number' => $wo?->order_no,
                'product' => $wo?->productType?->name,
                'quantity' => __(':count pcs', ['count' => count($units)]),
                'lot' => null,
                'location' => $carton->pallet?->pallet_no,
                'prod_date' => ($carton->closed_at ?? $carton->created_at)?->format('Y-m-d'),
            ],
            'units' => $units,
            'barcode_value' => $carton->carton_no,
            'qr_value' => $carton->carton_no,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($carton->carton_no, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($carton->carton_no) : null,
        ];
    }

    public function zplForPallets(Collection $pallets, LabelTemplate $template): string
    {
        return $pallets
            ->map(fn (Pallet $pallet) => $this->zplLabel($this->labelDataForPallet($pallet, $template), $template))
            ->implode("\n");
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

    public function zplForSerialUnits(Collection $units, LabelTemplate $template): string
    {
        return $units
            ->map(fn (SerialUnit $unit) => $this->zplLabel($this->labelDataForSerialUnit($unit, $template), $template))
            ->implode("\n");
    }

    /**
     * Per-unit carton label: the serial number is the primary value (barcode +
     * QR); the process serial number is also printed so the unit can be
     * re-found at packing by process-serial scan.
     */
    private function labelDataForSerialUnit(SerialUnit $unit, LabelTemplate $template): array
    {
        $unit->loadMissing('workOrder.productType');
        $wo = $unit->workOrder;
        // Both codes carry the unit's number itself: the label is read by the
        // next station's scanner, not by a phone - a URL there would be noise.
        // A unit still without its SN is labelled with its PSN.
        $barcodeValue = $unit->serial_no ?? $unit->psn;
        $qrValue = $barcodeValue;

        return [
            'fields' => [
                'serial_no' => $barcodeValue,
                'psn' => $unit->serial_no !== null ? $unit->psn : null,
                'wo_number' => $wo?->order_no,
                'product' => $wo?->productType?->name,
                'quantity' => null,
                'lot' => null,
                'prod_date' => ($unit->produced_at ?? $unit->created_at)?->format('Y-m-d'),
            ],
            'barcode_value' => $barcodeValue,
            'qr_value' => $qrValue,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($barcodeValue, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qrValue) : null,
        ];
    }

    private function labelDataForWorkOrder(WorkOrder $wo, LabelTemplate $template): array
    {
        $wo->loadMissing('productType', 'line');
        $barcodeValue = $wo->order_no;
        $qrValue = url("/admin/work-orders/{$wo->id}");

        return [
            'fields' => [
                'wo_number' => $wo->order_no,
                'product' => $wo->productType?->name, // null hides the line (no stray "-")
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
                'product' => $wo->productType?->name, // null hides the line (no stray "-")
                'quantity' => $this->formatQty($batch->produced_qty).' '.($wo->productType?->unit ?? 'pcs'),
                'lot' => $batch->lot_number ?: null,
                'prod_date' => ($batch->completed_at ?? $batch->released_at)?->format('Y-m-d'),
            ],
            'barcode_value' => $barcodeValue,
            'qr_value' => $qrValue,
            'barcode_png' => $template->hasField('barcode') ? $this->barcodePng($barcodeValue, $template->barcode_format) : null,
            'qr_png' => $template->hasField('qr') ? $this->qrPng($qrValue) : null,
        ];
    }

    private function labelDataForPallet(Pallet $pallet, LabelTemplate $template): array
    {
        $pallet->loadMissing('workOrder.productType', 'batch');
        $wo = $pallet->workOrder;
        // Both the 1D barcode and the QR encode the pallet number, so scanning the
        // pallet anywhere resolves straight back to it.
        $barcodeValue = $pallet->pallet_no;
        $qrValue = $pallet->pallet_no;

        return [
            'fields' => [
                'pallet_no' => $pallet->pallet_no,
                'wo_number' => $wo?->order_no,
                'product' => $wo?->productType?->name, // null hides the line (no stray "-")
                'quantity' => $this->formatQty($pallet->qty).' '.($wo?->productType?->unit ?? 'pcs'),
                'location' => $pallet->location,
                'lot' => $pallet->batch?->lot_number ?: null,
                'prod_date' => $pallet->created_at?->format('Y-m-d'),
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
                'product' => $wo->productType?->name ?? '-',
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

        $labels = array_map(function (array $label) use ($template, $widthMm): array {
            $label['has_qr'] = $template->hasField('qr') && ! empty($label['qr_png']);
            $label['has_barcode'] = $template->hasField('barcode') && ! empty($label['barcode_png']);
            // Account for the label's 3mm padding on both sides (6mm) plus the
            // 20mm QR cell + gap, so the QR column never spills past the page edge.
            $label['content_width'] = $label['has_qr'] ? $widthMm - 28 : $widthMm - 6;

            return $label;
        }, $labels);

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

    /**
     * The 1D barcode as a data URI, or null when the value cannot be encoded.
     * EAN-13 only takes 12-13 digits and CODE 39 a limited character set;
     * an order number or a serial that does not fit falls back to CODE 128
     * rather than failing the whole print.
     */
    public function barcodePng(string $value, string $format): ?string
    {
        $type = match (true) {
            $format === 'ean13' && preg_match('/^\d{12,13}$/', $value) === 1 => BarcodeGeneratorPNG::TYPE_EAN_13,
            $format === 'code39' && preg_match('/^[0-9A-Z\-. $\/+%]+$/', $value) === 1 => BarcodeGeneratorPNG::TYPE_CODE_39,
            default => BarcodeGeneratorPNG::TYPE_CODE_128,
        };

        try {
            $png = (new BarcodeGeneratorPNG)->getBarcode($value, $type, 2, 60);
        } catch (\Throwable) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function qrPng(string $value, int $size = 240): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
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

        if ($template->hasField('pallet_no') && ! empty($fields['pallet_no'])) {
            $zpl .= "^FO20,{$y}^A0N,32,32^FD".$this->zplEscape($fields['pallet_no'])."^FS\n";
            $y += $lineHeight + 4;
        }
        if ($template->hasField('serial_no') && ! empty($fields['serial_no'])) {
            $zpl .= "^FO20,{$y}^A0N,32,32^FD".$this->zplEscape($fields['serial_no'])."^FS\n";
            $y += $lineHeight + 4;
        }
        if ($template->hasField('psn') && ! empty($fields['psn'])) {
            $zpl .= "^FO20,{$y}^A0N,20,20^FDPSN: ".$this->zplEscape($fields['psn'])."^FS\n";
            $y += $lineHeight;
        }
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
        foreach (['material' => 22, 'status' => 22, 'supplier_lot' => 20] as $key => $size) {
            if ($template->hasField($key) && ! empty($fields[$key])) {
                $zpl .= "^FO20,{$y}^A0N,{$size},{$size}^FD".$this->zplEscape($fields[$key])."^FS\n";
                $y += $lineHeight;
            }
        }
        if ($template->hasField('lot') && ! empty($fields['lot'])) {
            $zpl .= "^FO20,{$y}^A0N,20,20^FDLOT: ".$this->zplEscape($fields['lot'])."^FS\n";
            $y += $lineHeight;
        }
        if ($template->hasField('location') && ! empty($fields['location'])) {
            $zpl .= "^FO20,{$y}^A0N,20,20^FDLOC: ".$this->zplEscape($fields['location'])."^FS\n";
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
