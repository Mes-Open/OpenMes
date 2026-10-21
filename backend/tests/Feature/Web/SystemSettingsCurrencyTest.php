<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\CurrencyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One currency per installation, chosen in Settings → System from the
 * CurrencyRegistry allowlist — on the web form and the API alike.
 */
class SystemSettingsCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        CurrencyRegistry::forget();
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

    private function storedCurrency(): mixed
    {
        return json_decode(DB::table('system_settings')->where('key', 'default_currency')->value('value'), true);
    }

    public function test_new_installation_defaults_to_pln(): void
    {
        $this->assertSame('PLN', $this->storedCurrency());
        $this->assertSame('PLN', CurrencyRegistry::current());
    }

    public function test_admin_can_change_the_currency(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['default_currency' => 'eur']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.system'));

        $this->assertSame('EUR', $this->storedCurrency());
        $this->assertSame('EUR', CurrencyRegistry::current());
    }

    public function test_the_new_currency_is_shared_with_every_page(): void
    {
        $this->actingAs($this->admin)->post('/settings/system', $this->payload(['default_currency' => 'USD']));

        $this->actingAs($this->admin)->get('/settings/system')
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency', 'USD')
                ->where('settings.default_currency', 'USD'));
    }

    public function test_unsupported_codes_are_rejected(): void
    {
        foreach (['XYZ', 'EURO', '12A', ''] as $code) {
            $this->actingAs($this->admin)
                ->postJson('/settings/system', $this->payload(['default_currency' => $code === '' ? ['x'] : $code]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('default_currency');
        }

        $this->assertSame('PLN', $this->storedCurrency());
    }

    public function test_rejection_message_names_iso_4217(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/system', $this->payload(['default_currency' => 'XYZ']))
            ->assertJsonPath('errors.default_currency.0', 'The selected currency is not a supported ISO 4217 code.');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post('/settings/system', $this->payload(['default_currency' => 'EUR']))
            ->assertForbidden();

        $this->assertSame('PLN', $this->storedCurrency());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/settings/system', $this->payload(['default_currency' => 'EUR']))
            ->assertRedirect('/login');

        $this->assertSame('PLN', $this->storedCurrency());
    }

    public function test_page_offers_the_allowlist(): void
    {
        $this->actingAs($this->admin)->get('/settings/system')
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/System')
                ->where('settings.default_currency', 'PLN')
                ->has('currencies', count(CurrencyRegistry::CURRENCIES))
                ->where('currencies.0', ['code' => 'PLN', 'name' => 'Polish Złoty'])
                ->where('currencies.1', ['code' => 'EUR', 'name' => 'Euro']));
    }

    public function test_a_stored_code_outside_the_allowlist_reads_as_pln(): void
    {
        DB::table('system_settings')->where('key', 'default_currency')->update(['value' => json_encode('ZZZ')]);
        CurrencyRegistry::forget();

        $this->assertSame('PLN', CurrencyRegistry::current());
        $this->actingAs($this->admin)->get('/settings/system')
            ->assertInertia(fn (Assert $page) => $page->where('settings.default_currency', 'PLN'));
    }

    public function test_migration_normalises_existing_installations(): void
    {
        $migration = require database_path('migrations/2026_10_21_100000_normalise_default_currency_setting.php');

        DB::table('system_settings')->where('key', 'default_currency')->update(['value' => json_encode('eur')]);
        $migration->up();
        $this->assertSame('EUR', $this->storedCurrency());

        DB::table('system_settings')->where('key', 'default_currency')->update(['value' => json_encode('xyz')]);
        $migration->up();
        $this->assertSame('PLN', $this->storedCurrency());

        DB::table('system_settings')->where('key', 'default_currency')->delete();
        $migration->up();
        $this->assertSame('PLN', $this->storedCurrency());

        // Idempotent.
        $migration->up();
        $this->assertSame(1, DB::table('system_settings')->where('key', 'default_currency')->count());
    }
}
