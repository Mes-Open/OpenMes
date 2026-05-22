<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Area;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->user = User::factory()->create();
        $this->user->assignRole('Operator');
    }

    public function test_admin_can_list_sites(): void
    {
        Site::factory()->create(['name' => 'Krakow Plant', 'code' => 'SITE-KRK']);

        $response = $this->actingAs($this->admin)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertSee('Krakow Plant');
    }

    public function test_guest_cannot_list_sites(): void
    {
        $this->get(route('admin.sites.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_create_site(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.sites.store'), [
            'name'      => 'Warsaw Plant',
            'code'      => 'SITE-WAW-01',
            'city'      => 'Warsaw',
            'country'   => 'PL',
            'timezone'  => 'Europe/Warsaw',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.sites.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sites', [
            'name'    => 'Warsaw Plant',
            'code'    => 'SITE-WAW-01',
            'country' => 'PL',
        ]);
    }

    public function test_store_validates_code_unique(): void
    {
        Site::factory()->create(['code' => 'SITE-DUP']);

        $response = $this->actingAs($this->admin)->post(route('admin.sites.store'), [
            'name' => 'Other', 'code' => 'SITE-DUP',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_site(): void
    {
        $site = Site::factory()->create(['name' => 'Old Name', 'code' => 'SITE-X']);

        $response = $this->actingAs($this->admin)->put(route('admin.sites.update', $site), [
            'name' => 'New Name', 'code' => 'SITE-X',
        ]);

        $response->assertRedirect(route('admin.sites.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'New Name']);
    }

    public function test_admin_cannot_delete_site_with_areas(): void
    {
        $site = Site::factory()->create();
        Area::factory()->create(['site_id' => $site->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.sites.destroy', $site));

        $response->assertRedirect(route('admin.sites.index'));
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    public function test_admin_can_delete_empty_site(): void
    {
        $site = Site::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('admin.sites.destroy', $site));

        $response->assertRedirect(route('admin.sites.index'));
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }
}
