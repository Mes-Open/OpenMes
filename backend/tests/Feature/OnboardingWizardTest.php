<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What an admin meets the first time they sign in to an empty system.
 *
 * This used to be a five-screen wizard — pick your feature modules, then build
 * a line, a product, a routing and a work order by hand. It asked which parts
 * of the product you wanted before you had seen any of them, and the four build
 * steps produced one of each: not enough to show anything, and thrown away as
 * soon as real data arrived.
 *
 * It is now one screen with one decision, and both answers are on it.
 */
class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // A fresh install: nothing built, first run still pending.
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(false), 'updated_at' => now()],
        );

        $this->configureAdminCredentials();
    }

    private function configureAdminCredentials(): void
    {
        config()->set('openmmes.admin.username', 'admin');
        config()->set('openmmes.admin.email', 'admin@example.test');
        config()->set('openmmes.admin.password', 'Admin1234!');
    }

    public function test_a_fresh_install_offers_the_first_run_screen(): void
    {
        $this->actingAs($this->admin)
            ->get(route('onboarding.index'))
            ->assertOk();
    }

    public function test_it_offers_every_example_company(): void
    {
        $response = $this->actingAs($this->admin)->get(route('onboarding.index'));

        $datasets = collect($response->viewData('page')['props']['datasets'] ?? []);

        // Offering one company would make this a prompt rather than a choice.
        $this->assertGreaterThanOrEqual(2, $datasets->count());
        $this->assertEqualsCanonicalizing(
            \App\Support\DemoDatasetRegistry::keys(),
            $datasets->pluck('key')->all(),
        );
    }

    public function test_installing_an_example_company_fills_the_system(): void
    {
        $this->actingAs($this->admin)
            ->post(route('onboarding.store'), ['dataset' => 'print_shop'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_types', ['code' => 'TSHIRT']);
        $this->assertGreaterThan(0, Line::count());
    }

    public function test_installing_records_which_company_and_finishes_first_run(): void
    {
        $this->actingAs($this->admin)->post(route('onboarding.store'), ['dataset' => 'print_shop']);

        // Settings → Data reads this back to say what is installed, and the
        // screen must not reappear on the next sign-in.
        $this->assertSame(
            'print_shop',
            json_decode(DB::table('system_settings')->where('key', 'sample_data_loaded')->value('value'), true),
        );
        $this->assertTrue(
            json_decode(DB::table('system_settings')->where('key', 'onboarding_completed')->value('value'), true),
        );
    }

    public function test_starting_empty_installs_nothing_and_does_not_ask_again(): void
    {
        $this->actingAs($this->admin)
            ->post(route('onboarding.skip'))
            ->assertRedirect(route('admin.dashboard'));

        // "Not now" has to mean nothing was written, or it is not a real
        // alternative to installing.
        $this->assertSame(0, ProductType::count());
        $this->assertSame(0, Line::count());
        $this->assertDatabaseMissing('system_settings', ['key' => 'sample_data_loaded']);

        $this->assertTrue(
            json_decode(DB::table('system_settings')->where('key', 'onboarding_completed')->value('value'), true),
        );
    }

    public function test_an_unknown_company_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('onboarding.store'), ['dataset' => 'ArbitrarySeeder'])
            ->assertSessionHasErrors('dataset');

        $this->assertSame(0, ProductType::count());
    }

    public function test_the_screen_steps_aside_once_first_run_is_done(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(true), 'updated_at' => now()],
        );

        $this->actingAs($this->admin)
            ->get(route('onboarding.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_non_admin_cannot_reach_it(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get(route('onboarding.index'))
            ->assertForbidden();
    }

    public function test_the_removed_wizard_steps_are_gone(): void
    {
        // The five-screen flow is deleted, not hidden: a bookmark or a stale
        // link must 404 rather than resurrect half a wizard.
        foreach (['/onboarding/modules', '/onboarding/step/1', '/onboarding/step/4', '/onboarding/complete'] as $path) {
            $this->actingAs($this->admin)->get($path)->assertNotFound();
        }
    }
}
