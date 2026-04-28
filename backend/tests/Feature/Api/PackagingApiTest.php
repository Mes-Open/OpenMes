<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Packaging\Models\PackagingScanLog;
use Modules\Packaging\Models\WorkOrderEan;
use Tests\TestCase;

class PackagingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $supervisor;
    protected User $operator;
    protected string $adminToken;
    protected string $supervisorToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // The Packaging module is conditionally loaded based on system_settings;
        // in tests, RefreshDatabase rolls everything back so the module is never
        // seen as "enabled". Register the service provider directly and run its
        // migrations so the packaging tables exist.
        $this->app->register(\Modules\Packaging\Providers\PackagingServiceProvider::class);
        $this->artisan('migrate', [
            '--path' => '/var/www/html/modules/Packaging/migrations',
            '--realpath' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->supervisorToken = $this->supervisor->createToken('test')->plainTextToken;
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authSupervisor() { return $this->withHeader('Authorization', "Bearer {$this->supervisorToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    // ── EAN management ───────────────────────────────────────────────────────

    public function test_supervisor_can_create_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $r = $this->authSupervisor()->postJson('/api/v1/packaging/eans', [
            'work_order_id' => $wo->id,
            'ean' => '5901234567890',
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('work_order_eans', ['ean' => '5901234567890']);
    }

    public function test_operator_cannot_create_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $this->authOperator()->postJson('/api/v1/packaging/eans', [
            'work_order_id' => $wo->id,
            'ean' => '5901234567890',
        ])->assertStatus(403);
    }

    public function test_unique_ean_required(): void
    {
        $wo = WorkOrder::factory()->create();
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'DUP']);

        $this->authSupervisor()->postJson('/api/v1/packaging/eans', [
            'work_order_id' => $wo->id,
            'ean' => 'DUP',
        ])->assertStatus(422);
    }

    public function test_anyone_can_list_eans(): void
    {
        $wo = WorkOrder::factory()->create();
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'E1']);

        $r = $this->authOperator()->getJson('/api/v1/packaging/eans');
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_supervisor_can_delete_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $ean = WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'E1']);

        $this->authSupervisor()->deleteJson("/api/v1/packaging/eans/{$ean->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('work_order_eans', ['id' => $ean->id]);
    }

    public function test_operator_cannot_delete_ean(): void
    {
        $wo = WorkOrder::factory()->create();
        $ean = WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'E1']);

        $this->authOperator()->deleteJson("/api/v1/packaging/eans/{$ean->id}")
            ->assertStatus(403);
    }

    // ── Scan ─────────────────────────────────────────────────────────────────

    public function test_operator_can_scan_in_progress_wo(): void
    {
        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 10,
            'packed_qty' => 0,
        ]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => '12345']);

        $r = $this->authOperator()->postJson('/api/v1/packaging/scan', ['ean' => '12345']);
        $r->assertStatus(200)
            ->assertJsonPath('data.work_order.packed_qty', 1);

        $this->assertEquals(1, $wo->fresh()->packed_qty);
        $this->assertDatabaseCount('packaging_scan_logs', 1);
    }

    public function test_scan_unknown_ean_returns_404(): void
    {
        $this->authOperator()->postJson('/api/v1/packaging/scan', ['ean' => 'NONEXISTENT'])
            ->assertStatus(404);
    }

    public function test_scan_pending_wo_rejected(): void
    {
        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_PENDING,
            'planned_qty' => 10,
        ]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'X']);

        $this->authOperator()->postJson('/api/v1/packaging/scan', ['ean' => 'X'])
            ->assertStatus(422);
    }

    public function test_scan_fully_packed_wo_rejected(): void
    {
        $wo = WorkOrder::factory()->create([
            'status' => WorkOrder::STATUS_DONE,
            'planned_qty' => 5,
            'packed_qty' => 5,
        ]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => 'X']);

        $this->authOperator()->postJson('/api/v1/packaging/scan', ['ean' => 'X'])
            ->assertStatus(422);
    }

    // ── Scan logs ────────────────────────────────────────────────────────────

    public function test_scan_logs_paginated(): void
    {
        $wo = WorkOrder::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            PackagingScanLog::create([
                'user_id' => $this->operator->id,
                'work_order_id' => $wo->id,
                'ean' => "E{$i}",
                'product_name' => 'X',
                'scanned_at' => now(),
            ]);
        }

        $r = $this->authAdmin()->getJson('/api/v1/packaging/scan-logs');
        $r->assertStatus(200);
        $this->assertCount(3, $r->json('data'));
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    public function test_stats_returns_structure(): void
    {
        $r = $this->authOperator()->getJson('/api/v1/packaging/stats');
        $r->assertStatus(200)
            ->assertJsonStructure(['data' => ['today_packed', 'plan', 'total_packed', 'backlog', 'shift_start']]);
    }

    public function test_items_returns_only_wos_with_eans(): void
    {
        $woWith = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $woWithout = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        WorkOrderEan::create(['work_order_id' => $woWith->id, 'ean' => 'X']);

        $r = $this->authOperator()->getJson('/api/v1/packaging/items');
        $r->assertStatus(200);
        $ids = collect($r->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($woWith->id));
        $this->assertFalse($ids->contains($woWithout->id));
    }

    public function test_unauthenticated_cannot_scan(): void
    {
        $this->postJson('/api/v1/packaging/scan', ['ean' => 'X'])->assertStatus(401);
    }
}
