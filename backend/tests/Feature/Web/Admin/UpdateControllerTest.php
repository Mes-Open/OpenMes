<?php

namespace Tests\Feature\Web\Admin;

use App\Http\Controllers\Web\Admin\UpdateController;
use App\Jobs\ApplyUpdateJob;
use App\Models\SystemUpdate;
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

    public function test_apply_concurrent_second_call_does_not_redispatch_job(): void
    {
        Bus::fake();

        Cache::put(UpdateApplier::CHECK_CACHE_KEY, [
            'version' => 'v9.0.0',
            'zip_url' => 'https://example.com/openmes-v9.0.0.zip',
        ], 60);

        // First click acquires the lock and dispatches the Job (Bus is faked,
        // so it never actually runs and never releases the lock).
        $first = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        $first->assertRedirect('/admin/dashboard');
        Bus::assertDispatchedTimes(ApplyUpdateJob::class, 1);
        $this->assertStringContainsString('queued', session('success') ?? '');

        // Second click — fired while the first Job still "holds" the lock —
        // must be rejected, NOT dispatched again. This is the race the
        // concurrency guard exists to defeat.
        $second = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        $second->assertRedirect('/admin/dashboard');
        Bus::assertDispatchedTimes(ApplyUpdateJob::class, 1);
        $this->assertStringContainsString(
            'already in progress',
            session('error') ?? ''
        );
    }

    public function test_apply_releases_lock_after_job_finishes(): void
    {
        // Acquire the lock as if a Job was in flight.
        Cache::lock(UpdateController::APPLY_LOCK_KEY, UpdateController::APPLY_LOCK_TTL)->get();

        // Job tear-down (handle() finally + failed()) always force-releases —
        // verify that after force-release a new apply() succeeds without
        // needing to wait for the TTL.
        Cache::lock(UpdateController::APPLY_LOCK_KEY)->forceRelease();

        Bus::fake();

        Cache::put(UpdateApplier::CHECK_CACHE_KEY, [
            'version' => 'v9.0.1',
            'zip_url' => 'https://example.com/openmes-v9.0.1.zip',
        ], 60);

        $response = $this->actingAs($this->admin)
            ->from('/admin/dashboard')
            ->post('/admin/update/apply');

        $response->assertRedirect('/admin/dashboard');
        Bus::assertDispatched(ApplyUpdateJob::class);
        $this->assertStringContainsString('queued', session('success') ?? '');
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

    // ── history() ────────────────────────────────────────────────────────────

    public function test_history_returns_recent_updates_with_user(): void
    {
        $completed = SystemUpdate::create([
            'user_id'              => $this->admin->id,
            'from_version'         => 'v1.0.0',
            'to_version'           => 'v1.1.0',
            'state'                => SystemUpdate::STATE_COMPLETED,
            'started_at'           => now()->subMinutes(5),
            'finished_at'          => now()->subMinutes(4),
            'duration_seconds'     => 60,
            'files_copied'         => 42,
            'composer_install_ran' => true,
            'checksum_verified'    => true,
        ]);

        $failed = SystemUpdate::create([
            'user_id'      => $this->admin->id,
            'from_version' => 'v1.1.0',
            'to_version'   => 'v1.2.0',
            'state'        => SystemUpdate::STATE_FAILED,
            'started_at'   => now()->subMinute(),
            'finished_at'  => now(),
            'error'        => 'boom',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/update/history');

        $response->assertOk();
        $payload = $response->json('updates');
        $this->assertCount(2, $payload);
        // Most recent first (started_at desc).
        $this->assertSame($failed->id, $payload[0]['id']);
        $this->assertSame('failed', $payload[0]['state']);
        $this->assertSame('boom', $payload[0]['error']);
        $this->assertSame($this->admin->id, $payload[0]['user']['id']);
        $this->assertSame($completed->id, $payload[1]['id']);
        $this->assertSame('completed', $payload[1]['state']);
        $this->assertSame(42, $payload[1]['files_copied']);
        $this->assertTrue($payload[1]['checksum_verified']);
    }

    public function test_history_requires_admin_role(): void
    {
        $response = $this->actingAs($this->operator)->get('/admin/update/history');

        $response->assertStatus(403);
    }
}
