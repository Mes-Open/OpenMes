<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A body over post_max_size never reaches a Form Request — PHP drops it and
 * ValidatePostSize throws from the GLOBAL middleware stack, before the web
 * group has started a session. The handler in bootstrap/app.php must still
 * get a flash to the user (web) or answer 413 (JSON) rather than a bare error
 * page, so these requests go through the real middleware with an oversized
 * Content-Length rather than throwing from inside a route.
 */
class PostTooLargeTest extends TestCase
{
    use RefreshDatabase;

    /** Larger than any post_max_size a test runner will have. */
    private const OVERSIZED = '999999999999';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_an_oversized_web_request_is_sent_back_with_a_flash_error(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)
            ->call('POST', '/admin/backups/upload', [], [], [], [
                'CONTENT_LENGTH' => self::OVERSIZED,
                'HTTP_REFERER' => 'http://localhost/admin/backups',
            ]);

        $response->assertRedirect('http://localhost/admin/backups');
        $response->assertSessionHas('error', fn (string $msg) => str_contains($msg, 'too large'));

        // assertSessionHas reads the in-memory store, which holds the flash even
        // when nothing persisted it. The browser only sees what the handler
        // SAVED — read that back through the driver, as the next request would.
        $store = app('session.store');
        $persisted = unserialize($store->getHandler()->read($store->getId()) ?: 'a:0:{}');

        $this->assertStringContainsString('too large', $persisted['error'] ?? '');
    }

    public function test_an_oversized_request_keeps_the_users_session(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->call('POST', '/admin/backups/upload', [], [], [], ['CONTENT_LENGTH' => self::OVERSIZED]);

        // Resuming the user's own session, not starting a new one, is what keeps
        // them logged in after the error.
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_an_oversized_json_request_gets_a_413(): void
    {
        $response = $this->call(
            'POST',
            '/admin/backups/upload',
            [], [], [],
            ['CONTENT_LENGTH' => self::OVERSIZED, 'HTTP_ACCEPT' => 'application/json'],
        );

        $response->assertStatus(413)
            ->assertJsonStructure(['message']);
    }
}
