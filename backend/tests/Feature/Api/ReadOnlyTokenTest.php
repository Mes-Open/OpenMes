<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApiTokenScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → API Tokens → Access: Read-only. Such a token reads everything its
 * creator can and is refused on every request that is not GET/HEAD/OPTIONS —
 * on core and module routes alike — while full tokens and the browser session
 * keep writing.
 */
class ReadOnlyTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Set by the probe route's handler — proves a refused write never ran. */
    private bool $handlerRan = false;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // Registered the way a module registers its own API routes (outside
        // routes/api.php), so the test also proves the block is group-wide.
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
            Route::middleware(['api', 'auth:sanctum'])->{$method}('/api/v1/test-probe', function () {
                $this->handlerRan = true;

                return response()->json(['ok' => true]);
            });
        }
    }

    private function readOnlyToken(): string
    {
        return $this->admin->createToken('mcp', ApiTokenScope::ReadOnly->abilities())->plainTextToken;
    }

    // ── Creating the token ───────────────────────────────────────

    public function test_admin_creates_a_read_only_token(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/api-tokens', ['name' => 'AI assistant', 'scope' => 'read-only'])
            ->assertRedirect('/settings/api-tokens')
            ->assertSessionHas('new_token')
            ->assertSessionHas('new_token_scope', 'read-only');

        $this->assertSame(['read'], $this->admin->tokens()->sole()->abilities);
    }

    public function test_token_list_labels_a_read_only_token(): void
    {
        $this->readOnlyToken();

        $this->actingAs($this->admin)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tokens.0.scope', 'read-only')
                ->where('tokens.0.scope_label', 'Read-only')
                ->where('scopes.1.value', 'read-only')
                ->has('scopes.1.description'));
    }

    // ── Enforcement ──────────────────────────────────────────────

    public function test_read_only_token_reads_the_api(): void
    {
        $this->withToken($this->readOnlyToken())
            ->getJson('/api/v1/lines')
            ->assertOk();
    }

    public function test_read_only_token_reads_module_routes(): void
    {
        $this->withToken($this->readOnlyToken())
            ->getJson('/api/v1/test-probe')
            ->assertOk();

        $this->assertTrue($this->handlerRan);
    }

    public function test_read_only_token_is_refused_every_write_method(): void
    {
        $token = $this->readOnlyToken();

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $call) {
            $this->withToken($token)
                ->{$call}('/api/v1/test-probe')
                ->assertForbidden()
                ->assertJson(['message' => 'This token is read-only.']);
        }

        $this->assertFalse($this->handlerRan);
    }

    public function test_read_only_token_cannot_be_refreshed_into_a_full_token(): void
    {
        $this->withToken($this->readOnlyToken())
            ->postJson('/api/auth/refresh')
            ->assertForbidden();

        $this->assertSame(['read'], $this->admin->tokens()->sole()->abilities);
    }

    public function test_full_token_and_session_still_write(): void
    {
        $this->withToken($this->admin->createToken('full')->plainTextToken)
            ->postJson('/api/v1/test-probe')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/test-probe')
            ->assertOk();
    }

    public function test_read_only_token_keeps_its_creators_role_limits(): void
    {
        // Read-only narrows a token; it never widens it — an operator's
        // read-only token still cannot read admin-only endpoints.
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $token = $operator->createToken('ro', ['read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/analytics/overview')
            ->assertForbidden();
    }

    public function test_guest_is_unauthenticated_not_read_only_refused(): void
    {
        $this->postJson('/api/v1/test-probe')->assertUnauthorized();
    }

    // ── /auth/me reports the scope ───────────────────────────────

    public function test_me_reports_a_read_only_token(): void
    {
        $this->withToken($this->readOnlyToken())
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('token.scope', 'read-only')
            ->assertJsonPath('token.abilities', ['read']);
    }

    public function test_me_reports_a_full_token(): void
    {
        $this->withToken($this->admin->createToken('full')->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('token.scope', 'full');
    }

    public function test_me_reports_no_token_for_a_session(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('token', null);
    }

    // ── Reading a scope back from abilities ──────────────────────

    public function test_scope_is_read_back_from_abilities(): void
    {
        $this->assertSame(ApiTokenScope::Full, ApiTokenScope::fromAbilities(['*']));
        $this->assertSame(ApiTokenScope::ReadOnly, ApiTokenScope::fromAbilities(['read']));
        $this->assertSame(ApiTokenScope::MachineGateway, ApiTokenScope::fromAbilities(['machine-gateway']));
        // `*` wins over anything listed beside it.
        $this->assertSame(ApiTokenScope::Full, ApiTokenScope::fromAbilities(['*', 'read']));
        // Legacy/unknown abilities keep showing as full, as before.
        $this->assertSame(ApiTokenScope::Full, ApiTokenScope::fromAbilities(null));
    }
}
