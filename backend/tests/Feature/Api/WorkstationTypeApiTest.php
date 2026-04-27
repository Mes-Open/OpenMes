<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstationTypeApiTest extends TestCase
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

    public function test_anyone_can_list(): void
    {
        WorkstationType::create(['code' => 'WT-A', 'name' => 'Welding', 'is_active' => true]);
        WorkstationType::create(['code' => 'WT-B', 'name' => 'Inactive', 'is_active' => false]);

        $r = $this->authOperator()->getJson('/api/v1/workstation-types');
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data')); // active only by default
    }

    public function test_admin_can_create(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/workstation-types', [
            'code' => 'WT-1', 'name' => 'Welding station',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.code', 'WT-1');
    }

    public function test_create_requires_unique_code(): void
    {
        WorkstationType::create(['code' => 'DUP', 'name' => 'X']);
        $this->authAdmin()->postJson('/api/v1/workstation-types', [
            'code' => 'DUP', 'name' => 'Y',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_operator_cannot_create(): void
    {
        $this->authOperator()->postJson('/api/v1/workstation-types', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_admin_can_update(): void
    {
        $wt = WorkstationType::create(['code' => 'X', 'name' => 'Old']);
        $this->authAdmin()->patchJson("/api/v1/workstation-types/{$wt->id}", [
            'name' => 'New',
        ])->assertStatus(200);
        $this->assertDatabaseHas('workstation_types', ['id' => $wt->id, 'name' => 'New']);
    }

    public function test_admin_can_delete_unused(): void
    {
        $wt = WorkstationType::create(['code' => 'X', 'name' => 'Y']);
        $this->authAdmin()->deleteJson("/api/v1/workstation-types/{$wt->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('workstation_types', ['id' => $wt->id]);
    }

    public function test_cannot_delete_when_referenced(): void
    {
        $wt = WorkstationType::create(['code' => 'X', 'name' => 'Y']);
        $line = Line::factory()->create();
        Workstation::factory()->create([
            'line_id' => $line->id,
            'workstation_type_id' => $wt->id,
        ]);

        $this->authAdmin()->deleteJson("/api/v1/workstation-types/{$wt->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_toggle_active(): void
    {
        $wt = WorkstationType::create(['code' => 'X', 'name' => 'Y', 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/workstation-types/{$wt->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($wt->fresh()->is_active);
    }
}
