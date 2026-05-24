<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Area;
use App\Models\Line;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AreaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_areas(): void
    {
        $site = Site::factory()->create();
        Area::factory()->create(['site_id' => $site->id, 'name' => 'Painting Booth']);

        $response = $this->actingAs($this->admin)->get(route('admin.areas.index'));

        $response->assertOk();
        $response->assertSee('Painting Booth');
    }

    public function test_admin_can_create_area_under_site(): void
    {
        $site = Site::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.sites.areas.store', $site), [
            'name' => 'Assembly Hall A', 'code' => 'AREA-AHA', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.sites.show', $site));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('areas', [
            'site_id' => $site->id,
            'name'    => 'Assembly Hall A',
            'code'    => 'AREA-AHA',
        ]);
    }

    public function test_area_code_unique_per_site(): void
    {
        $site = Site::factory()->create();
        Area::factory()->create(['site_id' => $site->id, 'code' => 'AREA-1']);

        $response = $this->actingAs($this->admin)->post(route('admin.sites.areas.store', $site), [
            'name' => 'Another', 'code' => 'AREA-1',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_area_code_can_repeat_across_sites(): void
    {
        $siteA = Site::factory()->create();
        $siteB = Site::factory()->create();
        Area::factory()->create(['site_id' => $siteA->id, 'code' => 'AREA-1']);

        $response = $this->actingAs($this->admin)->post(route('admin.sites.areas.store', $siteB), [
            'name' => 'Other', 'code' => 'AREA-1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('areas', ['site_id' => $siteB->id, 'code' => 'AREA-1']);
    }

    public function test_admin_cannot_delete_area_with_lines(): void
    {
        $area = Area::factory()->create();
        Line::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.areas.destroy', $area));

        $response->assertRedirect(route('admin.areas.index'));
        $this->assertDatabaseHas('areas', ['id' => $area->id]);
    }
}
