<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\DynamicCors;
use App\Models\Batch;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Settings → System switches must change what the system does, not only what
 * the settings page shows after a reload.
 */
class SystemSettingsEffectsTest extends TestCase
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

    /** What the settings page posts, with the given changes. */
    private function saveSettings(array $changes): void
    {
        $this->actingAs($this->admin, 'web')->post('/settings/system', array_merge([
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
        ], $changes))->assertSessionHasNoErrors();
    }

    private function batchOfTwoSteps(): Batch
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        foreach ([1 => 'Cut', 2 => 'Pack'] as $n => $name) {
            TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => $n, 'name' => $name]);
        }
        $wo = WorkOrder::factory()->create([
            'line_id' => Line::factory()->create()->id,
            'planned_qty' => 10,
            'process_snapshot' => $template->load('steps')->toSnapshot(),
        ]);

        return app(WorkOrderService::class)->createBatch($wo, 10);
    }

    // ── Force sequential steps ───────────────────────────────────────────────

    public function test_the_sequential_steps_switch_is_enforced(): void
    {
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
        $second = fn () => $this->batchOfTwoSteps()->steps()->where('step_number', 2)->first();

        $this->saveSettings(['force_sequential_steps' => true]);
        $this->assertFalse($second()->prerequisitesMet());

        $this->saveSettings(['force_sequential_steps' => false]);
        $this->assertTrue($second()->prerequisitesMet());
    }

    public function test_the_settings_page_shows_the_enforced_rules(): void
    {
        $this->saveSettings(['force_sequential_steps' => false, 'allow_overproduction' => true]);

        $settings = $this->actingAs($this->admin)->get('/settings/system')
            ->getOriginalContent()->getData()['page']['props']['settings'];

        $this->assertFalse($settings['force_sequential_steps']);
        $this->assertTrue($settings['allow_overproduction']);
    }

    // ── Allow overproduction ─────────────────────────────────────────────────

    public function test_the_overproduction_switch_controls_batches_above_the_planned_quantity(): void
    {
        $wo = WorkOrder::factory()->create(['planned_qty' => 10]);
        $create = fn () => $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/work-orders/{$wo->id}/batches", ['target_qty' => 15]);

        $this->saveSettings(['allow_overproduction' => false]);
        $create()->assertUnprocessable();

        $this->saveSettings(['allow_overproduction' => true]);
        $create()->assertSuccessful();
    }

    // ── CORS ─────────────────────────────────────────────────────────────────

    private function preflight(string $origin = 'https://erp.example.com')
    {
        return $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
    }

    public function test_a_cors_change_applies_immediately(): void
    {
        $this->saveSettings(['cors_allowed_origins' => '*']);
        $this->assertSame('*', $this->preflight()->headers->get('Access-Control-Allow-Origin'));
        $this->assertTrue(Cache::has(DynamicCors::CACHE_KEY));

        $this->saveSettings(['cors_allowed_origins' => '']);

        $this->assertNull($this->preflight()->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_credentials_are_allowed_only_for_named_origins(): void
    {
        $this->saveSettings(['cors_allowed_origins' => '*']);
        $this->assertNull($this->preflight()->headers->get('Access-Control-Allow-Credentials'));

        $this->saveSettings(['cors_allowed_origins' => 'https://erp.example.com']);
        $response = $this->preflight('https://erp.example.com');
        $this->assertSame('https://erp.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    // ── Timed correction window ──────────────────────────────────────────────

    public function test_a_correction_does_not_extend_the_timed_window(): void
    {
        $this->saveSettings(['production_qty_edit_policy' => 'timed', 'production_qty_edit_window_minutes' => 5]);
        $wo = WorkOrder::factory()->create(['planned_qty' => 100, 'produced_qty' => 10]);
        $shift = Shift::create(['line_id' => $wo->line_id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
        $entry = WorkOrderShiftEntry::create(['work_order_id' => $wo->id, 'shift_id' => $shift->id, 'quantity' => 10, 'user_id' => $this->admin->id, 'production_date' => today()]);

        // Four minutes after the entry: inside the window.
        $this->travel(4)->minutes();
        $this->actingAs($this->admin)->put("/operator/shift-entry/{$entry->id}/correct", ['quantity' => 9])
            ->assertRedirect();
        $this->assertEquals(9, $entry->fresh()->quantity);

        // Six minutes after the entry: the window is over, even though the
        // correction was made only two minutes ago.
        $this->travel(2)->minutes();
        $this->actingAs($this->admin)->put("/operator/shift-entry/{$entry->id}/correct", ['quantity' => 8])
            ->assertForbidden();
        $this->assertEquals(9, $entry->fresh()->quantity);
    }

    // ── Return to the saved tab ──────────────────────────────────────────────

    private function saveFrom(string $referer)
    {
        return $this->actingAs($this->admin, 'web')
            ->from($referer)
            ->post('/settings/system', [
                'production_period' => 'none',
                'workflow_mode' => 'status',
                'schedule_view_mode' => 'weekly',
                'schedule_shifts_per_day' => 1,
                'realtime_mode' => 'polling',
                'production_tracking_mode' => 'per_operation',
                'production_qty_edit_policy' => 'none',
                'scanner_mode' => 'hid',
            ]);
    }

    public function test_saving_returns_to_the_tab_it_was_saved_from(): void
    {
        $this->saveFrom('/settings/system?tab=production')
            ->assertRedirect(route('settings.system', ['tab' => 'production']));
    }

    public function test_saving_from_the_general_tab_returns_to_the_plain_page(): void
    {
        $this->saveFrom('/settings/system')->assertRedirect(route('settings.system'));
        $this->saveFrom('/settings/system?tab=general')->assertRedirect(route('settings.system'));
    }

    public function test_only_a_tab_name_is_taken_from_the_referring_url(): void
    {
        $this->saveFrom('/settings/system?tab='.urlencode('x"><script>'))
            ->assertRedirect(route('settings.system'));
        $this->saveFrom('https://evil.example.org/?tab=security')
            ->assertRedirect(route('settings.system', ['tab' => 'security']));
    }

    public function test_the_saved_message_is_translated(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $this->withSession(['locale' => 'pl'])
            ->saveFrom('/settings/system')
            ->assertSessionHas('success', 'Ustawienia systemowe zostały zaktualizowane.');
    }

    // ── Data tab ─────────────────────────────────────────────────────────────

    public function test_backup_actions_return_to_the_data_tab(): void
    {
        $this->actingAs($this->admin)->delete('/settings/backups/missing.zip')
            ->assertRedirect(route('settings.system', ['tab' => 'data']));
    }

    // ── Currency ─────────────────────────────────────────────────────────────

    public function test_a_cost_source_without_a_currency_gets_the_installation_currency(): void
    {
        $this->saveSettings(['default_currency' => 'EUR']);

        $this->actingAs($this->admin)->post('/admin/cost-sources', [
            'name' => 'Electricity',
            'code' => 'ELEC',
            'unit_cost' => 1,
            'currency' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('EUR', DB::table('cost_sources')->where('code', 'ELEC')->value('currency'));
    }
}
