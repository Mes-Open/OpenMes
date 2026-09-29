<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Models\Pallet;
use App\Services\Packaging\LabelGenerator;
use App\Support\PalletPackingList;
use Barryvdh\DomPDF\Facade\Pdf;

/** The packing list that travels with a pallet: what is in every carton, by serial number. */
class PackingListController extends Controller
{
    public function __construct(private readonly LabelGenerator $labels) {}

    public function pallet(Pallet $pallet)
    {
        $list = PalletPackingList::data($pallet);

        return Pdf::loadView('packaging.pdf.packing-list', [
            'list' => $list,
            'qrPng' => $this->labels->qrPng($pallet->pallet_no, 200),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4')->stream('packing-list-'.\App\Support\DownloadName::safe($pallet->pallet_no).'.pdf');
    }
}
