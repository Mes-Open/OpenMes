<?php

namespace Tests\Feature\Api;

use App\Models\AnomalyReason;
use App\Models\Company;
use App\Models\CostSource;
use App\Models\Shift;
use App\Models\Subassembly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsSupportApiTest extends TestCase
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

    // ── Companies ─────────────────────────────────────────────────────────

    public function test_admin_can_create_company(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/companies', [
            'code' => 'ACME', 'name' => 'Acme Corp', 'type' => 'supplier',
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('companies', ['code' => 'ACME']);
    }

    public function test_company_filter_by_type(): void
    {
        Company::create(['code' => 'A', 'name' => 'A', 'type' => 'supplier', 'is_active' => true]);
        Company::create(['code' => 'B', 'name' => 'B', 'type' => 'customer', 'is_active' => true]);
        $r = $this->authAdmin()->getJson('/api/v1/companies?type=supplier');
        $this->assertCount(1, $r->json('data'));
    }

    public function test_company_invalid_type_rejected(): void
    {
        $this->authAdmin()->postJson('/api/v1/companies', [
            'code' => 'X', 'name' => 'X', 'type' => 'invalid',
        ])->assertStatus(422);
    }

    public function test_operator_cannot_create_company(): void
    {
        $this->authOperator()->postJson('/api/v1/companies', [
            'code' => 'X', 'name' => 'X', 'type' => 'supplier',
        ])->assertStatus(403);
    }

    // ── Cost sources ──────────────────────────────────────────────────────

    public function test_admin_can_create_cost_source(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/cost-sources', [
            'code' => 'ELECTRICITY', 'name' => 'Electricity',
            'unit_cost' => '0.20', 'unit' => 'kWh', 'currency' => 'EUR',
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('cost_sources', ['code' => 'ELECTRICITY']);
    }

    public function test_cost_source_unique_code(): void
    {
        CostSource::create(['code' => 'DUP', 'name' => 'X', 'is_active' => true]);
        $this->authAdmin()->postJson('/api/v1/cost-sources', [
            'code' => 'DUP', 'name' => 'Y',
        ])->assertStatus(422);
    }

    // ── Anomaly reasons ────────────────────────────────────────────────────

    public function test_admin_can_create_anomaly_reason(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/anomaly-reasons', [
            'code' => 'SCRAP', 'name' => 'Scrap', 'category' => 'quality',
        ]);
        $r->assertStatus(201);
    }

    public function test_anomaly_reason_filter_by_category(): void
    {
        AnomalyReason::create(['code' => 'A', 'name' => 'A', 'category' => 'quality', 'is_active' => true]);
        AnomalyReason::create(['code' => 'B', 'name' => 'B', 'category' => 'process', 'is_active' => true]);
        $r = $this->authAdmin()->getJson('/api/v1/anomaly-reasons?category=quality');
        $this->assertCount(1, $r->json('data'));
    }

    // ── Subassemblies ──────────────────────────────────────────────────────

    public function test_admin_can_create_subassembly(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/subassemblies', [
            'code' => 'SUB-1', 'name' => 'Frame',
        ]);
        $r->assertStatus(201);
    }

    public function test_anyone_can_list_subassemblies(): void
    {
        Subassembly::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $r = $this->authOperator()->getJson('/api/v1/subassemblies');
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    // ── Shifts ─────────────────────────────────────────────────────────────

    public function test_admin_can_create_shift(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/shifts', [
            'name' => 'Morning',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('shifts', ['name' => 'Morning']);
    }

    public function test_shift_invalid_time_format_rejected(): void
    {
        $this->authAdmin()->postJson('/api/v1/shifts', [
            'name' => 'X', 'start_time' => 'six oclock', 'end_time' => '14:00',
        ])->assertStatus(422);
    }

    public function test_shift_filter_by_line(): void
    {
        $line = \App\Models\Line::factory()->create();
        Shift::create(['name' => 'A', 'line_id' => $line->id, 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
        Shift::create(['name' => 'B', 'start_time' => '14:00', 'end_time' => '22:00', 'is_active' => true]);
        $r = $this->authAdmin()->getJson("/api/v1/shifts?line_id={$line->id}");
        $this->assertCount(1, $r->json('data'));
    }

    public function test_operator_cannot_create_shift(): void
    {
        $this->authOperator()->postJson('/api/v1/shifts', [
            'name' => 'X', 'start_time' => '06:00', 'end_time' => '14:00',
        ])->assertStatus(403);
    }
}
