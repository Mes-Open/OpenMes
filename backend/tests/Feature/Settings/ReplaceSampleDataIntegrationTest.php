<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Real schema replacement must not run inside RefreshDatabase's transaction. */
class ReplaceSampleDataIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        // This test replaces the schema. Drop disposable test tables rather than
        // exercising unrelated historical down() migrations during cleanup.
        try {
            $this->artisan('db:wipe', ['--force' => true]);
        } finally {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
            parent::tearDown();
        }
    }

    public function test_replacement_reseeds_roles_recreates_admin_and_preserves_modules(): void
    {
        config(['openmmes.admin.username' => 'replacement-admin', 'openmmes.admin.email' => 'replacement@example.test', 'openmmes.admin.password' => 'Replacement123!']);
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['username' => 'old-admin']);
        $admin->assignRole('Admin');
        DB::table('system_settings')->insert([
            ['key' => 'sample_data_loaded', 'value' => json_encode('print_shop')],
            ['key' => 'modules_enabled', 'value' => json_encode(['core', 'quality'])],
        ]);

        $this->actingAs($admin)->post('/settings/sample-data', ['dataset' => 'bakery', 'replace' => '1'])
            ->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertDatabaseMissing('users', ['username' => 'old-admin']);
        $this->assertDatabaseHas('product_types', ['code' => 'BREAD_WHEAT']);
        $replacement = User::where('username', 'replacement-admin')->firstOrFail();
        $this->assertTrue($replacement->hasRole('Admin'));
        $this->assertTrue(Hash::check('Replacement123!', $replacement->password));
        $this->assertAuthenticatedAs($replacement);
        $this->assertSame('bakery', json_decode(DB::table('system_settings')->where('key', 'sample_data_loaded')->value('value'), true));
        $this->assertSame(['core', 'quality'], json_decode(DB::table('system_settings')->where('key', 'modules_enabled')->value('value'), true));
    }
}
