<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Subassembly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Subassembly detail page (#179) — hosts the engineering-documents panel so CAD
 * files can be attached to a subassembly. Route was previously excluded.
 */
class SubassemblyShowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_view_the_subassembly_show_page(): void
    {
        $sub = Subassembly::create(['code' => 'SUB-1', 'name' => 'Bracket assembly']);

        $this->actingAs($this->admin)
            ->get(route('admin.subassemblies.show', $sub))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/subassemblies/Show')
                ->where('subassembly.id', $sub->id)
                ->where('subassembly.code', 'SUB-1'));
    }

    public function test_guest_is_redirected(): void
    {
        $sub = Subassembly::create(['code' => 'SUB-2', 'name' => 'X']);
        $this->get(route('admin.subassemblies.show', $sub))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_view(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $sub = Subassembly::create(['code' => 'SUB-3', 'name' => 'X']);

        $this->actingAs($operator)->get(route('admin.subassemblies.show', $sub))->assertForbidden();
    }
}
