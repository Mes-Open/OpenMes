<?php

namespace Tests\Feature\Api;

use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected string $adminToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    public function test_any_user_can_list_active_product_types(): void
    {
        ProductType::factory()->create(['is_active' => true]);
        ProductType::factory()->create(['is_active' => false]);

        $response = $this->authOperator()->getJson('/api/v1/product-types');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_include_inactive(): void
    {
        ProductType::factory()->create(['is_active' => true]);
        ProductType::factory()->create(['is_active' => false]);

        $response = $this->authAdmin()->getJson('/api/v1/product-types?include_inactive=1');
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_product_type(): void
    {
        $response = $this->authAdmin()->postJson('/api/v1/product-types', [
            'code' => 'PT-A',
            'name' => 'Widget',
        ]);
        $response->assertStatus(201)->assertJsonPath('data.code', 'PT-A');
    }

    public function test_create_requires_unique_code(): void
    {
        ProductType::factory()->create(['code' => 'DUP']);
        $this->authAdmin()->postJson('/api/v1/product-types', [
            'code' => 'DUP', 'name' => 'X',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_operator_cannot_create(): void
    {
        $this->authOperator()->postJson('/api/v1/product-types', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_admin_can_update(): void
    {
        $pt = ProductType::factory()->create(['name' => 'Old']);
        $this->authAdmin()->patchJson("/api/v1/product-types/{$pt->id}", [
            'name' => 'New',
        ])->assertStatus(200);
        $this->assertDatabaseHas('product_types', ['id' => $pt->id, 'name' => 'New']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $pt = ProductType::factory()->create(['is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/product-types/{$pt->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($pt->fresh()->is_active);
    }

    public function test_admin_can_delete_unused(): void
    {
        $pt = ProductType::factory()->create();
        $this->authAdmin()->deleteJson("/api/v1/product-types/{$pt->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('product_types', ['id' => $pt->id]);
    }

    public function test_cannot_delete_when_referenced_by_work_order(): void
    {
        $pt = ProductType::factory()->create();
        WorkOrder::factory()->create(['product_type_id' => $pt->id]);

        $this->authAdmin()->deleteJson("/api/v1/product-types/{$pt->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('product_types', ['id' => $pt->id]);
    }
}
