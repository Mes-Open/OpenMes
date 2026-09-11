<?php

namespace Tests\Feature\Settings;

use App\Models\ProductType;
use App\Models\User;
use App\Support\DemoDatasetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    /**
     * The replacement path refuses to wipe unless it knows how to recreate an
     * admin afterwards, so a test that exercises it has to configure that.
     */
    private function configureAdminCredentials(): void
    {
        config()->set('openmmes.admin.username', 'admin');
        config()->set('openmmes.admin.email', 'admin@example.test');
        config()->set('openmmes.admin.password', 'Admin1234!');
    }

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
        $this->assertDatabaseMissing('product_types', ['code' => 'SHAFT40']);
    }

    public function test_each_dataset_installs_its_own_plant(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/sample-data', ['dataset' => 'machine_shop'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_types', ['code' => 'SHAFT40']);
        $this->assertDatabaseMissing('product_types', ['code' => 'TSHIRT']);
    }

    public function test_replacing_wipes_the_database_then_installs_the_chosen_company(): void
    {
        $this->configureAdminCredentials();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);

        // Artisan is faked here rather than letting the wipe run: migrate:fresh
        // drops the schema mid-test, which breaks the transaction RefreshDatabase
        // is holding and takes every later test with it. What matters is the
        // sequence — wipe, reference data, then the chosen plant.
        $called = [];
        Artisan::shouldReceive('call')->andReturnUsing(function ($command, $params = []) use (&$called) {
            $called[] = $command.' '.($params['--class'] ?? '');

            return 0;
        });

        $this->actingAs($admin)
            ->post('/settings/sample-data', ['dataset' => 'bakery', 'replace' => '1'])
            ->assertSessionHas('success');

        $this->assertContains('migrate:fresh ', $called, 'The database was not wiped before reinstalling.');
        $this->assertContains('db:seed ', $called, 'Reference data was not reseeded after the wipe.');

        foreach (DemoDatasetRegistry::seedersFor('bakery') as $seeder) {
            $this->assertContains("db:seed {$seeder}", $called, "{$seeder} never ran.");
        }
    }

    public function test_replacing_records_the_new_company(): void
    {
        $this->configureAdminCredentials();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);

        Artisan::shouldReceive('call')->andReturn(0);

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'bakery', 'replace' => '1']);

        $stored = DB::table('system_settings')->where('key', 'sample_data_loaded')->value('value');

        $this->assertSame('bakery', json_decode($stored, true));
    }

    public function test_replacing_leaves_an_admin_able_to_sign_in(): void
    {
        $this->configureAdminCredentials();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);

        Artisan::shouldReceive('call')->andReturn(0);

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'bakery', 'replace' => '1']);

        // The real wipe drops the users table with everything else, so the
        // handler recreates the admin from the configured credentials. Without
        // it, switching company would lock the operator out of their instance.
        $recreated = User::where('username', 'admin')->first();

        $this->assertNotNull($recreated, 'No admin account was recreated.');
        $this->assertTrue($recreated->hasRole('Admin'));
        $this->assertTrue(Hash::check('Admin1234!', $recreated->password));
    }

    public function test_replacing_refuses_when_it_cannot_recreate_an_admin(): void
    {
        config()->set('openmmes.admin.username', null);

        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);

        $this->actingAs($admin)
            ->post('/settings/sample-data', ['dataset' => 'bakery', 'replace' => '1'])
            ->assertSessionHas('error');

        // Refusing is the safe outcome: wiping without being able to recreate
        // an admin would lock the operator out of their own instance.
        $this->assertDatabaseHas('product_types', ['code' => 'TSHIRT']);
    }

    public function test_a_second_load_without_confirmation_is_still_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);
        $before = ProductType::count();

        // No replace flag: an unconfirmed post must not wipe anything.
        $this->actingAs($admin)
            ->post('/settings/sample-data', ['dataset' => 'bakery'])
            ->assertSessionHas('info');

        $this->assertSame($before, ProductType::count());
        $this->assertDatabaseHas('product_types', ['code' => 'TSHIRT']);
    }

    public function test_a_second_load_is_refused_rather_than_stacked(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'print_shop']);
        $before = ProductType::count();

        $this->actingAs($admin)
            ->post('/settings/sample-data', ['dataset' => 'machine_shop'])
            ->assertSessionHas('info');

        // Nothing from the second plant leaked in.
        $this->assertSame($before, ProductType::count());
        $this->assertDatabaseMissing('product_types', ['code' => 'SHAFT40']);
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
