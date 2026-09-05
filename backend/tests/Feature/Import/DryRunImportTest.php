<?php

namespace Tests\Feature\Import;

use App\Events\CollectionChanged;
use App\Jobs\ProcessDataImport;
use App\Models\CsvImport;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Validation-only import runs (#dry-run): the file is read, mapped and fed to
 * the entity importer exactly as a real run, then rolled back. What the run
 * reports must match what a real run would do; what it leaves behind must be
 * nothing.
 */
class DryRunImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // The test database pre-seeds roles, so never plain create().
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('Admin', 'web'));
    }

    /** A run over one new and one existing product type, dry or wet. */
    private function runImport(bool $dryRun): CsvImport
    {
        $import = $this->makeImport($dryRun);

        app()->call([new ProcessDataImport($import->id), 'handle']);

        return $import->fresh();
    }

    /** The same run, queued but not executed. */
    private function makeImport(bool $dryRun): CsvImport
    {
        $csv = "code,name,category\r\nNEW-A,New A,FG\r\nOLD-B,Renamed B,FG\r\n";
        $path = 'imports/'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        $import = CsvImport::create([
            'user_id' => $this->admin->id,
            'entity' => 'product_types',
            'filename' => basename($path),
            'original_filename' => 'products.csv',
            'file_path' => $path,
            'import_strategy' => 'update_or_create',
            'dry_run' => $dryRun,
            'options' => [
                'token' => str_repeat('t', 32),
                'mapping' => ['code' => 'code', 'name' => 'name', 'category' => 'category'],
                'delimiter' => 'comma',
                'encoding' => 'utf-8',
                'options' => ['strategy' => 'update_or_create'],
            ],
            'status' => CsvImport::STATUS_PENDING,
        ]);

        return $import;
    }

    public function test_dry_run_reports_what_a_real_run_would_do(): void
    {
        ProductType::factory()->create(['code' => 'OLD-B', 'name' => 'Old name']);

        $import = $this->runImport(dryRun: true);

        $this->assertSame(CsvImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(1, $import->created_rows);
        $this->assertSame(1, $import->updated_rows);
        $this->assertSame(0, $import->failed_rows);
    }

    public function test_dry_run_writes_nothing(): void
    {
        ProductType::factory()->create(['code' => 'OLD-B', 'name' => 'Old name']);

        $this->runImport(dryRun: true);

        $this->assertDatabaseMissing('product_types', ['code' => 'NEW-A']);
        $this->assertSame('Old name', ProductType::where('code', 'OLD-B')->first()->name);
    }

    public function test_a_real_run_over_the_same_file_does_write(): void
    {
        ProductType::factory()->create(['code' => 'OLD-B', 'name' => 'Old name']);

        $import = $this->runImport(dryRun: false);

        $this->assertSame(1, $import->created_rows);
        $this->assertSame(1, $import->updated_rows);
        $this->assertDatabaseHas('product_types', ['code' => 'NEW-A']);
        $this->assertSame('Renamed B', ProductType::where('code', 'OLD-B')->first()->name);
    }

    public function test_dry_run_broadcasts_no_row_deltas(): void
    {
        Event::fake([CollectionChanged::class]);

        $this->runImport(dryRun: true);

        // The rows exist for the length of the transaction; broadcasting them
        // would push rows to every open browser that no delta ever takes back.
        Event::assertNotDispatched(
            CollectionChanged::class,
            fn (CollectionChanged $e) => $e->collection === 'product_types',
        );

        // The run's own progress must still stream, or the bar never moves.
        Event::assertDispatched(
            CollectionChanged::class,
            fn (CollectionChanged $e) => $e->collection === 'data_imports',
        );
    }

    public function test_a_real_run_still_broadcasts(): void
    {
        Event::fake([CollectionChanged::class]);

        $this->runImport(dryRun: false);

        Event::assertDispatched(
            CollectionChanged::class,
            fn (CollectionChanged $e) => $e->collection === 'product_types',
        );
    }

    public function test_dry_run_reports_row_errors_without_writing_the_good_rows(): void
    {
        $csv = "code,name,category\r\nGOOD-A,Good,FG\r\n,No code,FG\r\n";
        $path = 'imports/'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        $import = CsvImport::create([
            'user_id' => $this->admin->id,
            'entity' => 'product_types',
            'filename' => basename($path),
            'original_filename' => 'products.csv',
            'file_path' => $path,
            'import_strategy' => 'update_or_create',
            'dry_run' => true,
            'options' => [
                'mapping' => ['code' => 'code', 'name' => 'name', 'category' => 'category'],
                'delimiter' => 'comma',
                'encoding' => 'utf-8',
                'options' => ['strategy' => 'update_or_create'],
            ],
            'status' => CsvImport::STATUS_PENDING,
        ])->fresh();

        app()->call([new ProcessDataImport($import->id), 'handle']);
        $import->refresh();

        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(1, $import->created_rows);
        $this->assertDatabaseMissing('product_types', ['code' => 'GOOD-A']);
        $this->assertNotEmpty($import->error_log);
    }

    public function test_the_show_page_is_told_the_run_was_validation_only(): void
    {
        $import = $this->runImport(dryRun: true);

        // Without dry_run on the payload the page cannot label the run, and the
        // counters read as writes that never happened.
        $this->actingAs($this->admin)
            ->get('/admin/import/runs/'.$import->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('import.dry_run', true));
    }

    public function test_a_validation_keeps_its_file_so_the_real_import_can_follow(): void
    {
        $import = $this->runImport(dryRun: true);

        // Deleting here would force a re-upload and a re-map to act on what the
        // validation just reported.
        $this->assertTrue(
            Storage::disk('local')->exists($import->file_path),
            'a validation must not delete the upload it validated'
        );
    }

    public function test_a_real_run_still_deletes_its_file(): void
    {
        $import = $this->runImport(dryRun: false);

        $this->assertFalse(Storage::disk('local')->exists($import->file_path));
    }

    public function test_the_show_page_offers_the_real_import_after_a_validation(): void
    {
        $import = $this->runImport(dryRun: true);

        $this->actingAs($this->admin)
            ->get('/admin/import/runs/'.$import->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('import.token', $import->options['token']));
    }

    public function test_one_row_failing_at_the_database_does_not_fail_the_rows_after_it(): void
    {
        // SQLite ignores varchar length and has no aborted-transaction state, so
        // the cascade this guards against cannot be reproduced there. The
        // driver-independent half of the guard is
        // test_every_row_runs_in_its_own_transaction.
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('needs PostgreSQL: only it aborts the transaction on a failed statement');
        }

        // A code past the column's 50 chars fails in the driver, not in
        // validation — the case the dry run exists to surface. On PostgreSQL
        // that aborts the surrounding transaction, so without a savepoint per
        // row every later row is reported as failed too.
        $csv = "code,name,category\r\n".str_repeat('X', 80).",Too Long,FG\r\nFINE-1,Fine One,FG\r\nFINE-2,Fine Two,FG\r\n";
        $path = 'imports/'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        $import = CsvImport::create([
            'user_id' => $this->admin->id,
            'entity' => 'product_types',
            'filename' => basename($path),
            'original_filename' => 'products.csv',
            'file_path' => $path,
            'import_strategy' => 'update_or_create',
            'dry_run' => true,
            'options' => [
                'mapping' => ['code' => 'code', 'name' => 'name', 'category' => 'category'],
                'delimiter' => 'comma',
                'encoding' => 'utf-8',
                'options' => ['strategy' => 'update_or_create'],
            ],
            'status' => CsvImport::STATUS_PENDING,
        ]);

        app()->call([new ProcessDataImport($import->id), 'handle']);
        $import->refresh();

        $this->assertSame(1, $import->failed_rows, 'only the oversized row may fail');
        $this->assertSame(2, $import->created_rows, 'the rows after it still report what they would do');
    }

    public function test_every_row_runs_in_its_own_transaction(): void
    {
        // The database-agnostic half of the guard above: whatever the driver
        // does with a failed statement, each row must be isolated.
        $levels = [];

        (new class
        {
            use \App\Services\Erp\Concerns\ReportsImportRows;

            public function run(array $rows, callable $handler): array
            {
                return $this->processRows($rows, $handler);
            }
        })->run([['a'], ['b']], function () use (&$levels) {
            $levels[] = \Illuminate\Support\Facades\DB::transactionLevel();

            return ['status' => 'success', 'action' => 'created'];
        });

        $ambient = \Illuminate\Support\Facades\DB::transactionLevel();

        $this->assertSame(
            [$ambient + 1, $ambient + 1],
            $levels,
            'each row opens its own transaction, and it closes before the next'
        );
    }

    public function test_a_second_worker_cannot_run_the_same_import_twice(): void
    {
        ProductType::factory()->create(['code' => 'OLD-B', 'name' => 'Old name']);

        $import = $this->runImport(dryRun: false);

        $this->assertSame(1, $import->created_rows);

        // What the database queue does once retry_after elapses: hand the same
        // job to another worker. The claim, not the queue's timing, is what
        // makes that harmless — so no retry_after tuning is needed.
        app()->call([new ProcessDataImport($import->id), 'handle']);
        $import->refresh();

        $this->assertSame(1, $import->created_rows, 'counters must not be applied twice');
        $this->assertSame(1, ProductType::where('code', 'NEW-A')->count(), 'the row is written once');
    }

    public function test_a_run_already_claimed_by_another_worker_is_left_alone(): void
    {
        $import = $this->makeImport(dryRun: false);
        $import->update(['status' => CsvImport::STATUS_PROCESSING]);

        app()->call([new ProcessDataImport($import->id), 'handle']);

        $this->assertSame(CsvImport::STATUS_PROCESSING, $import->fresh()->status, 'not touched');
        $this->assertDatabaseMissing('product_types', ['code' => 'NEW-A']);
    }

    public function test_process_endpoint_records_the_flag_and_defaults_to_a_real_run(): void
    {
        foreach ([true, false] as $dryRun) {
            $token = str_repeat($dryRun ? 'a' : 'b', 32);
            $path = 'imports/'.$token.'.csv';
            Storage::disk('local')->put($path, "code,name\r\nX,Y\r\n");

            $this->actingAs($this->admin)
                ->withSession(['import.uploads.'.$token => [
                    'entity' => 'product_types',
                    'file_path' => $path,
                    'original_filename' => 'x.csv',
                    'file_options' => ['delimiter' => 'comma', 'encoding' => 'utf-8'],
                    'options' => ['strategy' => 'update_or_create'],
                ]])
                ->post('/admin/import/product-types/process', [
                    'token' => $token,
                    'mapping' => ['code' => 'code', 'name' => 'name'],
                ] + ($dryRun ? ['dry_run' => true] : []))
                ->assertRedirect();

            $this->assertSame($dryRun, CsvImport::latest('id')->first()->dry_run);
        }
    }
}
