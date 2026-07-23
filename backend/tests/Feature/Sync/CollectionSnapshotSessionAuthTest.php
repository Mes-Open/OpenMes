<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The live-sync snapshot GET /api/collections/{name} feeds every admin list
 * (ResourceTable). It must authenticate via the plain web session — the same
 * cookie every Inertia page uses — so it works on any host / behind any reverse
 * proxy, without depending on Sanctum stateful-domain matching. Regression guard
 * for #193 ("data saved but lists show nothing"): the snapshot 401'd on hosts
 * APP_URL didn't cover, and the frontend swallowed the error into an empty list.
 */
class CollectionSnapshotSessionAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_session_gets_the_snapshot(): void
    {
        $user = User::factory()->create();

        // No Origin / Referer / stateful-domain set — pure session auth, as it
        // would be for any host the deployment is actually served on.
        $this->actingAs($user)
            ->getJson('/api/collections/issue_types')
            ->assertOk()
            ->assertJsonStructure(['rows', 'channel', 'at']);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/collections/issue_types')->assertUnauthorized();
    }

    public function test_unknown_collection_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/collections/definitely-not-a-shape')
            ->assertNotFound();
    }
}
