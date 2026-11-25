<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Models\User;
use App\Support\ProductionTargetSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A module may narrow an installation setting to where the request is — one
 * plant of several running to its own target — through `settings.value`.
 * The Settings page keeps showing and saving the installation's own value.
 */
class SettingsValueFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_module_can_narrow_a_production_target(): void
    {
        ProductionTargetSettings::save(2.0, 95.0);

        app(FilterRegistry::class)->addFilter('settings.value', fn ($value, array $ctx) => $ctx['key'] === ProductionTargetSettings::SCRAP_KEY ? 4.5 : $value);

        $this->assertSame(4.5, ProductionTargetSettings::maxScrapRate());
        $this->assertSame(95.0, ProductionTargetSettings::minFirstPassYield());
        $this->assertSame(2.0, ProductionTargetSettings::installationValue(ProductionTargetSettings::SCRAP_KEY));

        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get('/settings/system?tab=production')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('productionTargets.max_scrap_rate_pct', 2));
    }

    public function test_an_out_of_range_answer_falls_back_to_the_installation_value(): void
    {
        ProductionTargetSettings::save(2.0, 95.0);
        app(FilterRegistry::class)->addFilter('settings.value', fn () => 250);

        $this->assertSame(2.0, ProductionTargetSettings::maxScrapRate());
    }
}
