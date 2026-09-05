<?php

namespace Tests\Feature\Web\Admin;

use App\Jobs\ProcessDataImport;
use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The unified importer screens (Admin → Import, and the supervisor mount).
 */
class DataImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function csv(string $content = "code,name\nP-1,Widget\nP-2,Gadget\n", string $name = 'items.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_and_operator_forbidden(): void
    {
        $this->get('/admin/import')->assertRedirect();
        $this->actingAs($this->operator)->get('/admin/import')->assertForbidden();
        $this->actingAs($this->operator)->get('/admin/import/samples/materials')->assertForbidden();
    }

    public function test_admin_opens_every_entity_and_unknown_slugs_are_404(): void
    {
        foreach (['product-types' => 'product_types', 'materials' => 'materials', 'work-orders' => 'work_orders', 'boms' => 'boms'] as $slug => $key) {
            $this->actingAs($this->admin)->get("/admin/import/{$slug}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/import/Index')
                    ->where('entity.key', $key)
                    ->where('basePath', '/admin/import')
                    ->has('entity.fields')
                    ->has('entities', 4)
                    ->has('limits.delimiters')
                );
        }

        $this->actingAs($this->admin)->get('/admin/import')
            ->assertInertia(fn (Assert $page) => $page->where('entity.key', 'product_types'));

        $this->actingAs($this->admin)->get('/admin/import/customers')->assertNotFound();
    }

    public function test_supervisor_mount_offers_work_orders_only(): void
    {
        $this->actingAs($this->supervisor)->get('/supervisor/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entity.key', 'work_orders')
                ->where('basePath', '/supervisor/import')
                ->has('entities', 1)
            );

        $this->actingAs($this->supervisor)->get('/supervisor/import/work-orders')->assertOk();
        $this->actingAs($this->supervisor)->get('/supervisor/import/materials')->assertNotFound();
        $this->actingAs($this->supervisor)->get('/supervisor/import/samples/materials')->assertNotFound();
        $this->actingAs($this->supervisor)->get('/admin/import')->assertForbidden();
    }

    public function test_old_importer_urls_redirect_into_the_unified_importer(): void
    {
        $this->actingAs($this->admin)->get('/admin/csv-import')->assertRedirect('/admin/import/work-orders');
        $this->actingAs($this->admin)->get('/admin/materials-import')->assertRedirect('/admin/import/materials');
        $this->actingAs($this->supervisor)->get('/supervisor/csv-import')->assertRedirect('/supervisor/import/work-orders');
    }

    // ── Samples ─────────────────────────────────────────────────────────────

    public function test_samples_download_per_entity(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/import/samples/boms');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="boms-sample.csv"');
        $this->assertStringStartsWith('product_type_code,material_code,quantity_per_unit', $response->getContent());

        // The legacy example route serves the same registry samples.
        $this->actingAs($this->admin)->get('/admin/import-example/product-types')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="product_types_example.csv"');
        $this->actingAs($this->admin)->get('/admin/import-example/lines')->assertOk();
        $this->actingAs($this->admin)->get('/admin/import-example/nope')->assertNotFound();
    }

    // ── Upload ──────────────────────────────────────────────────────────────

    public function test_upload_validates_the_file_and_the_options(): void
    {
        $this->actingAs($this->admin)->from('/admin/import/product-types')
            ->post('/admin/import/product-types/upload', [
                'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors('file');

        $this->actingAs($this->admin)->from('/admin/import/product-types')
            ->post('/admin/import/product-types/upload', [
                'file' => $this->csv(), 'delimiter' => 'pipe',
            ])->assertSessionHasErrors('delimiter');

        $this->actingAs($this->admin)->from('/admin/import/product-types')
            ->post('/admin/import/product-types/upload', [
                'file' => $this->csv(), 'options' => ['strategy' => 'nuke'],
            ])->assertSessionHasErrors('options.strategy');

        $this->actingAs($this->admin)->from('/admin/import/materials')
            ->post('/admin/import/materials/upload', [
                'file' => $this->csv(), 'options' => ['external_system' => 'Subiekt GT'],
            ])->assertSessionHasErrors('options.external_system');

        $this->actingAs($this->admin)->from('/admin/import/product-types')
            ->post('/admin/import/product-types/upload', [
                'file' => $this->csv(''),
            ])->assertSessionHasErrors('file');
    }

    public function test_upload_stores_the_file_and_redirects_to_the_mapping_page(): void
    {
        $profile = CsvImportMapping::create([
            'name' => 'ERP', 'entity' => 'product_types', 'user_id' => $this->admin->id,
            'mapping_config' => ['column_mappings' => ['code' => 'code', 'name' => 'name']], 'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/import/product-types/upload', [
                'file' => $this->csv("code;name\nP-1;Widget\n"),
                'delimiter' => 'auto',
                'encoding' => 'utf-8',
                'mapping_id' => $profile->id,
                'options' => ['strategy' => 'skip_existing', 'external_system' => 'erp'],
            ]);

        $response->assertRedirect();
        $this->assertMatchesRegularExpression('#/admin/import/product-types/map/[A-Za-z0-9]{32}$#', $response->headers->get('Location'));
        $this->assertCount(1, Storage::disk('local')->files('imports'));

        // The mapping page is a real GET: a refresh or a redirect-back lands here.
        $this->actingAs($this->admin)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/import/Mapping')
                ->where('headers', ['code', 'name'])
                ->where('totalRows', 1)
                ->where('previewRows.0.name', 'Widget')
                ->where('originalFilename', 'items.csv')
                ->where('options.strategy', 'skip_existing')
                ->where('initialMapping', ['code' => 'code', 'name' => 'name'])
                ->where('entity.key', 'product_types')
                ->where('token', fn ($t) => is_string($t) && strlen($t) === 32)
                ->missing('filePath')
                ->has('profiles', 1)
            );

        $this->actingAs($this->admin)->get('/admin/import/product-types/map/'.str_repeat('x', 32))
            ->assertRedirect('/admin/import/product-types')
            ->assertSessionHas('error');
    }

    // ── Process ─────────────────────────────────────────────────────────────

    /**
     * A remembered upload, as the upload step leaves it in the session.
     *
     * @return array{token: string, session: array<string, mixed>}
     */
    private function remembered(string $entity, string $content = "code,name\nP-1,Widget\n", array $options = [], ?string $originalName = 'products.csv'): array
    {
        $path = 'imports/imp_'.uniqid().'.csv';
        Storage::disk('local')->put($path, $content);
        $token = str_repeat('a', 32);

        return ['token' => $token, 'session' => ['import.uploads.'.$token => [
            'entity' => $entity,
            'file_path' => $path,
            'original_filename' => $originalName,
            'file_options' => ['delimiter' => 'semicolon', 'encoding' => 'windows-1250'],
            'options' => $options,
            'mapping' => null,
        ]]];
    }

    public function test_process_rejects_bad_tokens_and_mapping_targets(): void
    {
        $this->actingAs($this->admin)->from('/admin/import/product-types')
            ->post('/admin/import/product-types/process', [
                'token' => '../.env', 'mapping' => ['code' => 'code'],
            ])->assertSessionHasErrors('token');

        $up = $this->remembered('product_types');

        $this->actingAs($this->admin)->withSession($up['session'])->from('/admin/import/product-types')
            ->post('/admin/import/product-types/process', [
                'token' => $up['token'], 'mapping' => ['code' => 'DROP TABLE'],
            ])->assertSessionHasErrors('mapping.code');
    }

    public function test_process_sends_the_user_back_to_the_mapping_page_when_an_identifier_is_unmapped(): void
    {
        Queue::fake();
        $up = $this->remembered('materials', "code,name\nM-1,Steel\n");

        $this->actingAs($this->admin)->withSession($up['session'])
            ->post('/admin/import/materials/process', [
                'token' => $up['token'],
                'mapping' => ['code' => 'code', 'name' => '_ignore'],
            ])
            ->assertRedirect('/admin/import/materials/map/'.$up['token'])
            ->assertSessionHasErrors('mapping');

        // The half-done mapping is kept for the page.
        $this->assertSame(['code' => 'code', 'name' => '_ignore'], session('import.uploads.'.$up['token'])['mapping']);

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('csv_imports', ['user_id' => $this->admin->id]);
    }

    public function test_process_queues_the_run_saves_the_profile_and_lands_on_the_run_page(): void
    {
        Queue::fake();
        $up = $this->remembered('product_types', options: ['strategy' => 'skip_existing', 'only_categories' => 'FG, RM']);

        $response = $this->actingAs($this->admin)->withSession($up['session'])
            ->post('/admin/import/product-types/process', [
                'token' => $up['token'],
                'mapping' => ['code' => 'code', 'name' => 'name', 'Extra' => '_ignore'],
                'save_mapping_name' => 'Pantheon products',
            ]);

        // Scoped to this user: a shared dev database may hold other runs.
        $import = CsvImport::where('user_id', $this->admin->id)->latest('id')->first();
        $this->assertNotNull($import);
        $response->assertRedirect("/admin/import/runs/{$import->id}")->assertSessionHas('success');

        $this->assertSame('product_types', $import->entity);
        $this->assertSame(CsvImport::STATUS_PENDING, $import->status);
        $this->assertSame($up['session']['import.uploads.'.$up['token']]['file_path'], $import->file_path);
        $this->assertSame('products.csv', $import->original_filename);
        $this->assertSame('skip_existing', $import->import_strategy);
        $this->assertSame($this->admin->id, $import->user_id);
        $this->assertSame('semicolon', $import->options['delimiter']);
        $this->assertSame('windows-1250', $import->options['encoding']);
        $this->assertSame(['code' => 'code', 'name' => 'name', 'Extra' => '_ignore'], $import->options['mapping']);
        $this->assertSame('FG, RM', $import->options['options']['only_categories']);
        $this->assertNull(session('import.uploads.'.$up['token']), 'the upload is forgotten once queued');

        Queue::assertPushed(ProcessDataImport::class, fn ($job) => $job->importId === $import->id);

        $this->assertDatabaseHas('csv_import_mappings', [
            'name' => 'Pantheon products', 'entity' => 'product_types', 'user_id' => $this->admin->id,
        ]);
    }

    public function test_process_with_a_forgotten_upload_sends_the_user_back_to_upload(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/admin/import/product-types/process', [
                'token' => str_repeat('b', 32), 'mapping' => ['code' => 'code'],
            ])
            ->assertRedirect('/admin/import/product-types')
            ->assertSessionHas('error');

        // A token remembered for another entity is not honoured either.
        $up = $this->remembered('materials');
        $this->actingAs($this->admin)->withSession($up['session'])
            ->post('/admin/import/product-types/process', [
                'token' => $up['token'], 'mapping' => ['code' => 'code'],
            ])
            ->assertRedirect('/admin/import/product-types');

        Queue::assertNothingPushed();
    }

    public function test_supervisor_can_queue_a_work_order_import_but_not_master_data(): void
    {
        Queue::fake();
        $up = $this->remembered('work_orders', "order,qty\nWO-1,5\n", ['strategy' => 'update_or_create']);

        $this->actingAs($this->supervisor)->withSession($up['session'])
            ->post('/supervisor/import/work-orders/process', [
                'token' => $up['token'],
                'mapping' => ['order' => 'order_no', 'qty' => 'quantity'],
            ])
            ->assertRedirect();

        Queue::assertPushed(ProcessDataImport::class);
        $this->assertSame('work_orders', CsvImport::where('user_id', $this->supervisor->id)->latest('id')->first()->entity);

        $mat = $this->remembered('materials');
        $this->actingAs($this->supervisor)->withSession($mat['session'])
            ->post('/supervisor/import/materials/process', [
                'token' => $mat['token'], 'mapping' => ['code' => 'code', 'name' => 'name'],
            ])
            ->assertNotFound();
    }

    // ── Run page, errors, profiles ──────────────────────────────────────────

    public function test_run_page_shows_the_import_with_structured_errors_and_tolerates_legacy_ones(): void
    {
        $import = CsvImport::factory()->completed()->create([
            'user_id' => $this->admin->id,
            'entity' => 'materials',
            'total_rows' => 3, 'processed_rows' => 3, 'created_rows' => 1, 'updated_rows' => 1, 'failed_rows' => 1,
            'error_log' => [
                ['row' => 3, 'field' => 'code', 'message' => 'Material code is required'],
                'Row 9: legacy string',
            ],
        ]);

        $this->actingAs($this->admin)->get("/admin/import/runs/{$import->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/import/Show')
                ->where('import.id', $import->id)
                ->where('import.progress', 100)
                ->where('entity.slug', 'materials')
                ->where('import.errors.0.row', 3)
                ->where('import.errors.1.message', 'Row 9: legacy string')
                ->where('userName', $this->admin->name)
            );

        $csv = $this->actingAs($this->admin)->get("/admin/import/runs/{$import->id}/errors.csv");
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Material code is required', $csv->getContent());
        $this->assertStringContainsString('legacy string', $csv->getContent());

        // A supervisor sees work-order runs only.
        $this->actingAs($this->supervisor)->get("/supervisor/import/runs/{$import->id}")->assertNotFound();
    }

    public function test_run_page_is_tenant_scoped(): void
    {
        $tenant = Tenant::create(['name' => 'Other']);
        $import = CsvImport::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $this->admin->id]);

        $stranger = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Mine'])->id]);
        $stranger->assignRole('Admin');

        $this->actingAs($stranger)->get("/admin/import/runs/{$import->id}")->assertNotFound();
    }

    public function test_mapping_profiles_are_per_entity_and_deletable_by_their_owner_only(): void
    {
        $mine = CsvImportMapping::create(['name' => 'Mine', 'entity' => 'materials', 'user_id' => $this->admin->id, 'mapping_config' => ['column_mappings' => ['a' => 'code']]]);
        $theirs = CsvImportMapping::create(['name' => 'Theirs', 'entity' => 'materials', 'user_id' => $this->supervisor->id, 'mapping_config' => ['column_mappings' => ['a' => 'code']]]);
        CsvImportMapping::create(['name' => 'Other entity', 'entity' => 'work_orders', 'user_id' => $this->admin->id, 'mapping_config' => ['column_mappings' => ['a' => 'order_no']]]);

        $this->actingAs($this->admin)->get('/admin/import/materials')
            ->assertInertia(fn (Assert $page) => $page->has('profiles', 1)->where('profiles.0.name', 'Mine')->where('profiles.0.own', true));

        $this->actingAs($this->admin)->delete("/admin/import/profiles/{$theirs->id}")->assertForbidden();
        $this->actingAs($this->admin)->from('/admin/import/materials')->delete("/admin/import/profiles/{$mine->id}")
            ->assertRedirect('/admin/import/materials');
        $this->assertSoftDeleted('csv_import_mappings', ['id' => $mine->id]);
    }
}
