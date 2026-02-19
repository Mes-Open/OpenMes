<?php

namespace Tests\Feature;

use App\Models\Crew;
use App\Models\Division;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DivisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Factory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->factory = Factory::create([
            'code'      => 'FAC01',
            'name'      => 'Test Factory',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_divisions(): void
    {
        Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Alpha Division',
            'is_active'  => true,
        ]);
        Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV02',
            'name'       => 'Beta Division',
            'is_active'  => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.divisions.index'));

        $response->assertStatus(200);
        $response->assertSee('Alpha Division');
        $response->assertSee('Beta Division');
    }

    public function test_admin_can_create_division(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.divisions.store'), [
            'factory_id'  => $this->factory->id,
            'code'        => 'DIV01',
            'name'        => 'Assembly Division',
            'description' => 'Main assembly area',
            'is_active'   => true,
        ]);

        $response->assertRedirect(route('admin.divisions.index'));
        $this->assertDatabaseHas('divisions', [
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Assembly Division',
        ]);
    }

    public function test_admin_can_update_division(): void
    {
        $division = Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Old Division Name',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.divisions.update', $division), [
            'factory_id'  => $this->factory->id,
            'code'        => 'DIV01',
            'name'        => 'New Division Name',
            'description' => 'Updated description',
            'is_active'   => true,
        ]);

        $response->assertRedirect(route('admin.divisions.index'));
        $this->assertDatabaseHas('divisions', [
            'id'   => $division->id,
            'name' => 'New Division Name',
        ]);
    }

    public function test_admin_can_delete_division(): void
    {
        $division = Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Empty Division',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.divisions.destroy', $division));

        $response->assertRedirect(route('admin.divisions.index'));
        $this->assertDatabaseMissing('divisions', ['id' => $division->id]);
    }

    public function test_cannot_delete_division_with_crews(): void
    {
        $division = Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Division With Crews',
            'is_active'  => true,
        ]);

        Crew::create([
            'code'        => 'CRW01',
            'name'        => 'Crew Alpha',
            'division_id' => $division->id,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.divisions.destroy', $division));

        $response->assertRedirect(route('admin.divisions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('divisions', ['id' => $division->id]);
    }

    public function test_admin_can_toggle_active(): void
    {
        $division = Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Active Division',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.divisions.toggle-active', $division));

        $response->assertRedirect(route('admin.divisions.index'));
        $this->assertDatabaseHas('divisions', [
            'id'        => $division->id,
            'is_active' => false,
        ]);

        // Toggle again — should restore active state.
        $this->actingAs($this->admin)
            ->post(route('admin.divisions.toggle-active', $division));

        $this->assertDatabaseHas('divisions', [
            'id'        => $division->id,
            'is_active' => true,
        ]);
    }

    public function test_division_code_unique_per_factory(): void
    {
        Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'First Division',
            'is_active'  => true,
        ]);

        // The controller validates code as globally unique across all divisions.
        $response = $this->actingAs($this->admin)->post(route('admin.divisions.store'), [
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Duplicate Division',
            'is_active'  => true,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('divisions', 1);
    }

    public function test_division_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.divisions.store'), [
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'is_active'  => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_division_code_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.divisions.store'), [
            'factory_id' => $this->factory->id,
            'name'       => 'No Code Division',
            'is_active'  => true,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_factory_id_must_exist_if_provided(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.divisions.store'), [
            'factory_id' => 99999,
            'code'       => 'DIV01',
            'name'       => 'Bad Factory Division',
            'is_active'  => true,
        ]);

        $response->assertSessionHasErrors('factory_id');
    }

    public function test_update_allows_same_code_for_same_division(): void
    {
        $division = Division::create([
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Original Name',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.divisions.update', $division), [
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Renamed Division',
            'is_active'  => true,
        ]);

        $response->assertRedirect(route('admin.divisions.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('divisions', ['id' => $division->id, 'name' => 'Renamed Division']);
    }

    public function test_guest_cannot_access_divisions(): void
    {
        $response = $this->get(route('admin.divisions.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_division(): void
    {
        $response = $this->post(route('admin.divisions.store'), [
            'factory_id' => $this->factory->id,
            'code'       => 'DIV01',
            'name'       => 'Ghost Division',
            'is_active'  => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('divisions', ['code' => 'DIV01']);
    }
}
