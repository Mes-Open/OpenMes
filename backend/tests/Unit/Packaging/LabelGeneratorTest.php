<?php

namespace Tests\Unit\Packaging;

use App\Models\Batch;
use App\Models\LabelTemplate;
use App\Models\WorkOrder;
use App\Services\Packaging\LabelGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected LabelGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->generator = app(LabelGenerator::class);
    }

    private function workOrderTemplate(array $fields = []): LabelTemplate
    {
        return LabelTemplate::create([
            'name' => 'WO Test',
            'type' => LabelTemplate::TYPE_WORK_ORDER,
            'size' => '100x50',
            'barcode_format' => 'code128',
            'fields_config' => $fields ?: [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => false,
                'lot' => false,
                'prod_date' => false,
                'logo' => false,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    // ── Pure helpers ─────────────────────────────────────────────────────────

    public function test_barcode_png_returns_base64_data_uri(): void
    {
        $uri = $this->generator->barcodePng('TEST-123', 'code128');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $payload = substr($uri, strlen('data:image/png;base64,'));
        $this->assertNotFalse(base64_decode($payload, true));
    }

    public function test_qr_png_returns_data_uri(): void
    {
        $uri = $this->generator->qrPng('https://example.test/x/1');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_barcode_supports_multiple_formats(): void
    {
        $this->assertNotEmpty($this->generator->barcodePng('ABC123', 'code128'));
        $this->assertNotEmpty($this->generator->barcodePng('ABC123', 'code39'));
        $this->assertNotEmpty($this->generator->barcodePng('5901234123457', 'ean13'));
    }

    // ── ZPL for work orders ──────────────────────────────────────────────────

    public function test_zpl_for_work_order_contains_required_commands(): void
    {
        $template = $this->workOrderTemplate();
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-TEST-001']);

        $zpl = $this->generator->zplForWorkOrders(collect([$wo]), $template);

        $this->assertStringStartsWith('^XA', $zpl);
        $this->assertStringContainsString('^XZ', $zpl);
        $this->assertStringContainsString('^PW800', $zpl); // 100mm × 8 dots
        $this->assertStringContainsString('^LL400', $zpl); // 50mm × 8 dots
        $this->assertStringContainsString('WO-TEST-001', $zpl);
    }

    public function test_zpl_uses_correct_barcode_command_per_format(): void
    {
        $woAlpha = WorkOrder::factory()->create(['order_no' => 'WO-TEST-002']);

        $code128 = $this->generator->zplForWorkOrders(
            collect([$woAlpha]),
            $this->workOrderTemplate() // code128 default
        );
        $this->assertStringContainsString('^BCN', $code128);

        $tplCode39 = $this->workOrderTemplate();
        $tplCode39->update(['barcode_format' => 'code39']);
        $code39 = $this->generator->zplForWorkOrders(collect([$woAlpha]), $tplCode39->fresh());
        $this->assertStringContainsString('^B3N', $code39);

        // EAN-13 requires a numeric 12+1 string — use a valid sample EAN
        $woEan = WorkOrder::factory()->create(['order_no' => '5901234123457']);
        $tplEan = $this->workOrderTemplate();
        $tplEan->update(['barcode_format' => 'ean13']);
        $ean = $this->generator->zplForWorkOrders(collect([$woEan]), $tplEan->fresh());
        $this->assertStringContainsString('^BEN', $ean);
    }

    public function test_zpl_omits_fields_disabled_in_template(): void
    {
        $template = $this->workOrderTemplate([
            'wo_number' => true,
            'product' => false,
            'quantity' => false,
            'barcode' => false,
            'qr' => false,
            'lot' => false,
            'prod_date' => false,
            'logo' => false,
        ]);
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-MIN-001']);

        $zpl = $this->generator->zplForWorkOrders(collect([$wo]), $template);

        $this->assertStringContainsString('WO-MIN-001', $zpl);
        $this->assertStringNotContainsString('^BCN', $zpl);
        $this->assertStringNotContainsString('^BQN', $zpl);
        $this->assertStringNotContainsString('Qty:', $zpl);
    }

    public function test_zpl_emits_qr_command_when_enabled(): void
    {
        $template = $this->workOrderTemplate([
            'wo_number' => true,
            'product' => false,
            'quantity' => false,
            'barcode' => false,
            'qr' => true,
            'lot' => false,
            'prod_date' => false,
            'logo' => false,
        ]);
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-QR-001']);

        $zpl = $this->generator->zplForWorkOrders(collect([$wo]), $template);

        $this->assertStringContainsString('^BQN', $zpl);
        $this->assertStringContainsString('FDQA,', $zpl);
    }

    public function test_zpl_escape_neutralizes_zpl_control_chars(): void
    {
        $template = $this->workOrderTemplate();
        $wo = WorkOrder::factory()->create(['order_no' => 'WO^TEST~001']);

        $zpl = $this->generator->zplForWorkOrders(collect([$wo]), $template);

        // ^ and ~ removed (replaced with space), no raw injection of these chars
        // beyond the legitimate ZPL command prefixes
        $this->assertStringContainsString('WO TEST 001', $zpl);
    }

    public function test_zpl_multiple_work_orders_concatenates_labels(): void
    {
        $template = $this->workOrderTemplate();
        $wo1 = WorkOrder::factory()->create(['order_no' => 'WO-MULTI-1']);
        $wo2 = WorkOrder::factory()->create(['order_no' => 'WO-MULTI-2']);

        $zpl = $this->generator->zplForWorkOrders(collect([$wo1, $wo2]), $template);

        $this->assertEquals(2, substr_count($zpl, '^XA'));
        $this->assertEquals(2, substr_count($zpl, '^XZ'));
        $this->assertStringContainsString('WO-MULTI-1', $zpl);
        $this->assertStringContainsString('WO-MULTI-2', $zpl);
    }

    // ── Finished goods ───────────────────────────────────────────────────────

    public function test_zpl_for_finished_goods_uses_lot_number_for_barcode(): void
    {
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-FG-1']);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'lot_number' => 'LOT-2026-001',
        ]);

        $template = LabelTemplate::create([
            'name' => 'FG Test',
            'type' => LabelTemplate::TYPE_FINISHED_GOODS,
            'size' => '100x50',
            'barcode_format' => 'code128',
            'fields_config' => [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => true,
                'lot' => true,
                'prod_date' => true,
                'logo' => false,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        $zpl = $this->generator->zplForFinishedGoods(collect([$batch]), $template);

        $this->assertStringContainsString('LOT-2026-001', $zpl);
        $this->assertStringContainsString('^BCN', $zpl);
        $this->assertStringContainsString('^BQN', $zpl);
    }

    public function test_zpl_for_finished_goods_falls_back_to_wo_when_lot_missing(): void
    {
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-FG-2']);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'lot_number' => null,
            'batch_number' => 3,
        ]);

        $template = LabelTemplate::create([
            'name' => 'FG NoLot',
            'type' => LabelTemplate::TYPE_FINISHED_GOODS,
            'size' => '100x50',
            'barcode_format' => 'code128',
            'fields_config' => [
                'wo_number' => true, 'product' => true, 'quantity' => true,
                'barcode' => true, 'qr' => false, 'lot' => false,
                'prod_date' => false, 'logo' => false,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        $zpl = $this->generator->zplForFinishedGoods(collect([$batch]), $template);

        $this->assertStringContainsString('WO-FG-2-B3', $zpl);
    }

    // ── PDF smoke test ───────────────────────────────────────────────────────

    public function test_pdf_for_work_orders_returns_renderable_pdf(): void
    {
        $template = $this->workOrderTemplate();
        $wo = WorkOrder::factory()->create(['order_no' => 'WO-PDF-1']);

        $pdf = $this->generator->pdfForWorkOrders(collect([$wo]), $template);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }
}
