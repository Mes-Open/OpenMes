<?php

namespace Tests\Feature\Extension;

use App\Extension\HookRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The sign-in page offers a module the space around the form: display hooks
 * `display.auth.login.before` and `display.auth.login.after`. The form itself
 * stays core's.
 */
class SignInHookTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        // Not via assertInertia's dot paths: hook names contain dots.
        return $response->viewData('page')['props'];
    }

    public function test_a_community_install_contributes_nothing(): void
    {
        $props = $this->props($this->get(route('login'))->assertOk());

        $this->assertSame([], $props['hooks']);
        $this->assertSame('auth/Login', $this->get(route('login'))->viewData('page')['component']);
    }

    public function test_a_module_can_render_above_and_below_the_form(): void
    {
        app(HookRegistry::class)->listen('display.auth.login.before', ['title' => 'Acme Plant 2']);
        app(HookRegistry::class)->listen('display.auth.login.after', [
            'title' => 'Forgot your password?',
            'href' => '/password/forgot',
        ]);

        $props = $this->props($this->get(route('login'))->assertOk());

        $this->assertSame([['title' => 'Acme Plant 2']], $props['hooks']['display.auth.login.before']);
        $this->assertSame(
            [['title' => 'Forgot your password?', 'href' => '/password/forgot']],
            $props['hooks']['display.auth.login.after'],
        );

        // Beside what the page was already given, not instead of it.
        $this->assertArrayHasKey('pinEnabled', $props);
        $this->assertArrayHasKey('regEnabled', $props);
    }

    public function test_the_listener_is_asked_before_anybody_is_signed_in(): void
    {
        $seen = 'never called';

        app(HookRegistry::class)->listen('display.auth.login.before', function (array $context) use (&$seen) {
            $seen = $context;

            // Null renders nothing: a module opts out per request this way.
            return null;
        });

        $props = $this->props($this->get(route('login'))->assertOk());

        // No user to hand over, and nothing else pretending to be context.
        $this->assertSame([], $seen);
        $this->assertSame([], $props['hooks']);
    }

    public function test_one_point_does_not_leak_into_the_other(): void
    {
        app(HookRegistry::class)->listen('display.auth.login.after', ['title' => 'Sign in with your company account']);

        $props = $this->props($this->get(route('login'))->assertOk());

        $this->assertArrayNotHasKey('display.auth.login.before', $props['hooks']);
        $this->assertCount(1, $props['hooks']['display.auth.login.after']);
    }
}
