<?php

namespace Tests\Feature\Settings;

use App\Models\ProductType;
use App\Models\User;
use App\Support\DemoDatasetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Choosing which example company to install.
 *
 * The datasets are alternative plants, not layers: loading one is meant to make
 * the app look like that business. Seeding a second on top would leave two sets
 * of lines and products side by side, so the choice is offered once per
 * database and refused afterwards.
 */
class LoadSampleDataTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    public function test_an_admin_can_load_a_chosen_company(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sample-data', ['dataset' => 'print_shop'])
            ->assertRedirect('/settings/system')
            ->assertSessionHas('success');

        // The print shop's own products, not the other dataset's.
        $this->assertDatabaseHas('product_types', ['code' => 'TSHIRT']);
        $this->assertDatabaseMissing('product_types', ['code' => 'HEPA13_STD']);
    }

    public function test_each_dataset_installs_its_own_plant(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sample-data', ['dataset' => 'air_filter'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_types', ['code' => 'HEPA13_STD']);
        $this->assertDatabaseMissing('product_types', ['code' => 'TSHIRT']);
    }

    public function test_a_second_load_is_refused_rather_than_stacked(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);
        $before = ProductType::count();

        $this->actingAs($admin)
            ->post('/settings/sample-data', ['dataset' => 'air_filter'])
            ->assertSessionHas('info');

        // Nothing from the second plant leaked in.
        $this->assertSame($before, ProductType::count());
        $this->assertDatabaseMissing('product_types', ['code' => 'HEPA13_STD']);
    }

    public function test_it_records_which_company_was_loaded(): void
    {
        $this->actingAs($this->admin())->post('/settings/sample-data', ['dataset' => 'print_shop']);

        $stored = DB::table('system_settings')->where('key', 'sample_data_loaded')->value('value');

        // The settings screen reads this back to say which one is installed, so
        // it has to be the key rather than a bare "true".
        $this->assertSame('print_shop', json_decode($stored, true));
    }

    public function test_an_unknown_company_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sample-data', ['dataset' => 'ArbitrarySeeder'])
            ->assertSessionHasErrors('dataset');

        $this->assertDatabaseCount('product_types', 0);
    }

    public function test_the_choice_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sample-data', [])
            ->assertSessionHasErrors('dataset');
    }

    public function test_a_guest_cannot_load_sample_data(): void
    {
        $this->post('/settings/sample-data', ['dataset' => 'print_shop'])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('product_types', 0);
    }

    public function test_a_non_admin_cannot_load_sample_data(): void
    {
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post('/settings/sample-data', ['dataset' => 'print_shop'])
            ->assertForbidden();

        $this->assertDatabaseCount('product_types', 0);
    }

    public function test_every_registered_dataset_names_real_seeders(): void
    {
        foreach (DemoDatasetRegistry::keys() as $key) {
            $seeders = DemoDatasetRegistry::seedersFor($key);

            $this->assertNotEmpty($seeders, "{$key} installs nothing.");

            foreach ($seeders as $seeder) {
                // A typo here would only surface as a runtime crash mid-seed,
                // with the database half populated.
                $this->assertTrue(class_exists($seeder), "{$seeder} does not exist.");
            }
        }
    }
}
