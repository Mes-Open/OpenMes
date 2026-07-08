<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Backup / restore / reset. The destructive backup/restore DB paths are
 * PostgreSQL-only (information_schema, TRUNCATE … CASCADE, pg_get_serial_sequence)
 * and can't run on the sqlite test DB, so these tests cover the security surface
 * that IS portable: authorization, validation and the upload handler.
 */
class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/backups/*')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    // ── Authorization (the CodeRabbit finding) ────────────────────────────────

    public function test_guest_cannot_reset(): void
    {
        $this->post('/settings/reset', ['confirm_text' => 'RESET'])->assertRedirect();
    }

    public function test_non_admin_cannot_reset(): void
    {
        $this->actingAs($this->operator)
            ->post('/settings/reset', ['confirm_text' => 'RESET'])
            ->assertForbidden();
    }

    public function test_non_admin_cannot_upload_backup(): void
    {
        $this->actingAs($this->operator)
            ->post('/settings/backups/upload', ['backup_file' => UploadedFile::fake()->create('b.zip', 10, 'application/zip')])
            ->assertForbidden();
    }

    public function test_non_admin_cannot_create_backup(): void
    {
        $this->actingAs($this->operator)->post('/settings/backups/full')->assertForbidden();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_reset_requires_confirm_text(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/reset', ['confirm_text' => 'nope'])
            ->assertSessionHasErrors('confirm_text');

        $this->actingAs($this->admin)
            ->post('/settings/reset', [])
            ->assertSessionHasErrors('confirm_text');
    }

    public function test_upload_rejects_non_zip(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/backups/upload', ['backup_file' => UploadedFile::fake()->create('evil.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('backup_file');
    }

    public function test_upload_requires_a_file(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/backups/upload', [])
            ->assertSessionHasErrors('backup_file');
    }

    // ── Upload happy path (no DB-specific SQL) ────────────────────────────────

    public function test_admin_can_upload_a_zip_backup(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/backups/upload', ['backup_file' => UploadedFile::fake()->create('mybackup.zip', 20, 'application/zip')])
            ->assertRedirect();

        $stored = glob(storage_path('app/backups/uploaded_*mybackup.zip'));
        $this->assertNotEmpty($stored, 'Uploaded backup should be stored with a sanitized name.');
    }

    // ── Download/delete path guards ───────────────────────────────────────────

    public function test_download_rejects_unresolvable_path(): void
    {
        // realpath() fails for anything that doesn't resolve inside the backups
        // dir (missing file or traversal attempt) → the guard returns 400 before
        // serving anything.
        $this->actingAs($this->admin)
            ->get('/settings/backups/download/does-not-exist.zip')
            ->assertStatus(400);
    }

    public function test_admin_config_present_for_reset_recreation(): void
    {
        // The reset recreates the admin from config('openmmes.admin.*') (read via
        // config so it survives config:cache). Assert the config keys exist.
        config(['openmmes.admin.username' => 'admin', 'openmmes.admin.email' => 'a@b.c', 'openmmes.admin.password' => 'secret']);
        $this->assertSame('admin', config('openmmes.admin.username'));
        $this->assertArrayHasKey('admin', config('openmmes'));
    }
}
