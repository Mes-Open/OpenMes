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

    public function test_a_guest_is_sent_to_sign_in_and_no_listener_runs(): void
    {
        $called = false;
        app(HookRegistry::class)->listen('display.settings.index.cards', function () use (&$called) {
            $called = true;

            return ['title' => 'Connected apps', 'href' => '/settings/connected-apps'];
        });

        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->assertFalse($called);
    }

    public function test_links_a_browser_would_take_elsewhere_or_run_are_not_sent(): void
    {
        foreach (['javascript:alert(1)', '//evil.example/x', '/\\evil.example/login', "/\t/evil.example", 'data:text/html,x', 'settings/relative'] as $href) {
            app(HookRegistry::class)->listen('display.settings.index.cards', ['title' => 'Card', 'href' => $href]);
        }
        app(HookRegistry::class)->listen('display.settings.index.cards', ['title' => 'Docs', 'href' => 'https://example.com/docs']);

        $cards = $this->props($this->actingAs($this->operator)->get(route('settings.index'))->assertOk())['hooks']['display.settings.index.cards'];

        $this->assertCount(7, $cards);
        $this->assertSame(['Docs' => 'https://example.com/docs'], array_column(array_filter($cards, fn ($c) => isset($c['href'])), 'href', 'title'));
    }

    public function test_a_failing_listener_does_not_take_the_page_down(): void
    {
        $this->withoutExceptionHandling();
        app(HookRegistry::class)->listen('display.settings.index.cards', function (): array {
            throw new \RuntimeException('module table missing');
        });
        app(HookRegistry::class)->listen('display.settings.index.cards', ['title' => 'Connected apps', 'href' => '/settings/connected-apps']);

        $props = $this->props($this->actingAs($this->operator)->get(route('settings.index'))->assertOk());

        $this->assertSame([['title' => 'Connected apps', 'href' => '/settings/connected-apps']], $props['hooks']['display.settings.index.cards']);
    }
}
