<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting an admin record that is already gone (soft-deleted in another tab, a
 * stale list, a double submit) must not dump a bare 404 — the delete's intent is
 * already satisfied, so the app bounces back to the list with an info message.
 * Handled centrally in bootstrap/app.php for every admin CRUD resource.
 */
class StaleDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_deleting_an_already_removed_record_redirects_with_a_message_not_404(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete(); // already soft-deleted

        // A second delete (stale list / other tab) must not 404.
        $this->actingAs($this->admin)
            ->from('/admin/customers')
            ->delete("/admin/customers/{$customer->id}")
            ->assertRedirect('/admin/customers')
            ->assertSessionHas('info');
    }

    public function test_a_real_delete_still_works(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->from('/admin/customers')
            ->delete("/admin/customers/{$customer->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($customer);
    }
}
