<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessDataImport;
use App\Models\CsvImport;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function queued(string $csv, string $entity, array $mapping, array $options = [], array $attrs = []): CsvImport
    {
        $path = 'imports/imp_'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        return CsvImport::factory()->create(array_merge([
            'entity' => $entity,
            'file_path' => $path,
            'options' => ['mapping' => $mapping, 'delimiter' => 'auto', 'encoding' => 'utf-8', 'options' => $options],
        ], $attrs));
    }

    public function test_runs_a_product_type_file_and_records_counts_errors_and_progress(): void
    {
        ProductType::factory()->create(['code' => 'OLD', 'name' => 'Old']);

        $import = $this->queued(
            "Kod;Nazwa;Aktywny\nOLD;Renamed;tak\nNEW;Fresh;nie\n;no code;\nBAD;Bad flag;maybe\n",
            'product_types',
            ['Kod' => 'code', 'Nazwa' => 'name', 'Aktywny' => 'is_active'],
            ['strategy' => 'update_or_create'],
        );

        (new ProcessDataImport($import->id))->handle(
            app(\App\Services\Import\SpreadsheetReader::class),
            app(\App\Services\Import\RowMapper::class),
            app(\App\Import\ImportRegistry::class),
            app(\App\Support\TenantContext::class),
        );

        $import->refresh();
        $this->assertSame(CsvImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(4, $import->total_rows);
        $this->assertSame(4, $import->processed_rows);
        $this->assertSame(1, $import->created_rows);
        $this->assertSame(1, $import->updated_rows);
        $this->assertSame(2, $import->failed_rows);
        $this->assertSame(2, $import->successful_rows);
        $this->assertSame(100, $import->progress());
        $this->assertSame([4, 5], array_column($import->error_log, 'row'), 'errors point at file lines');
        $this->assertSame('is_active', $import->error_log[1]['field']);
        $this->assertDatabaseHas('product_types', ['code' => 'NEW', 'is_active' => false]);
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function test_stamps_created_rows_with_the_imports_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'T1']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $import = $this->queued("code\nTEN-1\n", 'product_types', ['code' => 'code'], [], [
            'tenant_id' => $tenant->id, 'user_id' => $user->id,
        ]);

        ProcessDataImport::dispatchSync($import->id);

        $this->assertDatabaseHas('product_types', ['code' => 'TEN-1', 'tenant_id' => $tenant->id]);
        $this->assertNull(app(\App\Support\TenantContext::class)->id(), 'context is cleared afterwards');
    }

    public function test_is_a_no_op_once_the_run_left_pending(): void
    {
        $import = $this->queued("code\nX\n", 'product_types', ['code' => 'code'], [], ['status' => CsvImport::STATUS_COMPLETED]);

        ProcessDataImport::dispatchSync($import->id);

        $this->assertDatabaseMissing('product_types', ['code' => 'X']);
    }

    public function test_unknown_entity_marks_the_run_failed_and_removes_the_file(): void
    {
        $import = $this->queued("code\nX\n", 'martians', ['code' => 'code']);

        // The job deliberately does not rethrow — it records the failure on the
        // row instead. (The previous try/catch here asserted the opposite and
        // could never fail: PHPUnit's AssertionFailedError extends
        // RuntimeException, so fail() was caught by its own catch block.)
        ProcessDataImport::dispatchSync($import->id);

        $import->refresh();
        $this->assertSame(CsvImport::STATUS_FAILED, $import->status);
        $this->assertSame(0, $import->error_log[0]['row']);
        $this->assertStringNotContainsString('martians', $import->error_log[0]['message'], 'internal detail stays in the log');
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function test_bom_mapping_error_cannot_partially_replace_a_recipe(): void
    {
        $product = ProductType::factory()->create(['code' => 'SOFA']);
        $template = \App\Models\ProcessTemplate::factory()->create(['product_type_id' => $product->id]);
        $material = \App\Models\Material::factory()->create(['code' => 'FOAM']);
        $item = \App\Models\BomItem::factory()->create(['process_template_id' => $template->id, 'material_id' => $material->id, 'quantity_per_unit' => 4]);
        $run = $this->queued("product,material,qty\nSOFA,FOAM,99\nSOFA,FOAM,not-a-number\n", 'boms', ['product' => 'product_type_code', 'material' => 'material_code', 'qty' => 'quantity_per_unit']);
        ProcessDataImport::dispatchSync($run->id);
        $this->assertEquals(1, $run->fresh()->failed_rows);
        $this->assertEquals(0, $run->fresh()->updated_rows);
        $this->assertEquals(4, $item->fresh()->quantity_per_unit);
    }

    public function test_component_import_dry_run_and_real_run_report_generated_jobs(): void
    {
        $product = ProductType::factory()->create(['code' => 'SOFA']);
        $root = \App\Models\ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $product->id]);
        $part = ProductType::factory()->create();
        \App\Models\ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $part->id]);
        \App\Models\BomItem::create(['process_template_id' => $root->id, 'product_type_id' => $part->id, 'quantity_per_unit' => 4]);
        foreach ([true, false] as $dryRun) {
            $run = $this->queued("order,product,qty\nSOFA-IMPORT,SOFA,2\n", 'work_orders', ['order' => 'order_no', 'product' => 'product_type_code', 'qty' => 'quantity'], ['generate_components' => true], ['dry_run' => $dryRun]);
            ProcessDataImport::dispatchSync($run->id);
            $this->assertEquals(0, $run->fresh()->failed_rows);
            $this->assertEquals(1, $run->fresh()->options['generated_component_jobs']);
            $this->assertDatabaseCount('work_orders', $dryRun ? 0 : 2);
        }
    }
}
