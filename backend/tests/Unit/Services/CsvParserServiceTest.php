<?php

namespace Tests\Unit\Services;

use App\Services\CsvImport\CsvParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvParserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CsvParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CsvParserService::class);
        Storage::fake('local');
    }

    private function writeTempCsv(string $content): string
    {
        $path = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        file_put_contents($path, $content);
        return $path;
    }

    protected function tearDown(): void
    {
        // Clean up any temp files created during tests
        foreach (glob(sys_get_temp_dir() . '/test_*.csv') as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // ── parse() ──────────────────────────────────────────────────────────────

    public function test_parse_returns_headers_preview_and_total_rows(): void
    {
        $csv = "OrderNo,Quantity,Line\nWO-001,100,LINE-A\nWO-002,200,LINE-B";
        $path = $this->writeTempCsv($csv);

        $result = $this->service->parse($path);

        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('preview', $result);
        $this->assertArrayHasKey('total_rows', $result);
    }

    public function test_parse_extracts_correct_headers(): void
    {
        $csv = "OrderNo,Quantity,Line\nWO-001,100,LINE-A";
        $path = $this->writeTempCsv($csv);

        $result = $this->service->parse($path);

        $this->assertEquals(['OrderNo', 'Quantity', 'Line'], $result['headers']);
    }

    public function test_parse_counts_total_rows_correctly(): void
    {
        $rows = implode("\n", array_map(fn($i) => "WO-00{$i},{$i}00,LINE-A", range(1, 7)));
        $csv = "OrderNo,Quantity,Line\n{$rows}";
        $path = $this->writeTempCsv($csv);

        $result = $this->service->parse($path);

        $this->assertEquals(7, $result['total_rows']);
    }

    public function test_parse_limits_preview_rows(): void
    {
        $rows = implode("\n", array_map(fn($i) => "WO-00{$i},{$i}00,LINE-A", range(1, 10)));
        $csv = "OrderNo,Quantity,Line\n{$rows}";
        $path = $this->writeTempCsv($csv);

        $result = $this->service->parse($path, previewRows: 3);

        $this->assertCount(3, $result['preview']);
    }

    public function test_parse_handles_single_row_csv(): void
    {
        $csv = "OrderNo,Quantity\nWO-001,50";
        $path = $this->writeTempCsv($csv);

        $result = $this->service->parse($path);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertCount(1, $result['preview']);
    }

    // ── validateMapping() ────────────────────────────────────────────────────

    public function test_validate_mapping_passes_when_all_required_fields_mapped(): void
    {
        $headers = ['OrderNo', 'Line', 'Product', 'Qty'];
        $mapping = [
            'order_no'          => ['csv_column' => 'OrderNo'],
            'line_code'         => ['csv_column' => 'Line'],
            'product_type_code' => ['csv_column' => 'Product'],
            'planned_qty'       => ['csv_column' => 'Qty'],
        ];

        $errors = $this->service->validateMapping($headers, $mapping);

        $this->assertEmpty($errors);
    }

    public function test_validate_mapping_fails_when_required_field_missing(): void
    {
        $headers = ['Line', 'Product', 'Qty'];
        $mapping = [
            'line_code'         => ['csv_column' => 'Line'],
            'product_type_code' => ['csv_column' => 'Product'],
            'planned_qty'       => ['csv_column' => 'Qty'],
            // 'order_no' missing
        ];

        $errors = $this->service->validateMapping($headers, $mapping);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('order_no', $errors[0]);
    }

    public function test_validate_mapping_fails_when_mapped_column_not_in_headers(): void
    {
        $headers = ['OrderNo', 'Line', 'Product', 'Qty'];
        $mapping = [
            'order_no'          => ['csv_column' => 'NonExistentColumn'],
            'line_code'         => ['csv_column' => 'Line'],
            'product_type_code' => ['csv_column' => 'Product'],
            'planned_qty'       => ['csv_column' => 'Qty'],
        ];

        $errors = $this->service->validateMapping($headers, $mapping);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('NonExistentColumn', $errors[0]);
    }

    public function test_validate_mapping_returns_multiple_errors(): void
    {
        $headers = ['X'];
        $mapping = [];

        $errors = $this->service->validateMapping($headers, $mapping);

        // All 4 required fields missing
        $this->assertCount(4, $errors);
    }

    // ── parseWithMapping() ───────────────────────────────────────────────────

    public function test_parse_with_mapping_maps_columns_correctly(): void
    {
        $csv = "OrderNo,Qty\nWO-001,100\nWO-002,200";
        $path = $this->writeTempCsv($csv);
        $mapping = [
            'order_no'   => ['csv_column' => 'OrderNo'],
            'planned_qty' => ['csv_column' => 'Qty'],
        ];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertCount(2, $result);
        $this->assertEquals('WO-001', $result[0]['order_no']);
        $this->assertEquals('100', $result[0]['planned_qty']);
        $this->assertEquals('WO-002', $result[1]['order_no']);
    }

    public function test_parse_with_mapping_includes_row_number(): void
    {
        $csv = "OrderNo,Qty\nWO-001,100";
        $path = $this->writeTempCsv($csv);
        $mapping = ['order_no' => ['csv_column' => 'OrderNo']];

        $result = $this->service->parseWithMapping($path, $mapping);

        // CSV index starts at 1 (header at offset 0), so first data row index=1, row_number = 1+2 = 3
        $this->assertEquals(3, $result[0]['row_number']);
    }

    public function test_parse_with_mapping_uses_default_when_column_missing(): void
    {
        $csv = "OrderNo\nWO-001";
        $path = $this->writeTempCsv($csv);
        $mapping = [
            'order_no'   => ['csv_column' => 'OrderNo'],
            'priority'   => ['csv_column' => 'Priority', 'default' => 3],
        ];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertEquals(3, $result[0]['priority']);
    }

    public function test_parse_with_mapping_trims_whitespace_by_default(): void
    {
        $csv = "OrderNo\n  WO-001  ";
        $path = $this->writeTempCsv($csv);
        $mapping = ['order_no' => ['csv_column' => 'OrderNo']];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertEquals('WO-001', $result[0]['order_no']);
    }

    public function test_parse_with_mapping_applies_integer_transform(): void
    {
        $csv = "Qty\n42";
        $path = $this->writeTempCsv($csv);
        $mapping = ['planned_qty' => ['csv_column' => 'Qty', 'transform' => 'integer']];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertSame(42, $result[0]['planned_qty']);
    }

    public function test_parse_with_mapping_applies_uppercase_transform(): void
    {
        $csv = "Code\nline-a";
        $path = $this->writeTempCsv($csv);
        $mapping = ['line_code' => ['csv_column' => 'Code', 'transform' => 'uppercase']];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertEquals('LINE-A', $result[0]['line_code']);
    }

    public function test_parse_with_mapping_applies_lowercase_transform(): void
    {
        $csv = "Code\nLINE-A";
        $path = $this->writeTempCsv($csv);
        $mapping = ['line_code' => ['csv_column' => 'Code', 'transform' => 'lowercase']];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertEquals('line-a', $result[0]['line_code']);
    }

    public function test_parse_with_mapping_applies_float_transform(): void
    {
        $csv = "Qty\n3.14";
        $path = $this->writeTempCsv($csv);
        $mapping = ['planned_qty' => ['csv_column' => 'Qty', 'transform' => 'float']];

        $result = $this->service->parseWithMapping($path, $mapping);

        $this->assertSame(3.14, $result[0]['planned_qty']);
    }

    // ── storeTemporary() / cleanupTemporary() ────────────────────────────────

    public function test_store_temporary_returns_path(): void
    {
        $file = UploadedFile::fake()->createWithContent('orders.csv', "A,B\n1,2");

        $path = $this->service->storeTemporary($file);

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }

    public function test_cleanup_temporary_removes_file(): void
    {
        $tmpPath = sys_get_temp_dir() . '/test_cleanup_' . uniqid() . '.csv';
        file_put_contents($tmpPath, 'test');

        $this->service->cleanupTemporary($tmpPath);

        $this->assertFileDoesNotExist($tmpPath);
    }

    public function test_cleanup_temporary_does_not_throw_on_missing_file(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->cleanupTemporary('/nonexistent/path/file.csv');
    }
}
