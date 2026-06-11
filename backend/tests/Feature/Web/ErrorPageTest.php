<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * In production, error statuses render an Inertia `Error` page so the app
 * chrome (sidebar) stays put. The handler is bypassed in local/testing, so we
 * force the production environment for these assertions.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
    }

    public function test_not_found_renders_inertia_error_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get('/admin/a-route-that-does-not-exist-xyz');

        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
        );
    }

    public function test_within_route_403_keeps_the_user_so_the_layout_has_the_sidebar(): void
    {
        // An operator hitting an Admin-only route 403s *inside* the route, so
        // HandleInertiaRequests has already shared the user — the Error page can
        // render the viewer's chrome (sidebar).
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get('/settings/system')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('auth.user.roles', ['Operator'])
            );
    }

    public function test_model_not_found_404_inside_a_route_keeps_the_sidebar(): void
    {
        // A bad id resolves to a ModelNotFoundException after the session has
        // started, so auth is (re)shared and the Error page keeps the chrome.
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/admin/work-orders/99999999')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('auth.user.roles', ['Admin'])
            );
    }

    public function test_api_errors_stay_json_not_an_inertia_page(): void
    {
        $response = $this->getJson('/api/v1/a-route-that-does-not-exist-xyz');

        $response->assertStatus(404);
        // A JSON 404 has no Inertia component header.
        $this->assertNull($response->headers->get('X-Inertia'));
    }
}
