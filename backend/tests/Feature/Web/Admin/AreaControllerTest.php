<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Area;
use App\Models\Line;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
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

        // Rows live-sync to the browser via the `areas` collection, so the
        // names are not in the server HTML — assert the Inertia page renders.
        $this->actingAs($this->admin)->get(route('admin.areas.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/areas/Index'));
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
            'name' => 'Assembly Hall A',
            'code' => 'AREA-AHA',
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

    /**
     * The list page's create/edit drawer posts `stay`, which the StaysOnList
     * concern answers with back() — a redirect to the index would remount the
     * page and take the user's filters, paging and scroll with it.
     */
    public function test_create_from_the_list_drawer_stays_on_the_list(): void
    {
        $site = Site::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from(route('admin.areas.index'))
            ->post(route('admin.areas.store'), [
                'site_id' => $site->id, 'name' => 'Drawer Area', 'code' => 'AREA-DRW', 'stay' => 1,
            ]);

        $response->assertRedirect(route('admin.areas.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('areas', ['code' => 'AREA-DRW']);
    }

    public function test_update_from_the_list_drawer_stays_on_the_list(): void
    {
        $area = Area::factory()->create(['name' => 'Before']);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.areas.index'))
            ->put(route('admin.areas.update', $area), [
                'site_id' => $area->site_id, 'name' => 'After', 'code' => $area->code, 'stay' => 1,
            ]);

        $response->assertRedirect(route('admin.areas.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => 'After']);
    }

    /** Without `stay` — the standalone /create page — the redirect is unchanged. */
    public function test_create_without_stay_redirects_to_the_list(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.areas.create'))
            ->post(route('admin.areas.store'), [
                'site_id' => $site->id, 'name' => 'Page Area', 'code' => 'AREA-PGE',
            ])
            ->assertRedirect(route('admin.areas.index'));
    }

    /**
     * The drawer's option lists are Inertia::optional(), so they cost nothing on
     * a plain list render and arrive on the partial reload the drawer fires.
     */
    public function test_list_serves_the_drawers_options_only_when_asked(): void
    {
        Site::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.areas.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/areas/Index')
                ->missing('sites')
                ->missing('customFields'));

        $this->actingAs($this->admin)
            ->get(route('admin.areas.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => 'admin/areas/Index',
                'X-Inertia-Partial-Data' => 'sites,customFields',
            ])
            // A partial reload answers with the Inertia JSON envelope, not an HTML
            // page, so assertInertia (which reads data-page) can't see it.
            ->assertJsonPath('component', 'admin/areas/Index')
            ->assertJsonStructure(['props' => ['sites', 'customFields']]);
    }

    /** The standalone create and edit routes keep working beside the drawer. */
    public function test_standalone_create_and_edit_pages_still_render(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.areas.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/areas/Create')
                ->has('sites'));

        $this->actingAs($this->admin)->get(route('admin.areas.edit', $area))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/areas/Edit')
                ->has('area')
                ->has('sites'));
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
