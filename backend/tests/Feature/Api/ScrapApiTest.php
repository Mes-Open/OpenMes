<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private string $adminToken;
    private string $operatorToken;

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

    private function asAdmin()
    {
        return $this->withHeader('Authorization', "Bearer {$this->adminToken}");
    }

    private function asOperator()
    {
        return $this->withHeader('Authorization', "Bearer {$this->operatorToken}");
    }

    // ── Scrap reasons ────────────────────────────────────────────────────────

    public function test_index_returns_only_active_reasons_by_default(): void
    {
        ScrapReason::factory()->create(['code' => 'ACT-1', 'is_active' => true]);
        ScrapReason::factory()->inactive()->create(['code' => 'INACT-1']);

        $response = $this->asOperator()->getJson('/api/v1/scrap-reasons');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('ACT-1'));
        $this->assertFalse($codes->contains('INACT-1'));
    }

    public function test_admin_can_create_reason(): void
    {
        $this->asAdmin()->postJson('/api/v1/scrap-reasons', [
            'code' => 'API-1', 'name' => 'Via API', 'category' => 'machine',
        ])->assertStatus(201)->assertJsonPath('data.code', 'API-1');

        $this->assertDatabaseHas('scrap_reasons', ['code' => 'API-1', 'category' => 'machine']);
    }

    public function test_operator_cannot_create_reason(): void
    {
        $this->asOperator()->postJson('/api/v1/scrap-reasons', [
            'code' => 'API-1', 'name' => 'Via API', 'category' => 'machine',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('scrap_reasons', ['code' => 'API-1']);
    }

    public function test_unauthenticated_cannot_read_reasons(): void
    {
        $this->getJson('/api/v1/scrap-reasons')->assertStatus(401);
    }

    // ── Scrap entries ────────────────────────────────────────────────────────

    public function test_operator_can_report_scrap_against_work_order(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = ScrapReason::factory()->create();

        $response = $this->asOperator()->postJson("/api/v1/work-orders/{$wo->id}/scrap-entries", [
            'scrap_reason_id' => $reason->id,
            'quantity' => 12,
            'notes' => 'API report',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.quantity', '12.00');
        $this->assertDatabaseHas('scrap_entries', [
            'work_order_id' => $wo->id,
            'scrap_reason_id' => $reason->id,
            'reported_by' => $this->operator->id,
        ]);
    }

    public function test_reporting_against_inactive_reason_fails_validation(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = ScrapReason::factory()->inactive()->create();

        $this->asOperator()->postJson("/api/v1/work-orders/{$wo->id}/scrap-entries", [
            'scrap_reason_id' => $reason->id,
            'quantity' => 12,
        ])->assertStatus(422)->assertJsonValidationErrors('scrap_reason_id');
    }

    public function test_work_order_scrap_listing_includes_totals_and_quality(): void
    {
        $wo = WorkOrder::factory()->create(['produced_qty' => 200]);
        $reason = ScrapReason::factory()->create();
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $reason->id, 'quantity' => 50]);

        $response = $this->asOperator()->getJson("/api/v1/work-orders/{$wo->id}/scrap-entries");

        $response->assertOk()
            ->assertJsonPath('meta.total_scrap_qty', 50)
            ->assertJsonPath('meta.quality_pct', 75); // (200-50)/200
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    public function test_scrap_pareto_returns_reasons_sorted_by_quantity(): void
    {
        $wo = WorkOrder::factory()->create();
        $big = ScrapReason::factory()->create(['code' => 'BIG']);
        $small = ScrapReason::factory()->create(['code' => 'SMALL']);
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $big->id, 'quantity' => 90, 'reported_at' => now()]);
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $small->id, 'quantity' => 10, 'reported_at' => now()]);

        $response = $this->asAdmin()->getJson('/api/v1/reports/scrap-pareto');

        $response->assertOk();
        $reasons = $response->json('data.pareto.reasons');
        $this->assertSame('BIG', $reasons[0]['code']);
        $this->assertEquals(90, $reasons[0]['pct']);          // 90 / 100
        $this->assertEquals(100, $reasons[1]['cumulative_pct']);
    }

    public function test_scrap_rate_per_line(): void
    {
        $wo = WorkOrder::factory()->create();
        Batch::factory()->done()->create(['work_order_id' => $wo->id, 'target_qty' => 100, 'produced_qty' => 100]);
        $reason = ScrapReason::factory()->create();
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $reason->id, 'quantity' => 25, 'reported_at' => now()]);

        $response = $this->asAdmin()->getJson('/api/v1/reports/scrap-rate');

        $response->assertOk();
        $perLine = collect($response->json('data.per_line'))->firstWhere('line_id', $wo->line_id);
        $this->assertNotNull($perLine);
        $this->assertEquals(25, $perLine['scrap_qty']);
        $this->assertEquals(100, $perLine['produced_qty']);
        $this->assertEquals(25, $perLine['scrap_rate_pct']);
    }

    public function test_operator_cannot_access_scrap_reports(): void
    {
        $this->asOperator()->getJson('/api/v1/reports/scrap-pareto')->assertStatus(403);
    }

    public function test_scrap_entries_are_isolated_per_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);

        $operatorA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $operatorA->assignRole('Operator');
        $tokenA = $operatorA->createToken('test')->plainTextToken;

        $reason = ScrapReason::factory()->create();

        $woA = WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);
        $entryA = ScrapEntry::factory()->create(['work_order_id' => $woA->id, 'scrap_reason_id' => $reason->id]);

        $woB = WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);
        $entryB = ScrapEntry::factory()->create(['work_order_id' => $woB->id, 'scrap_reason_id' => $reason->id]);

        $authA = $this->withHeader('Authorization', "Bearer {$tokenA}");

        // Listing only returns the caller's tenant entries.
        $ids = collect($authA->getJson('/api/v1/scrap-entries')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($entryA->id));
        $this->assertFalse($ids->contains($entryB->id), 'Cross-tenant scrap entry leaked into listing');

        // A cross-tenant entry is not directly readable; an own entry is.
        $authA->getJson("/api/v1/scrap-entries/{$entryB->id}")->assertStatus(404);
        $authA->getJson("/api/v1/scrap-entries/{$entryA->id}")->assertStatus(200);
    }
}
