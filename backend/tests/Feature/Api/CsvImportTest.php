<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\TemplateStep;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Line $line;
    protected ProductType $productType;
    protected ProcessTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->line = Line::factory()->create(['code' => 'LINE-A']);

        $this->productType = ProductType::factory()->create(['code' => 'PROD-001']);

        $this->template = ProcessTemplate::factory()->create([
            'product_type_id' => $this->productType->id,
            'is_active' => true,
        ]);

        TemplateStep::factory()->create([
            'process_template_id' => $this->template->id,
            'step_number' => 1,
            'name' => 'Cut',
        ]);

        TemplateStep::factory()->create([
            'process_template_id' => $this->template->id,
            'step_number' => 2,
            'name' => 'Assemble',
        ]);
    }

    public function test_it_can_upload_csv_file()
    {
        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,100";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'upload_id',
                    'filename',
                    'headers',
                    'preview',
                    'total_rows',
                ],
            ]);

        $this->assertCount(4, $response->json('data.headers'));
        $this->assertEquals('orders.csv', $response->json('data.filename'));
        $this->assertEquals(1, $response->json('data.total_rows'));
    }

    public function test_it_validates_csv_file_format()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_it_can_execute_import_with_update_or_create_strategy()
    {
        Queue::fake();

        // Create CSV
        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,100";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        // Upload
        $uploadResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $uploadId = $uploadResponse->json('data.upload_id');

        // Execute import
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/execute', [
                'upload_id' => $uploadId,
                'mapping' => [
                    'import_strategy' => 'update_or_create',
                    'columns' => [
                        'order_no' => ['csv_column' => 'Order Number'],
                        'line_code' => ['csv_column' => 'Line'],
                        'product_type_code' => ['csv_column' => 'Product'],
                        'planned_qty' => ['csv_column' => 'Quantity'],
                    ],
                ],
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'status',
                    'total_rows',
                ],
            ]);

        $this->assertDatabaseHas('csv_imports', [
            'id' => $response->json('data.import_id'),
            'status' => 'PENDING',
        ]);

        Queue::assertPushed(\App\Jobs\ProcessCsvImport::class);
    }

    public function test_it_validates_required_column_mapping()
    {
        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,100";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $uploadResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $uploadId = $uploadResponse->json('data.upload_id');

        // Missing required field 'order_no'
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/execute', [
                'upload_id' => $uploadId,
                'mapping' => [
                    'import_strategy' => 'update_or_create',
                    'columns' => [
                        'line_code' => ['csv_column' => 'Line'],
                        'product_type_code' => ['csv_column' => 'Product'],
                        'planned_qty' => ['csv_column' => 'Quantity'],
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_it_can_save_mapping_template()
    {
        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,100";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $uploadResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $uploadId = $uploadResponse->json('data.upload_id');

        Queue::fake();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/execute', [
                'upload_id' => $uploadId,
                'mapping' => [
                    'import_strategy' => 'update_or_create',
                    'columns' => [
                        'order_no' => ['csv_column' => 'Order Number'],
                        'line_code' => ['csv_column' => 'Line'],
                        'product_type_code' => ['csv_column' => 'Product'],
                        'planned_qty' => ['csv_column' => 'Quantity'],
                    ],
                ],
                'save_mapping_template' => true,
                'mapping_template_name' => 'Standard Import',
            ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('csv_import_mappings', [
            'name' => 'Standard Import',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_import_with_update_or_create_updates_existing_work_order()
    {
        // Create existing work order
        $existing = WorkOrder::factory()->create([
            'order_no' => 'WO-001',
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'planned_qty' => 100,
            'status' => 'PENDING',
        ]);

        // Import CSV with updated quantity
        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,200";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $uploadResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $uploadId = $uploadResponse->json('data.upload_id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/execute', [
                'upload_id' => $uploadId,
                'mapping' => [
                    'import_strategy' => 'update_or_create',
                    'columns' => [
                        'order_no' => ['csv_column' => 'Order Number'],
                        'line_code' => ['csv_column' => 'Line'],
                        'product_type_code' => ['csv_column' => 'Product'],
                        'planned_qty' => ['csv_column' => 'Quantity'],
                    ],
                ],
            ]);

        // Process the job synchronously for testing
        $import = \App\Models\CsvImport::latest()->first();
        $uploadData = cache()->get("csv_upload_{$uploadId}");

        $job = new \App\Jobs\ProcessCsvImport(
            $import->id,
            $uploadData['file_path'],
            [
                'import_strategy' => 'update_or_create',
                'columns' => [
                    'order_no' => ['csv_column' => 'Order Number'],
                    'line_code' => ['csv_column' => 'Line'],
                    'product_type_code' => ['csv_column' => 'Product'],
                    'planned_qty' => ['csv_column' => 'Quantity'],
                ],
            ]
        );

        $job->handle(
            app(\App\Services\CsvImport\CsvParserService::class),
            app(\App\Services\CsvImport\WorkOrderImportService::class)
        );

        // Check that work order was updated, not duplicated
        $this->assertEquals(1, WorkOrder::where('order_no', 'WO-001')->count());

        $updated = WorkOrder::where('order_no', 'WO-001')->first();
        $this->assertEquals(200, $updated->planned_qty);
        $this->assertEquals($existing->id, $updated->id);
    }

    public function test_import_with_skip_existing_does_not_update()
    {
        // Create existing work order
        WorkOrder::factory()->create([
            'order_no' => 'WO-001',
            'line_id' => $this->line->id,
            'product_type_id' => $this->productType->id,
            'planned_qty' => 100,
        ]);

        $csv = "Order Number,Line,Product,Quantity\nWO-001,LINE-A,PROD-001,200";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $uploadResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/upload', ['file' => $file]);

        $uploadId = $uploadResponse->json('data.upload_id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/csv-imports/execute', [
                'upload_id' => $uploadId,
                'mapping' => [
                    'import_strategy' => 'skip_existing',
                    'columns' => [
                        'order_no' => ['csv_column' => 'Order Number'],
                        'line_code' => ['csv_column' => 'Line'],
                        'product_type_code' => ['csv_column' => 'Product'],
                        'planned_qty' => ['csv_column' => 'Quantity'],
                    ],
                ],
            ]);

        $import = \App\Models\CsvImport::latest()->first();
        $uploadData = cache()->get("csv_upload_{$uploadId}");

        $job = new \App\Jobs\ProcessCsvImport(
            $import->id,
            $uploadData['file_path'],
            [
                'import_strategy' => 'skip_existing',
                'columns' => [
                    'order_no' => ['csv_column' => 'Order Number'],
                    'line_code' => ['csv_column' => 'Line'],
                    'product_type_code' => ['csv_column' => 'Product'],
                    'planned_qty' => ['csv_column' => 'Quantity'],
                ],
            ]
        );

        $job->handle(
            app(\App\Services\CsvImport\CsvParserService::class),
            app(\App\Services\CsvImport\WorkOrderImportService::class)
        );

        // Check that work order was NOT updated
        $workOrder = WorkOrder::where('order_no', 'WO-001')->first();
        $this->assertEquals(100, $workOrder->planned_qty);
    }
}
