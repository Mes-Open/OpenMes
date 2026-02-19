<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_factories(): void
    {
        Factory::create(['code' => 'FAC01', 'name' => 'Alpha Factory', 'is_active' => true]);
        Factory::create(['code' => 'FAC02', 'name' => 'Beta Factory', 'is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.factories.index'));

        $response->assertStatus(200);
        $response->assertSee('Alpha Factory');
        $response->assertSee('Beta Factory');
    }

    public function test_admin_can_create_factory(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.factories.store'), [
            'code'        => 'FAC01',
            'name'        => 'Test Factory',
            'description' => 'A factory used for testing',
            'is_active'   => true,
        ]);

        $response->assertRedirect(route('admin.factories.index'));
        $this->assertDatabaseHas('factories', [
            'code'      => 'FAC01',
            'name'      => 'Test Factory',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_factory(): void
    {
        $factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Old Name',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.factories.update', $factory), [
            'code'        => 'FAC01',
            'name'        => 'New Name',
            'description' => 'Updated description',
            'is_active'   => true,
        ]);

        $response->assertRedirect(route('admin.factories.index'));
        $this->assertDatabaseHas('factories', [
            'id'   => $factory->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_toggle_active(): void
    {
        $factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Active Factory',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.factories.toggle-active', $factory));

        $response->assertRedirect(route('admin.factories.index'));
        $this->assertDatabaseHas('factories', [
            'id'        => $factory->id,
            'is_active' => false,
        ]);

        // Toggle again — should go back to active.
        $this->actingAs($this->admin)
            ->post(route('admin.factories.toggle-active', $factory));

        $this->assertDatabaseHas('factories', [
            'id'        => $factory->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_factory_without_divisions(): void
    {
        $factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Empty Factory',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.factories.destroy', $factory));

        $response->assertRedirect(route('admin.factories.index'));
        $this->assertDatabaseMissing('factories', ['id' => $factory->id]);
    }

    public function test_cannot_delete_factory_with_divisions(): void
    {
        $factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Factory With Divisions',
            'is_active' => true,
        ]);

        Division::create([
            'factory_id' => $factory->id,
            'code'       => 'DIV01',
            'name'       => 'Division One',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.factories.destroy', $factory));

        $response->assertRedirect(route('admin.factories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('factories', ['id' => $factory->id]);
    }

    public function test_factory_code_must_be_unique(): void
    {
        Factory::create(['code' => 'FAC01', 'name' => 'Existing Factory', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->post(route('admin.factories.store'), [
            'code'      => 'FAC01',
            'name'      => 'Another Factory',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('factories', 1);
    }

    public function test_factory_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.factories.store'), [
            'code'      => 'FAC01',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_factory_code_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.factories.store'), [
            'name'      => 'Test Factory',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_update_allows_same_code_for_same_factory(): void
    {
        $factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Original Name',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.factories.update', $factory), [
            'code'      => 'FAC01',
            'name'      => 'Renamed Factory',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.factories.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('factories', ['id' => $factory->id, 'name' => 'Renamed Factory']);
    }

    public function test_guest_cannot_access_factories(): void
    {
        $response = $this->get(route('admin.factories.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_factory(): void
    {
        $response = $this->post(route('admin.factories.store'), [
            'code'      => 'FAC01',
            'name'      => 'Test Factory',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('factories', ['code' => 'FAC01']);
    }
}
