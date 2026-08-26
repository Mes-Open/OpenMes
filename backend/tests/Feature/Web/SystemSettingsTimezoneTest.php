<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Changing the plant timezone after installation.
 *
 * The installer's own step is covered by InstallTimezoneTest; this pins the
 * post-install half the wizard promises ("you can change it later without
 * reinstalling"): an admin-only field on Settings → System that writes the same
 * `system_settings` row, plus the per-request re-apply that stops a long-lived
 * Octane/queue worker from serving the zone it booted with.
 */
class SystemSettingsTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        TimezoneRegistry::flush();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'schedule_horizon_weeks' => 6,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
        ], $overrides);
    }

    public function test_admin_can_change_the_plant_timezone(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['app_timezone' => 'America/Argentina/Buenos_Aires']))
            ->assertSessionHasNoErrors();

        // Stored raw, not JSON-encoded — TimezoneRegistry reads it as a plain
        // identifier, so an accidental json_encode() here would break `stored()`.
        $this->assertDatabaseHas('system_settings', [
            'key' => TimezoneRegistry::SETTING_KEY,
            'value' => 'America/Argentina/Buenos_Aires',
        ]);
        $this->assertSame('America/Argentina/Buenos_Aires', TimezoneRegistry::stored());
    }

    public function test_saving_applies_the_zone_to_the_running_request(): void
    {
        config(['app.timezone' => 'UTC']);

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['app_timezone' => 'Asia/Tokyo']));

        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());
    }

    public function test_an_unknown_timezone_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['app_timezone' => 'Mars/Olympus_Mons']))
            ->assertSessionHasErrors('app_timezone');

        $this->assertNull(TimezoneRegistry::stored());
    }

    public function test_omitting_the_field_leaves_the_stored_zone_alone(): void
    {
        TimezoneRegistry::save('Asia/Tokyo');

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload())
            ->assertSessionHasNoErrors();

        TimezoneRegistry::flush();
        $this->assertSame('Asia/Tokyo', TimezoneRegistry::stored());
    }

    public function test_a_non_admin_cannot_change_the_timezone(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post('/settings/system', $this->payload(['app_timezone' => 'Asia/Tokyo']))
            ->assertForbidden();

        $this->assertNull(TimezoneRegistry::stored());
    }

    public function test_a_guest_cannot_change_the_timezone(): void
    {
        $this->post('/settings/system', $this->payload(['app_timezone' => 'Asia/Tokyo']))
            ->assertRedirect(route('login'));

        $this->assertNull(TimezoneRegistry::stored());
    }

    public function test_the_page_offers_the_current_zone_and_the_full_region_list(): void
    {
        TimezoneRegistry::save('Asia/Tokyo');

        $props = $this->actingAs($this->admin)
            ->get('/settings/system')
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props'];

        $this->assertSame('Asia/Tokyo', $props['settings']['app_timezone']);
        $this->assertArrayHasKey('Europe', $props['timezones']);
        $this->assertContains('Europe/Warsaw', $props['timezones']['Europe']);
    }

    /**
     * The reason ApplyPlantTimezone exists: AppServiceProvider::boot() applies the
     * zone once per worker, so a worker that booted before the change would keep
     * rendering the old one. Simulated here by leaving a stale cached value and a
     * stale config behind, then making an ordinary request.
     */
    public function test_a_request_re_applies_a_zone_changed_by_another_worker(): void
    {
        TimezoneRegistry::save('Asia/Tokyo');
        TimezoneRegistry::apply();

        // What the "other worker" state looks like: the row says Tokyo, this
        // process still believes it is on UTC.
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        $props = $this->actingAs($this->admin)
            ->get('/settings')
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props'];

        $this->assertSame('Asia/Tokyo', $props['timezone']);
    }

    /**
     * The other half of that: once the setting is gone (a system reset truncates
     * `system_settings`), a worker that had already applied a zone must fall back
     * to the one it booted on — APP_TIMEZONE — not stay on the removed value.
     */
    public function test_removing_the_setting_puts_a_worker_back_on_the_env_zone(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);
        TimezoneRegistry::flush();
        TimezoneRegistry::apply();          // boot: nothing stored yet

        TimezoneRegistry::save('Asia/Tokyo');
        TimezoneRegistry::apply();
        $this->assertSame('Asia/Tokyo', config('app.timezone'));

        DB::table('system_settings')->where('key', TimezoneRegistry::SETTING_KEY)->delete();

        TimezoneRegistry::refresh();

        $this->assertSame('Europe/Warsaw', config('app.timezone'));
        $this->assertSame('Europe/Warsaw', date_default_timezone_get());
    }
}
