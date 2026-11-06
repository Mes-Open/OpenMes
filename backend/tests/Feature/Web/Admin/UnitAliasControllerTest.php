<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\UnitAlias;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Settings → Units of Measure: the alias dictionary ERP imports translate
 * unit names through. Admin-only, per tenant, and an alias is unique by what
 * an import would match on — not by how it happens to be typed.
 */
class UnitAliasControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_the_defaults_are_seeded_by_the_migration(): void
    {
        $this->assertDatabaseHas('unit_aliases', ['alias_normalized' => 'units', 'unit' => 'pcs', 'tenant_id' => null]);
        $this->assertDatabaseHas('unit_aliases', ['alias_normalized' => 'kgm', 'unit' => 'kg', 'tenant_id' => null]);
        $this->assertDatabaseHas('unit_aliases', ['alias_normalized' => 'szt', 'unit' => 'pcs', 'tenant_id' => null]);
    }

    public function test_the_seeder_adds_no_duplicates(): void
    {
        $before = UnitAlias::withoutGlobalScopes()->count();

        $this->seed(UnitAliasSeeder::class);

        $this->assertSame($before, UnitAlias::withoutGlobalScopes()->count());
    }

    public function test_the_seeder_gives_every_tenant_its_own_copy(): void
    {
        $tenant = Tenant::create(['name' => 'Plant B']);

        $this->seed(UnitAliasSeeder::class);

        $this->assertDatabaseHas('unit_aliases', ['alias_normalized' => 'kgm', 'unit' => 'kg', 'tenant_id' => $tenant->id]);
    }

    public function test_admin_sees_the_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.unit-aliases.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/unit-aliases/Index')
                ->where('units', fn ($units) => collect($units)->contains('pcs') && collect($units)->contains('kg')));
    }

    public function test_admin_can_create_an_alias(): void
    {
        UnitAlias::withoutGlobalScopes()->where('alias_normalized', 'stück')->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.unit-aliases.store'), ['alias' => ' Stück ', 'unit' => 'pcs'])
            ->assertRedirect(route('admin.unit-aliases.index'))
            ->assertSessionHas('success', 'Unit alias created successfully.');

        $this->assertDatabaseHas('unit_aliases', [
            'alias' => 'Stück',
            'alias_normalized' => 'stück',
            'unit' => 'pcs',
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_update_an_alias(): void
    {
        $alias = UnitAlias::create(['alias' => 'Fass', 'unit' => 'pcs']);

        $this->actingAs($this->admin)
            ->put(route('admin.unit-aliases.update', $alias), ['alias' => 'Fass', 'unit' => 'l'])
            ->assertRedirect(route('admin.unit-aliases.index'));

        $this->assertSame('l', $alias->fresh()->unit);
    }

    public function test_an_alias_can_be_saved_unchanged(): void
    {
        $alias = UnitAlias::create(['alias' => 'Fass', 'unit' => 'l']);

        $this->actingAs($this->admin)
            ->put(route('admin.unit-aliases.update', $alias), ['alias' => 'FASS', 'unit' => 'l'])
            ->assertSessionHasNoErrors();

        $this->assertSame('FASS', $alias->fresh()->alias);
    }

    public function test_admin_can_delete_an_alias_and_reuse_it(): void
    {
        $alias = UnitAlias::create(['alias' => 'Fass', 'unit' => 'l']);

        $this->actingAs($this->admin)
            ->delete(route('admin.unit-aliases.destroy', $alias))
            ->assertRedirect(route('admin.unit-aliases.index'));

        $this->assertSoftDeleted('unit_aliases', ['id' => $alias->id]);

        // The unique index is partial, so a deleted alias frees its name.
        $this->actingAs($this->admin)
            ->post(route('admin.unit-aliases.store'), ['alias' => 'Fass', 'unit' => 'l'])
            ->assertSessionHasNoErrors();
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing alias' => [['unit' => 'pcs'], 'alias'],
            'missing unit' => [['alias' => 'Fass'], 'unit'],
            'alias too long' => [['alias' => str_repeat('a', 21), 'unit' => 'pcs'], 'alias'],
            'unit too long' => [['alias' => 'Fass', 'unit' => str_repeat('a', 21)], 'unit'],
            'markup in alias' => [['alias' => '<script>', 'unit' => 'pcs'], 'alias'],
            'markup in unit' => [['alias' => 'Fass', 'unit' => 'p"cs'], 'unit'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_refused(array $payload, string $field): void
    {
        $before = UnitAlias::withoutGlobalScopes()->count();

        $this->actingAs($this->admin)
            ->post(route('admin.unit-aliases.store'), $payload)
            ->assertSessionHasErrors($field);

        $this->assertSame($before, UnitAlias::withoutGlobalScopes()->count());
    }

    public function test_invalid_input_is_a_422_for_json_callers(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.unit-aliases.store'), ['alias' => '', 'unit' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alias', 'unit']);
    }

    public function test_an_alias_is_unique_regardless_of_case_and_trailing_dot(): void
    {
        // "szt" is a default; "SZT." is the same alias to an import.
        $this->actingAs($this->admin)
            ->post(route('admin.unit-aliases.store'), ['alias' => 'SZT.', 'unit' => 'pcs'])
            ->assertSessionHasErrors(['alias' => 'This alias is already defined.']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.unit-aliases.index'))->assertRedirect(route('login'));
        $this->post(route('admin.unit-aliases.store'), ['alias' => 'Fass', 'unit' => 'l'])->assertRedirect(route('login'));
    }

    public function test_supervisors_and_operators_are_refused(): void
    {
        $alias = UnitAlias::create(['alias' => 'Fass', 'unit' => 'l']);

        foreach (['Supervisor', 'Operator'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get(route('admin.unit-aliases.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.unit-aliases.store'), ['alias' => 'Kiste', 'unit' => 'pcs'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.unit-aliases.update', $alias), ['alias' => 'Fass', 'unit' => 'pcs'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.unit-aliases.destroy', $alias))->assertForbidden();
        }

        $this->assertSame('l', $alias->fresh()->unit);
        $this->assertDatabaseMissing('unit_aliases', ['alias' => 'Kiste']);
    }

    public function test_an_admin_cannot_touch_another_tenants_alias(): void
    {
        $tenantA = Tenant::create(['name' => 'A']);
        $tenantB = Tenant::create(['name' => 'B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');

        $foreign = UnitAlias::create(['alias' => 'Fass', 'unit' => 'l', 'tenant_id' => $tenantB->id]);

        $this->actingAs($adminA)->get(route('admin.unit-aliases.edit', $foreign->id))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.unit-aliases.update', $foreign->id), ['alias' => 'Fass', 'unit' => 'pcs'])->assertNotFound();
        // A DELETE of a record the admin cannot see is answered like one that is
        // already gone (bootstrap/app.php): back to the list, nothing deleted.
        $this->actingAs($adminA)->delete(route('admin.unit-aliases.destroy', $foreign->id))->assertRedirect();

        $this->assertSame('l', $foreign->fresh()->unit);
        $this->assertNotSoftDeleted('unit_aliases', ['id' => $foreign->id]);

        // …and the name is free in tenant A: uniqueness is per tenant.
        $this->actingAs($adminA)
            ->post(route('admin.unit-aliases.store'), ['alias' => 'Fass', 'unit' => 'l'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('unit_aliases', ['alias' => 'Fass', 'tenant_id' => $tenantA->id]);
    }
}
