<?php

namespace Tests\Feature\Extension;

use App\Extension\HookRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings offers a module a card of its own: display hook
 * `display.settings.index.cards`, plain card fields (title, body, href), asked
 * with the signed-in user so a module can leave someone out.
 */
class SettingsCardsHookTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator');
        $this->operator = User::factory()->create(['force_password_change' => false]);
        $this->operator->assignRole('Operator');
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        // Not via assertInertia's dot paths: hook names contain dots.
        return $response->viewData('page')['props'];
    }

    public function test_a_community_install_contributes_nothing(): void
    {
        $response = $this->actingAs($this->operator)->get(route('settings.index'))->assertOk();

        $this->assertSame('settings/Index', $response->viewData('page')['component']);
        $this->assertSame([], $this->props($response)['hooks']);
    }

    public function test_a_module_adds_a_card_for_every_user(): void
    {
        app(HookRegistry::class)->listen('display.settings.index.cards', [
            'title' => 'Connected apps',
            'body' => 'Apps you allowed to use your account',
            'href' => '/settings/connected-apps',
        ]);

        $props = $this->props($this->actingAs($this->operator)->get(route('settings.index'))->assertOk());

        $this->assertSame(
            [['title' => 'Connected apps', 'body' => 'Apps you allowed to use your account', 'href' => '/settings/connected-apps']],
            $props['hooks']['display.settings.index.cards'],
        );
        // Beside what the page was already given, not instead of it.
        $this->assertArrayHasKey('twoFactorEnabled', $props);
    }

    public function test_the_listener_gets_the_user_and_may_leave_them_out(): void
    {
        $seen = null;
        app(HookRegistry::class)->listen('display.settings.index.cards', function (array $context) use (&$seen) {
            $seen = $context['user'] ?? null;

            return null;
        });

        $props = $this->props($this->actingAs($this->operator)->get(route('settings.index'))->assertOk());

        $this->assertTrue($this->operator->is($seen));
        $this->assertSame([], $props['hooks']);
    }

    public function test_fields_other_than_card_fields_are_dropped(): void
    {
        app(HookRegistry::class)->listen('display.settings.index.cards', [
            'title' => 'Connected apps',
            'href' => '/settings/connected-apps',
            'html' => '<script>alert(1)</script>',
        ]);

        $props = $this->props($this->actingAs($this->operator)->get(route('settings.index'))->assertOk());

        $this->assertSame(
            [['title' => 'Connected apps', 'href' => '/settings/connected-apps']],
            $props['hooks']['display.settings.index.cards'],
        );
    }
}
