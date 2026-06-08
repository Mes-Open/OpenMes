<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The language whitelist is derived from config('app.available_locales')
 * (single source of truth) — adding a locale there must make it valid
 * without touching the controller, and removing it must reject it.
 */
class SystemSettingsLanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
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

    public function test_configured_locale_is_accepted(): void
    {
        foreach (array_keys(config('app.available_locales')) as $locale) {
            $response = $this->actingAs($this->admin)
                ->post('/settings/system', $this->payload(['language' => $locale]));

            $response->assertSessionHasNoErrors();
        }
    }

    public function test_unconfigured_locale_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['language' => 'xx']))
            ->assertSessionHasErrors('language');
    }

    public function test_newly_configured_locale_is_accepted_without_controller_change(): void
    {
        // Simulate adding a language in config/app.php.
        config(['app.available_locales' => config('app.available_locales') + ['nl' => 'Nederlands']]);

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['language' => 'nl']))
            ->assertSessionHasNoErrors();
    }

    public function test_language_whitelist_matches_switcher_options(): void
    {
        // The dropdown shown on the settings page must use the same source.
        $response = $this->actingAs($this->admin)->get('/settings/system');

        $response->assertOk();
        $locales = $response->getOriginalContent()->getData()['page']['props']['availableLocales'] ?? null;
        $this->assertSame(config('app.available_locales'), $locales);
    }

    public function test_changing_language_in_settings_updates_session_locale(): void
    {
        // The session override is what SetLocale reads first, so saving the
        // language must align it for the change to take effect on reload.
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['language' => 'pl']))
            ->assertSessionHas('locale', 'pl');

        $this->assertDatabaseHas('system_settings', ['key' => 'language', 'value' => json_encode('pl')]);
    }

    public function test_labor_costing_settings_are_persisted(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload([
                'standard_weekly_hours' => 38,
                'default_currency' => 'eur',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_settings', ['key' => 'standard_weekly_hours', 'value' => json_encode(38.0)]);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_currency', 'value' => json_encode('EUR')]);
    }

    public function test_invalid_currency_length_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['default_currency' => 'EURO']))
            ->assertSessionHasErrors('default_currency');
    }
}
