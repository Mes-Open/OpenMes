<?php

namespace Database\Seeders;

use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Database\Seeder;

/**
 * Finished-goods opening stock for the four completed batches (3001–3004).
 *
 * The raw-material receipts already create the material side of the ledger. This
 * seeder adds the product side through a real product-receipt document, so the
 * stock ledger proves finished goods are not a hand-written table: the same
 * document service that posts receipts writes the balances.
 *
 * Idempotent by checking for any product stock in the finished-goods warehouse;
 * a second run is a no-op. NewTeaDemoSeeder already truncates the table, so a
 * reset always takes the fresh path.
 */
class FinishedGoodsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $finished = Warehouse::where('code', 'NT-WH-FG')->first();
        $admin = User::where('username', 'admin')->first();

        if (! $finished) {
            $this->command?->warn('Finished-goods warehouse missing; skip product stock.');

            return;
        }

        if (WarehouseStock::where('warehouse_id', $finished->id)->whereNotNull('product_type_id')->exists()) {
            $this->command?->info('Finished-goods stock already present; skip.');

            return;
        }

        $quantities = [
            'NT-FG-JASMINE-COLD' => 120,
            'NT-FG-DONGFANG-LEMON' => 1_500,
            'NT-FG-OOLONG-CREAM' => 1_200,
            'NT-FG-MANGO-SAGO' => 800,
        ];

        $products = ProductType::whereIn('code', array_keys($quantities))->get()->keyBy('code');

        if ($products->count() !== count($quantities)) {
            $this->command?->warn('Finished-goods products missing; skip product stock.');

            return;
        }

        $lines = collect($quantities)->map(function (int $quantity, string $code) use ($products) {
            $product = $products->get($code);

            return [
                'product_type_id' => $product->id,
                'quantity' => $quantity,
                'unit_of_measure' => $product->unit_of_measure,
            ];
        })->values()->all();

        $document = app(StockDocumentService::class)->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'warehouse_id' => $finished->id,
            'notes' => '新茶饮演示成品入库：3001–3004 已完成批次，留作待发货与库存台账成品维度。',
            'lines' => $lines,
        ], $admin);

        app(StockDocumentService::class)->post($document, $admin);

        $this->command?->info('Finished-goods stock seeded: '.$document->document_no.' / '.count($lines).' products');
    }
}
