<?php

namespace Tests\Feature\Web\Admin;

use App\Jobs\ApplyUpdateJob;
use App\Models\User;
use App\Services\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        Cache::flush();
    }

    // ── check() ──────────────────────────────────────────────────────────────

    public function test_check_returns_false_when_no_remote(): void
    {
        Http::fake([
            'getopenmes.com/*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/check');

        $response->assertOk();
        $response->assertJson(['available' => false]);
    }

    public function test_check_returns_true_when_remote_newer(): void
    {
        Http::fake([
            'getopenmes.com/*' => Http::response([
                'version'     => 'v999.0.0',
                'name'        => 'OpenMES v999.0.0',
                'release_url' => 'https://example.com/r',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/check');

        $response->assertOk();
        $response->assertJson([
            'available' => true,
            'latest'    => 'v999.0.0',
        ]);
    }

    public function test_check_returns_false_when_remote_older_or_equal(): void
    {
        $current = config('version.current');

        Http::fake([
            'getopenmes.com/*' => Http::response([
                'version' => $current,
                'name'    => "OpenMES {$current}",
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/check');

        $response->assertOk();
        $response->assertJson(['available' => false]);
    }

    // ── apply() ──────────────────────────────────────────────────────────────

    public function test_apply_returns_error_when_no_cached_check(): void
    {
        Cache::forget(UpdateApplier::CHECK_CACHE_KEY);

        $response = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        $response->assertRedirect('/admin/dashboard');
        $this->assertStringContainsString(
            'No update information',
            session('error') ?? ''
        );
    }

    public function test_apply_dispatches_job_with_cached_remote(): void
    {
        Bus::fake();

        Cache::put(UpdateApplier::CHECK_CACHE_KEY, [
            'version' => 'v9.0.0',
            'zip_url' => 'https://example.com/openmes-v9.0.0.zip',
        ], 60);

        $response = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        Bus::assertDispatched(ApplyUpdateJob::class);
        $response->assertRedirect('/admin/dashboard');

        $this->assertStringContainsString('queued', session('success') ?? '');

        $status = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
        $this->assertIsArray($status);
        $this->assertSame('queued', $status['state']);
    }

    public function test_apply_rejects_when_update_already_in_progress(): void
    {
        Cache::put(UpdateApplier::STATUS_CACHE_KEY, [
            'state'      => 'copying',
            'version'    => 'v9.0.0',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], 60);

        $response = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        $response->assertRedirect('/admin/dashboard');
        $this->assertStringContainsString(
            'already in progress',
            session('error') ?? ''
        );
    }

    // ── status() ─────────────────────────────────────────────────────────────

    public function test_status_returns_cached_state(): void
    {
        Cache::put(UpdateApplier::STATUS_CACHE_KEY, [
            'state'    => 'downloading',
            'progress' => 20,
        ], 60);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/status');

        $response->assertOk();
        $response->assertJson(['state' => 'downloading']);
    }

    public function test_status_returns_null_when_nothing_in_cache(): void
    {
        Cache::forget(UpdateApplier::STATUS_CACHE_KEY);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/status');

        $response->assertOk();
        // Laravel renders response()->json(null) as either "null" or "{}" depending
        // on JsonResponse defaults; both unambiguously signal "no status cached".
        $this->assertContains($response->getContent(), ['null', '{}']);
    }

    // ── auth ─────────────────────────────────────────────────────────────────

    public function test_check_requires_admin_role(): void
    {
        $response = $this->actingAs($this->operator)->get('/admin/update/check');

        $response->assertStatus(403);
    }

    public function test_apply_requires_admin_role(): void
    {
        $response = $this->actingAs($this->operator)->post('/admin/update/apply');

        $response->assertStatus(403);
    }
}
