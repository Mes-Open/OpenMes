<?php

namespace Tests\Feature\Sync;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression guard for "POST create returns 200 but GET /api/collections returns
 * 401" — the live-sync list read needs a Sanctum stateful session. The fix marks
 * the app's own host stateful via currentRequestHost(), so same-origin reads
 * authenticate on any host/port/domain without per-deploy SANCTUM_STATEFUL_DOMAINS.
 */
class StatefulCollectionAccessTest extends TestCase
{
    public function test_current_request_host_placeholder_is_in_stateful_domains(): void
    {
        $this->assertContains(
            Sanctum::$currentRequestHostPlaceholder,
            config('sanctum.stateful'),
            'config/sanctum.php must include Sanctum::currentRequestHost() so same-origin reads stay stateful.',
        );
    }

    public function test_empty_stateful_domains_env_falls_back_to_the_defaults(): void
    {
        // docker-compose passes SANCTUM_STATEFUL_DOMAINS through as an empty string
        // when the deployment doesn't set it. env() only applies its default on null,
        // so an empty value must not wipe the list and 401 every SPA call to /api/v1.
        $previous = getenv('SANCTUM_STATEFUL_DOMAINS');
        putenv('SANCTUM_STATEFUL_DOMAINS=');
        $_ENV['SANCTUM_STATEFUL_DOMAINS'] = $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = '';

        try {
            $stateful = (require base_path('config/sanctum.php'))['stateful'];
        } finally {
            putenv($previous === false ? 'SANCTUM_STATEFUL_DOMAINS' : "SANCTUM_STATEFUL_DOMAINS={$previous}");
            unset($_ENV['SANCTUM_STATEFUL_DOMAINS'], $_SERVER['SANCTUM_STATEFUL_DOMAINS']);
        }

        $this->assertContains(Sanctum::$currentRequestHostPlaceholder, $stateful);
        $this->assertContains('localhost', $stateful);
    }

    public function test_same_origin_request_is_treated_as_stateful_on_any_host(): void
    {
        // App served on an arbitrary host the app URL doesn't cover.
        $request = Request::create('http://mes.factory.local:8095/api/collections/work_orders_all');
        $request->headers->set('Origin', 'http://mes.factory.local:8095');
        $request->headers->set('Host', 'mes.factory.local:8095');

        $this->assertTrue(
            EnsureFrontendRequestsAreStateful::fromFrontend($request),
            'Same-origin SPA requests must be stateful regardless of the host.',
        );
    }

    public function test_cross_origin_request_is_not_stateful(): void
    {
        // Different Origin (attacker site) than the app host → must stay rejected.
        $request = Request::create('http://mes.factory.local:8095/api/collections/work_orders_all');
        $request->headers->set('Origin', 'http://evil.example.com');
        $request->headers->set('Host', 'mes.factory.local:8095');

        $this->assertFalse(
            EnsureFrontendRequestsAreStateful::fromFrontend($request),
            'Cross-origin requests must not become stateful (CSRF protection preserved).',
        );
    }
}
