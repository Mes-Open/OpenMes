<?php

namespace Tests\Feature\Extension;

use App\Extension\HookRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin layout offers a module one place to say something on every screen:
 * display hook `display.admin.layout`, the counterpart of the operator panel's.
 */
class AdminLayoutHookTest extends TestCase
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

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        // Not via assertInertia's dot paths: hook names contain dots.
        return $response->viewData('page')['props'];
    }

    public function test_a_community_install_contributes_nothing(): void
    {
        $props = $this->props($this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk());

        $this->assertSame([], $props['adminHooks']);
    }

    public function test_a_module_can_render_on_every_admin_screen(): void
    {
        app(HookRegistry::class)->listen('display.admin.layout', ['title' => 'Licence expires in 3 days']);

        foreach ([route('admin.dashboard'), route('settings.system')] as $url) {
            $props = $this->props($this->actingAs($this->admin)->get($url)->assertOk());

            $this->assertSame([['title' => 'Licence expires in 3 days']], $props['adminHooks']['display.admin.layout']);
        }
    }

    public function test_the_listener_is_told_who_is_looking(): void
    {
        $seen = null;

        app(HookRegistry::class)->listen('display.admin.layout', function (array $context) use (&$seen) {
            $seen = $context['user'];

            // Null renders nothing: a module opts out per user this way.
            return null;
        });

        $props = $this->props($this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk());

        $this->assertTrue($this->admin->is($seen));
        $this->assertSame([], $props['adminHooks']);
    }
}
