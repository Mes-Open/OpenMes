<?php

namespace Tests\Feature\Import;

use App\Models\CsvImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may see an import run, and what happens to the files behind them.
 */
class ImportAccessAndUpkeepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('Admin', 'web'));

        return $u;
    }

    private function supervisor(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('Supervisor', 'web'));

        return $u;
    }

    private function makeRun(string $entity, array $attrs = []): CsvImport
    {
        // $attrs first: PHP's array union keeps the LEFT operand, so defaults
        // listed first would silently win over what the caller asked for.
        return CsvImport::create($attrs + [
            'user_id' => $this->admin()->id,
            'entity' => $entity,
            'filename' => 'f.csv',
            'original_filename' => 'f.csv',
            'file_path' => 'imports/'.uniqid().'.csv',
            'import_strategy' => 'update_or_create',
            'status' => CsvImport::STATUS_COMPLETED,
            'error_log' => [['row' => 2, 'field' => 'code', 'message' => 'MAT-SECRET-01 already exists']],
        ]);
    }

    public function test_a_supervisor_cannot_download_the_errors_of_an_admin_only_run(): void
    {
        $materials = $this->makeRun('materials');

        // The rows carry material codes and names — admin-only master data.
        $this->actingAs($this->supervisor())
            ->get("/supervisor/import/runs/{$materials->id}/errors.csv")
            ->assertNotFound();

        $this->actingAs($this->supervisor())
            ->get("/supervisor/import/runs/{$materials->id}")
            ->assertNotFound();
    }

    public function test_a_supervisor_can_still_reach_a_work_order_run(): void
    {
        $orders = $this->makeRun('work_orders');

        $this->actingAs($this->supervisor())
            ->get("/supervisor/import/runs/{$orders->id}/errors.csv")
            ->assertOk();
    }

    public function test_an_admin_reaches_every_run(): void
    {
        $materials = $this->makeRun('materials');

        $this->actingAs($this->admin())
            ->get("/admin/import/runs/{$materials->id}/errors.csv")
            ->assertOk()
            ->assertSee('MAT-SECRET-01');
    }

    public function test_a_run_whose_entity_the_registry_no_longer_knows_is_not_shown(): void
    {
        // Previously the guard was skipped entirely when the registry returned
        // null, so a legacy `entity` value rendered for everyone.
        $legacy = $this->makeRun('martians');

        $this->actingAs($this->admin())
            ->get("/admin/import/runs/{$legacy->id}")
            ->assertNotFound();
    }

    public function test_the_history_lists_only_the_entities_the_section_can_open(): void
    {
        $this->makeRun('materials');
        $orders = $this->makeRun('work_orders');

        $this->actingAs($this->supervisor())
            ->get('/supervisor/import/work-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recentImports', fn ($rows) => collect($rows)->pluck('id')->all() === [$orders->id]));
    }

    public function test_prune_keeps_only_the_uploads_a_run_still_needs(): void
    {
        Storage::fake('local');

        $files = [
            'abandoned' => 'imports/abandoned.csv',   // nothing references it
            'queued' => 'imports/queued.csv',         // a job has yet to read it
            'validated' => 'imports/validated.csv',   // a dry run the user may still run for real
            'stale' => 'imports/stale.csv',           // a dry run from days ago
        ];

        foreach ($files as $p) {
            Storage::disk('local')->put($p, "code,name\r\nA,B\r\n");
        }

        $this->makeRun('product_types', ['file_path' => $files['queued'], 'status' => CsvImport::STATUS_PENDING]);
        $this->makeRun('product_types', ['file_path' => $files['validated'], 'dry_run' => true]);
        $this->makeRun('product_types', ['file_path' => $files['stale'], 'dry_run' => true])
            ->forceFill(['created_at' => now()->subDays(3)])->save();

        // Age every file past the cutoff, so what survives is decided by the
        // runs that reference it, not by the file's own timestamp.
        foreach ($files as $p) {
            touch(Storage::disk('local')->path($p), now()->subDays(3)->getTimestamp());
        }

        $this->artisan('imports:prune-uploads')->assertSuccessful();

        $disk = Storage::disk('local');
        $this->assertFalse($disk->exists($files['abandoned']), 'an upload nothing references is deleted');
        $this->assertTrue($disk->exists($files['queued']), 'a queued run has not read its file yet');
        $this->assertTrue($disk->exists($files['validated']), 'a recent validation is still waiting to be run for real');
        $this->assertFalse($disk->exists($files['stale']), 'a validation from days ago has had its chance');
    }

    public function test_prune_leaves_a_recent_upload_alone(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/fresh.csv', "code\r\nA\r\n");

        $this->artisan('imports:prune-uploads')->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('imports/fresh.csv'));
    }
}
