<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A body over post_max_size never reaches a Form Request — PHP drops it and
 * ValidatePostSize throws. The handler in bootstrap/app.php must turn that
 * into a flash (web) or a 413 (JSON) rather than a bare error page.
 */
class PostTooLargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Route::post('/_test/too-large', fn () => throw new PostTooLargeException)
            ->middleware('web');
    }

    public function test_web_request_is_sent_back_with_a_flash_error(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)
            ->from('/admin/backups')
            ->post('/_test/too-large');

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHas('error', fn (string $msg) => str_contains($msg, 'too large'));
    }

    public function test_json_request_gets_a_413(): void
    {
        $response = $this->postJson('/_test/too-large');

        $response->assertStatus(413)
            ->assertJsonStructure(['message']);
    }
}
