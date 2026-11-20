<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The language switch sends the browser back where it was. Pages read their
 * live collections in the background; those reads are marked as AJAX by the
 * client so they never become the "previous URL" a back() redirect returns to.
 */
class LocaleSwitchReturnsToPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_language_after_a_background_collection_read_returns_to_the_page(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        $this->get('/admin/schedule')->assertOk();
        // What the client's collection snapshot sends (lib/realtimeCollection.js).
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson('/api/collections/lines_active')
            ->assertOk();

        $this->get('/locale/pl')->assertRedirect(url('/admin/schedule'));
        $this->assertSame('pl', session('locale'));
    }

    public function test_an_unknown_language_is_ignored(): void
    {
        $this->get('/login');

        $this->get('/locale/xx')->assertRedirect();
        $this->assertNull(session('locale'));
    }
}
