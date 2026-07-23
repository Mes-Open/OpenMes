<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $tenant = Tenant::create(['name' => 'Admin Tenant']);
        $this->admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->admin->assignRole('Admin');
    }

    public function test_store_returns_the_plaintext_key_exactly_once(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/api-keys', [
            'name' => 'SAP prod',
            'scopes' => ['erp:orders:import', 'erp:production:read'],
        ]);

        $response->assertCreated();
        $plaintext = $response->json('plaintext_key');
        $this->assertNotNull($plaintext);
        $this->assertStringStartsWith(ApiKey::PREFIX, $plaintext);

        // The stored row keeps only the hash; the secret is never persisted.
        $this->assertDatabaseHas('api_keys', [
            'name' => 'SAP prod',
            'tenant_id' => $this->admin->tenant_id,
            'key_hash' => ApiKey::hashSecret($plaintext),
        ]);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $plaintext]);
    }

    public function test_index_never_exposes_hash_or_plaintext(): void
    {
        ApiKey::issue([
            'name' => 'listed',
            'scopes' => ['erp:production:read'],
            'tenant_id' => $this->admin->tenant_id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/api-keys');

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertArrayNotHasKey('key_hash', $row);
        $this->assertArrayNotHasKey('plaintext_key', $row);
        $this->assertArrayHasKey('key_prefix', $row);
    }

    public function test_store_validates_scopes(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/api-keys', ['name' => 'bad', 'scopes' => ['not-a-scope']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scopes.0');
    }

    public function test_guest_cannot_manage_keys(): void
    {
        $this->getJson('/api/v1/api-keys')->assertStatus(401);
    }

    public function test_non_admin_cannot_manage_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/api-keys')
            ->assertStatus(403);
    }

    public function test_key_management_404s_when_the_erp_module_is_disabled(): void
    {
        ModuleRegistry::save(['reports']); // enabled set without 'erp'

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/api-keys')
            ->assertStatus(404);
    }
}
