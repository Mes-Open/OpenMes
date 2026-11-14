<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
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

    public function test_the_picker_shows_the_stored_system_language_not_the_session_choice(): void
    {
        // System language is Polish; this admin switched only their own session
        // to English on the login screen.
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $props = $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->get('/settings/system')
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props'];

        // The field sets the plant-wide language, and the whole form is posted
        // on every save: showing the session choice here made any save
        // overwrite the plant language with it.
        $this->assertSame('pl', $props['settings']['language']);
    }

    public function test_saving_other_settings_keeps_the_system_language_and_the_admins_choice(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        // Exactly what the page posts from any tab but General: no language.
        $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->post('/settings/system', $this->payload(['schedule_view_mode' => 'monthly']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('locale', 'en');

        $this->assertSame('pl', json_decode(DB::table('system_settings')->where('key', 'language')->value('value'), true));
    }

    public function test_saving_the_unchanged_system_language_applies_it_to_the_admins_session(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        // The picker shows Polish while this admin switched their own session to
        // English; saving the General tab must show the language they saved.
        $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->post('/settings/system', $this->payload(['language' => 'pl']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('locale', 'pl');

        $this->assertSame('pl', json_decode(DB::table('system_settings')->where('key', 'language')->value('value'), true));
    }

    public function test_a_form_without_the_language_does_not_reset_it_to_english(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('pl', json_decode(DB::table('system_settings')->where('key', 'language')->value('value'), true));
    }

    public function test_changing_language_in_settings_updates_session_locale(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('en')]);

        // The session choice is what SetLocale applies last, so a changed system
        // language must align it for the change to show on reload — even over
        // a stale login-screen choice (#205).
        $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->post('/settings/system', $this->payload(['language' => 'pl']))
            ->assertSessionHas('locale', 'pl');

        $this->assertSame('pl', json_decode(DB::table('system_settings')->where('key', 'language')->value('value'), true));
    }

    public function test_the_system_language_reaches_a_guest_without_a_session_choice(): void
    {
        // Under Octane the boot-time locale is reused for every request of a
        // worker; config('app.locale') stands in for that stale value here.
        config(['app.locale' => 'en']);
        app()->setLocale('en');
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $this->get('/login')->assertOk();

        $this->assertSame('pl', app()->getLocale());
    }

    public function test_a_session_choice_still_wins_over_the_system_language(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $this->withSession(['locale' => 'en'])->get('/login')->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_labor_costing_settings_are_persisted(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload([
                'standard_weekly_hours' => 38,
                'default_currency' => 'eur',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(38.0, (float) json_decode(DB::table('system_settings')->where('key', 'standard_weekly_hours')->value('value'), true));
        $this->assertSame('EUR', json_decode(DB::table('system_settings')->where('key', 'default_currency')->value('value'), true));
    }

    public function test_invalid_currency_length_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['default_currency' => 'EURO']))
            ->assertSessionHasErrors('default_currency');
    }

    /**
     * The system settings page must render even when no rows exist in
     * `system_settings` yet (fresh tenant). Previously a missing key caused
     * "Attempt to read property 'value' on null" → HTTP 500. Regression guard.
     */
    public function test_system_settings_page_renders_with_empty_settings_table(): void
    {
        DB::table('system_settings')->truncate();

        $this->actingAs($this->admin)
            ->get('/settings/system')
            ->assertStatus(200);
    }

    public function test_settings_index_renders_with_empty_settings_table(): void
    {
        DB::table('system_settings')->truncate();

        $this->actingAs($this->admin)
            ->get('/settings')
            ->assertStatus(200);
    }
}
