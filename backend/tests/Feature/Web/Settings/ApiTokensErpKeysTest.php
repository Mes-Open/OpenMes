<?php

namespace Tests\Feature\Web\Settings;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Settings → API Tokens carries the ERP key section's data: whether the ERP
 * module is on and the scopes an admin can grant, with their descriptions —
 * plus the session-guarded /settings/api-keys endpoints the section calls.
 */
class ApiTokensErpKeysTest extends TestCase
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

    public function test_admin_sees_every_erp_scope_with_label_and_description(): void
    {
        $this->actingAs($this->admin)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/ApiTokens')
                ->where('erpEnabled', true)
                ->has('erpScopes', count(ApiScope::cases()))
                ->where('erpScopes', function ($scopes) {
                    $row = collect($scopes)->firstWhere('value', 'erp:masterdata:read');

                    return $row !== null
                        && $row['label'] === 'Read lines, warehouses & product types'
                        && $row['description'] !== '';
                })
            );
    }

    public function test_erp_section_is_hidden_when_the_module_is_off(): void
    {
        ModuleRegistry::save(['reports']);

        $this->actingAs($this->admin)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('erpEnabled', false)
                ->where('erpScopes', [])
            );
    }

    public function test_non_admin_cannot_open_the_page(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get('/settings/api-tokens')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect();
    }

    public function test_admin_creates_lists_and_revokes_a_key_over_the_session(): void
    {
        $created = $this->actingAs($this->admin)
            ->postJson('/settings/api-keys', [
                'name' => 'Odoo',
                'scopes' => ['erp:masterdata:read', 'erp:production:read'],
                'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Odoo')
            ->assertJsonPath('data.scopes', ['erp:masterdata:read', 'erp:production:read']);

        $plaintext = $created->json('plaintext_key');
        $this->assertStringStartsWith(ApiKey::PREFIX, $plaintext);
        $id = $created->json('data.id');

        $list = $this->actingAs($this->admin)->getJson('/settings/api-keys')->assertOk();
        $this->assertSame(['Odoo'], collect($list->json('data'))->pluck('name')->all());
        $this->assertStringNotContainsString($plaintext, $list->getContent());
        $this->assertArrayNotHasKey('key_hash', $list->json('data.0'));

        $this->actingAs($this->admin)->deleteJson("/settings/api-keys/{$id}")->assertNoContent();
        $this->assertSoftDeleted('api_keys', ['id' => $id]);
    }

    public function test_session_key_creation_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/settings/api-keys', ['name' => '', 'scopes' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'scopes']);

        $this->actingAs($this->admin)
            ->postJson('/settings/api-keys', ['name' => 'Odoo', 'scopes' => ['erp:everything']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scopes.0');

        $this->actingAs($this->admin)
            ->postJson('/settings/api-keys', [
                'name' => 'Odoo',
                'scopes' => ['erp:masterdata:read'],
                'expires_at' => now()->subDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expires_at');

        $this->assertSame(0, ApiKey::count());
    }

    public function test_session_key_endpoints_are_admin_only(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        [$key] = ApiKey::issue(['name' => 'existing', 'scopes' => ['erp:production:read']]);

        $this->getJson('/settings/api-keys')->assertUnauthorized();
        $this->postJson('/settings/api-keys', ['name' => 'x', 'scopes' => ['erp:production:read']])->assertUnauthorized();

        $this->actingAs($operator)->getJson('/settings/api-keys')->assertForbidden();
        $this->actingAs($operator)
            ->postJson('/settings/api-keys', ['name' => 'x', 'scopes' => ['erp:production:read']])
            ->assertForbidden();
        $this->actingAs($operator)->deleteJson("/settings/api-keys/{$key->id}")->assertForbidden();

        $this->assertNotSoftDeleted('api_keys', ['id' => $key->id]);
        $this->assertSame(1, ApiKey::count());
    }

    public function test_session_key_endpoints_404_when_the_erp_module_is_off(): void
    {
        ModuleRegistry::save(['reports']);

        $this->actingAs($this->admin)->getJson('/settings/api-keys')->assertNotFound();
        $this->actingAs($this->admin)
            ->postJson('/settings/api-keys', ['name' => 'x', 'scopes' => ['erp:production:read']])
            ->assertNotFound();
    }
}
