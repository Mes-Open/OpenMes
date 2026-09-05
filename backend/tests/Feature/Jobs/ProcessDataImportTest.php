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

        try {
            ProcessDataImport::dispatchSync($import->id);
            $this->fail('expected the job to rethrow');
        } catch (\RuntimeException) {
        }

        $import->refresh();
        $this->assertSame(CsvImport::STATUS_FAILED, $import->status);
        $this->assertSame(0, $import->error_log[0]['row']);
        $this->assertStringNotContainsString('martians', $import->error_log[0]['message'], 'internal detail stays in the log');
        Storage::disk('local')->assertMissing($import->file_path);
    }
}
