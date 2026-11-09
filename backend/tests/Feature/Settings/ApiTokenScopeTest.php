<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → API Tokens: a token can be created with full access or scoped to
 * the machine gateway, which is what the OPC UA sidecar should run with.
 */
class ApiTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        foreach (['Admin', 'Supervisor', 'Operator'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_creates_a_machine_gateway_token(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/settings/api-tokens', ['name' => 'OPC UA gateway', 'scope' => 'machine-gateway'])
            ->assertRedirect('/settings/api-tokens')
            ->assertSessionHas('new_token')
            ->assertSessionHas('new_token_scope', 'machine-gateway');

        $token = $admin->tokens()->sole();
        $this->assertSame(['machine-gateway'], $token->abilities);
    }

    public function test_token_without_scope_keeps_full_access(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/settings/api-tokens', ['name' => 'ERP'])
            ->assertRedirect('/settings/api-tokens');

        $this->assertSame(['*'], $admin->tokens()->sole()->abilities);
    }

    public function test_unknown_scope_is_rejected(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/settings/api-tokens', ['name' => 'X', 'scope' => 'root'])
            ->assertSessionHasErrors('scope');

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_name_is_required(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/settings/api-tokens', ['scope' => 'machine-gateway'])
            ->assertSessionHasErrors('name');
    }

    public function test_non_admin_cannot_create_a_token(): void
    {
        $operator = $this->userWithRole('Operator');

        $this->actingAs($operator)
            ->post('/settings/api-tokens', ['name' => 'X', 'scope' => 'machine-gateway'])
            ->assertForbidden();

        $this->assertSame(0, $operator->tokens()->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/settings/api-tokens', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_token_list_shows_the_scope(): void
    {
        $admin = $this->userWithRole('Admin');
        $admin->createToken('gateway', ['machine-gateway']);

        $this->actingAs($admin)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tokens.0.scope', 'machine-gateway')
                ->has('scopes', 2));
    }
}
