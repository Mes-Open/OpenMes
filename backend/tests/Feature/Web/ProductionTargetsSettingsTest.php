<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\QualityCheck;
use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\ProductionTargetSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → System → Production targets: the admin-only scrap rate and
 * first-pass yield targets, saved through their own route.
 */
class ProductionTargetsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function stored(string $key): mixed
    {
        // JSON column on PostgreSQL: decode in PHP, never compare in SQL.
        $raw = DB::table('system_settings')->where('key', $key)->value('value');

        return $raw === null ? null : json_decode($raw, true);
    }

    public function test_page_shows_defaults_and_no_fpy_without_checks(): void
    {
        $this->actingAs($this->admin)
            ->get('/settings/system?tab=production')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/System')
                ->where('productionTargets.max_scrap_rate_pct', 2)
                ->where('productionTargets.min_fpy_pct', 95)
                ->where('productionTargets.current_shift_fpy', null)
                ->where('productionTargets.quality_enabled', true)
                ->where('productionTargets.plant_oee_target', 85));
    }

    public function test_page_shows_the_current_shift_fpy(): void
    {
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => true]);
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => false]);

        $this->actingAs($this->admin)
            ->get('/settings/system?tab=production')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('productionTargets.current_shift_fpy', 50));
    }

    public function test_other_tabs_open_without_the_targets_queries(): void
    {
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => true]);

        foreach (['/settings/system', '/settings/system?tab=security'] as $url) {
            $queries = $this->targetsQueriesDuring(fn () => $this->actingAs($this->admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('settings/System')
                    ->missing('productionTargets')));

            $this->assertSame([], $queries, "{$url} ran the production targets queries");
        }
    }

    public function test_production_tab_runs_the_targets_queries(): void
    {
        // The counterpart of the test above — proves the query filter sees them.
        $queries = $this->targetsQueriesDuring(fn () => $this->actingAs($this->admin)
            ->get('/settings/system?tab=production')
            ->assertOk());

        $this->assertNotEmpty($queries);
    }

    public function test_partial_reload_loads_the_targets_with_the_shift_fpy(): void
    {
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => true]);
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => false]);

        $this->actingAs($this->admin)
            ->get('/settings/system')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('productionTargets')
                ->reloadOnly('productionTargets', fn (AssertableInertia $reload) => $reload
                    ->where('productionTargets.current_shift_fpy', 50)
                    ->where('productionTargets.min_fpy_pct', 95)
                    ->where('productionTargets.plant_oee_target', 85)
                    ->missing('settings')));
    }

    public function test_targets_report_no_fpy_when_quality_module_is_disabled(): void
    {
        QualityCheck::factory()->create(['batch_id' => Batch::factory(), 'checked_at' => now(), 'all_passed' => true]);
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['quality'])));

        $this->actingAs($this->admin)
            ->get('/settings/system?tab=production')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('productionTargets.quality_enabled', false)
                ->where('productionTargets.current_shift_fpy', null));
    }

    public function test_non_admin_cannot_fetch_the_targets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        $this->actingAs($user)
            ->get('/settings/system?tab=production')
            ->assertForbidden();
    }

    /**
     * SQL touching the tables only the targets card reads (quality checks for
     * FPY, the schedule for planned minutes) while running $request.
     *
     * @return list<string>
     */
    private function targetsQueriesDuring(callable $request): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return array_values(array_filter(
            array_column($log, 'query'),
            fn (string $sql) => preg_match('/\b(quality_checks|scheduled_operations)\b/', $sql) === 1,
        ));
    }

    public function test_admin_saves_valid_targets(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/production-targets', ['max_scrap_rate_pct' => '1.5', 'min_fpy_pct' => 97])
            ->assertRedirect(route('settings.system', ['tab' => 'production']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertEquals(1.5, $this->stored(ProductionTargetSettings::SCRAP_KEY));
        $this->assertEquals(97, $this->stored(ProductionTargetSettings::FPY_KEY));

        $this->actingAs($this->admin)
            ->get('/settings/system?tab=production')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('productionTargets.max_scrap_rate_pct', 1.5)
                ->where('productionTargets.min_fpy_pct', 97));
    }

    public function test_decimal_comma_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/production-targets', ['max_scrap_rate_pct' => '2,5', 'min_fpy_pct' => '96,5'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(2.5, ProductionTargetSettings::maxScrapRate());
        $this->assertEquals(96.5, ProductionTargetSettings::minFirstPassYield());
    }

    public function test_boundaries_are_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/production-targets', ['max_scrap_rate_pct' => 0, 'min_fpy_pct' => 100])
            ->assertSessionHasNoErrors();

        $this->assertEquals(0.0, ProductionTargetSettings::maxScrapRate());
        $this->assertEquals(100.0, ProductionTargetSettings::minFirstPassYield());
    }

    public static function invalidValues(): array
    {
        return [
            'above 100' => [150],
            'negative' => [-1],
            'text' => ['abc'],
            'two decimals' => [1.25],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_scrap_rate_is_rejected(mixed $value): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/production-targets', ['max_scrap_rate_pct' => $value, 'min_fpy_pct' => 95])
            ->assertStatus(422)
            ->assertJsonValidationErrors('max_scrap_rate_pct');

        $this->assertNull($this->stored(ProductionTargetSettings::SCRAP_KEY));
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_fpy_is_rejected(mixed $value): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/production-targets', ['max_scrap_rate_pct' => 2, 'min_fpy_pct' => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('min_fpy_pct');
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/production-targets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_scrap_rate_pct', 'min_fpy_pct']);
    }

    public function test_error_message_is_readable_in_english_and_polish(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/production-targets', ['max_scrap_rate_pct' => 150, 'min_fpy_pct' => 95])
            ->assertJsonPath('errors.max_scrap_rate_pct.0', 'The max scrap rate field must not be greater than 100.');

        app()->setLocale('pl');
        $message = $this->actingAs($this->admin)
            ->withSession(['locale' => 'pl'])
            ->postJson('/settings/production-targets', ['max_scrap_rate_pct' => 150, 'min_fpy_pct' => 95])
            ->json('errors.max_scrap_rate_pct.0');

        $this->assertStringContainsString('maks. wskaźnik braków', $message);
        $this->assertStringContainsString('100', $message);
    }

    public function test_supervisor_and_operator_are_forbidden(): void
    {
        foreach (['Supervisor', 'Operator'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)
                ->post('/settings/production-targets', ['max_scrap_rate_pct' => 1, 'min_fpy_pct' => 90])
                ->assertForbidden();
        }

        $this->assertNull($this->stored(ProductionTargetSettings::SCRAP_KEY));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/settings/production-targets', ['max_scrap_rate_pct' => 1, 'min_fpy_pct' => 90])
            ->assertRedirect(route('login'));

        $this->assertNull($this->stored(ProductionTargetSettings::SCRAP_KEY));
    }

    public function test_saving_targets_leaves_other_settings_untouched(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'language'], ['value' => json_encode('pl')]);

        $this->actingAs($this->admin)
            ->post('/settings/production-targets', ['max_scrap_rate_pct' => 3, 'min_fpy_pct' => 90])
            ->assertSessionHasNoErrors();

        $this->assertSame('pl', $this->stored('language'));
    }
}
