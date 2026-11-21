<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → API Tokens lists the tokens the signed-in admin can revoke.
 *
 * It used to list every user's tokens — including the 15-minute tokens the
 * mobile app gets at sign-in, expired ones too — each with a Revoke button
 * that ended on a bare 403 page, because revoking is (deliberately) limited
 * to one's own tokens.
 */
class ApiTokenListTest extends TestCase
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

    public function test_lists_only_the_admins_own_live_tokens(): void
    {
        $admin = $this->userWithRole('Admin');
        $otherAdmin = $this->userWithRole('Admin');
        $operator = $this->userWithRole('Operator');

        $admin->createToken('Mine');
        $admin->createToken('Mine, expired', ['*'], now()->subMinute());
        $otherAdmin->createToken('Someone else');
        $operator->createToken('api-token', ['*'], now()->addMinutes(15));

        $this->actingAs($admin)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/ApiTokens')
                ->has('tokens', 1)
                ->where('tokens.0.name', 'Mine'));
    }

    public function test_every_listed_token_can_be_revoked_by_the_viewer(): void
    {
        $admin = $this->userWithRole('Admin');
        $otherAdmin = $this->userWithRole('Admin');

        $admin->createToken('Mine');
        $otherAdmin->createToken('Someone else');

        $listed = [];
        $this->actingAs($admin)
            ->get('/settings/api-tokens')
            ->assertInertia(function (AssertableInertia $page) use (&$listed) {
                $listed = array_column($page->toArray()['props']['tokens'], 'id');
            });

        $this->assertNotEmpty($listed);
        foreach ($listed as $id) {
            $this->actingAs($admin)
                ->delete('/settings/api-tokens/'.$id)
                ->assertRedirect('/settings/api-tokens');
        }

        $this->assertSame(0, $admin->tokens()->count());
        $this->assertSame(1, $otherAdmin->tokens()->count());
    }
}
