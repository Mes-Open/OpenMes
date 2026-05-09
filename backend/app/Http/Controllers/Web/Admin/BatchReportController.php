<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Barryvdh\DomPDF\Facade\Pdf;

class BatchReportController extends Controller
{
    public function show(Batch $batch)
    {
        $data = $this->gatherReportData($batch);

        return view('admin.reports.batch-report', $data);
    }

    public function pdf(Batch $batch)
    {
        $data = $this->gatherReportData($batch);

        $pdf = Pdf::loadView('admin.reports.batch-report-pdf', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'report-'.($batch->lot_number ?? 'batch-'.$batch->id).'.pdf';

        return $pdf->download($filename);
    }

    private function gatherReportData(Batch $batch): array
    {
        $batch->load([
            'workOrder.line',
            'workOrder.productType',
            'workstation',
            'steps.startedBy',
            'steps.completedBy',
            'processConfirmations.confirmedBy',
            'qualityChecks.samples',
            'qualityChecks.checkedBy',
            'packagingChecklist',
            'releasedBy',
        ]);

        $workOrder = $batch->workOrder;
        $snapshot = $workOrder->process_snapshot ?? [];
        $bom = $snapshot['bom'] ?? [];

        $bomWithTotals = array_map(function ($item) use ($batch) {
            $baseQty = $item['quantity_per_unit'] * (float) $batch->produced_qty;
            $scrapQty = $baseQty * ($item['scrap_percentage'] / 100);

            return array_merge($item, [
                'total_qty' => round($baseQty + $scrapQty, 2),
            ]);
        }, $bom);

        return [
            'batch' => $batch,
            'workOrder' => $workOrder,
            'bom' => $bomWithTotals,
            'steps' => $batch->steps,
            'confirmations' => $batch->processConfirmations,
            'qualityChecks' => $batch->qualityChecks,
            'checklist' => $batch->packagingChecklist,
        ];
    }
}
