<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Field-error fixes surfaced by the http_error telemetry:
 *  - 500 on PUT /admin/schedule — auto-batch side-effect must not crash the drag.
 *  - 409 on /settings/sample-data — re-load must be guarded, not race.
 *  - 403 on /admin/dashboard — a tab the user can't open redirects, not dead-ends.
 */
class ErrorHandlingFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    // ── 500 schedule ────────────────────────────────────────────────────────

    public function test_schedule_update_survives_a_failing_auto_batch(): void
    {
        $line = Line::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => ProductType::factory(),
            'process_snapshot' => ['steps' => []], // non-empty → snapshot step skipped
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 100,
        ]);

        // Force the auto-batch side-effect to blow up the way bad BOM data does.
        $this->mock(WorkOrderService::class, function ($m) {
            $m->shouldReceive('createBatch')->andThrow(new \RuntimeException('BOM broken'));
        });

        $response = $this->actingAs($this->admin)->putJson("/admin/schedule/{$workOrder->id}", [
            'line_id' => $line->id,
            'planned_start_at' => now()->addDay()->toDateTimeString(),
            'planned_end_at' => now()->addDay()->addHours(2)->toDateTimeString(),
        ]);

        // No 500 — the placement succeeds and the failure is a warning.
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('warnings'));
        $this->assertStringContainsString('BOM broken', implode(' ', $response->json('warnings')));

        // The schedule placement itself persisted.
        $this->assertNotNull($workOrder->fresh()->planned_start_at);
    }

    // ── 409 sample data ───────────────────────────────────────────────────────

    public function test_sample_data_is_not_reloaded_when_already_loaded(): void
    {
        DB::table('system_settings')->insert(['key' => 'sample_data_loaded', 'value' => json_encode(true)]);

        Artisan::shouldReceive('call')->never(); // guard must short-circuit

        $this->actingAs($this->admin)->post('/settings/sample-data')
            ->assertRedirect()
            ->assertSessionHas('info');
    }

    public function test_sample_data_loads_once_and_sets_flag(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);

        $this->actingAs($this->admin)->post('/settings/sample-data')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', ['key' => 'sample_data_loaded']);
    }

    public function test_sample_data_failure_reports_error_without_marking_loaded(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('seed boom'));

        $this->actingAs($this->admin)->post('/settings/sample-data')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('system_settings', ['key' => 'sample_data_loaded']);
    }

    // ── 403 dashboard ─────────────────────────────────────────────────────────

    public function test_user_without_dashboard_tab_is_redirected_to_first_accessible_tab(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tab:orders'); // can open Orders, not Dashboard

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertRedirect('/admin/work-orders');
    }

    public function test_user_with_no_tabs_still_gets_403_on_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_api_request_without_tab_permission_still_gets_403(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tab:orders');

        $this->actingAs($user)->getJson('/admin/dashboard')->assertForbidden();
    }
}
