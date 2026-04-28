<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolMaintenanceApiTest extends TestCase
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

    // ── Tools ─────────────────────────────────────────────────────────────

    public function test_admin_can_create_tool(): void
    {
        $r = $this->authAdmin()->postJson('/api/v1/tools', [
            'code' => 'TOOL-1', 'name' => 'Drill press',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.status', Tool::STATUS_AVAILABLE);
    }

    public function test_supervisor_cannot_create_tool(): void
    {
        $this->authSupervisor()->postJson('/api/v1/tools', [
            'code' => 'X', 'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_supervisor_can_update_tool(): void
    {
        $t = Tool::create(['code' => 'X', 'name' => 'Old', 'status' => Tool::STATUS_AVAILABLE]);
        $this->authSupervisor()->patchJson("/api/v1/tools/{$t->id}", ['name' => 'New'])
            ->assertStatus(200);
        $this->assertDatabaseHas('tools', ['id' => $t->id, 'name' => 'New']);
    }

    public function test_supervisor_can_transition_tool_status(): void
    {
        $t = Tool::create(['code' => 'X', 'name' => 'X', 'status' => Tool::STATUS_AVAILABLE]);
        $this->authSupervisor()->postJson("/api/v1/tools/{$t->id}/status", ['status' => Tool::STATUS_IN_USE])
            ->assertStatus(200);
        $this->assertEquals(Tool::STATUS_IN_USE, $t->fresh()->status);
    }

    public function test_operator_cannot_update_tool(): void
    {
        $t = Tool::create(['code' => 'X', 'name' => 'X', 'status' => Tool::STATUS_AVAILABLE]);
        $this->authOperator()->patchJson("/api/v1/tools/{$t->id}", ['name' => 'Y'])
            ->assertStatus(403);
    }

    public function test_tool_filter_by_status(): void
    {
        Tool::create(['code' => 'A', 'name' => 'A', 'status' => Tool::STATUS_AVAILABLE]);
        Tool::create(['code' => 'B', 'name' => 'B', 'status' => Tool::STATUS_RETIRED]);
        $r = $this->authAdmin()->getJson('/api/v1/tools?status=available');
        $this->assertCount(1, $r->json('data'));
    }

    public function test_cannot_delete_tool_with_open_maintenance(): void
    {
        $t = Tool::create(['code' => 'X', 'name' => 'X', 'status' => Tool::STATUS_AVAILABLE]);
        MaintenanceEvent::create([
            'title' => 'Service', 'event_type' => 'planned',
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'tool_id' => $t->id,
        ]);
        $this->authAdmin()->deleteJson("/api/v1/tools/{$t->id}")->assertStatus(422);
    }

    // ── Maintenance events ───────────────────────────────────────────────

    public function test_supervisor_can_create_event(): void
    {
        $r = $this->authSupervisor()->postJson('/api/v1/maintenance-events', [
            'title' => 'Quarterly check', 'event_type' => 'inspection',
            'scheduled_at' => '2026-05-01 09:00',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.status', MaintenanceEvent::STATUS_PENDING);
    }

    public function test_operator_cannot_create_event(): void
    {
        $this->authOperator()->postJson('/api/v1/maintenance-events', [
            'title' => 'X', 'event_type' => 'planned',
        ])->assertStatus(403);
    }

    public function test_assigned_operator_can_start_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'Fix', 'event_type' => 'corrective',
            'status' => MaintenanceEvent::STATUS_PENDING,
            'assigned_to_id' => $this->operator->id,
        ]);
        $this->authOperator()->postJson("/api/v1/maintenance-events/{$event->id}/start")
            ->assertStatus(200);
        $this->assertEquals(MaintenanceEvent::STATUS_IN_PROGRESS, $event->fresh()->status);
    }

    public function test_unassigned_operator_cannot_start_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'Fix', 'event_type' => 'corrective',
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
        $this->authOperator()->postJson("/api/v1/maintenance-events/{$event->id}/start")
            ->assertStatus(403);
    }

    public function test_assigned_operator_can_complete_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'Fix', 'event_type' => 'corrective',
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'assigned_to_id' => $this->operator->id,
        ]);
        $this->authOperator()->postJson("/api/v1/maintenance-events/{$event->id}/complete", [
            'resolution_notes' => 'Replaced bearing',
            'actual_cost' => 45.50,
            'currency' => 'EUR',
        ])->assertStatus(200);
        $this->assertEquals(MaintenanceEvent::STATUS_COMPLETED, $event->fresh()->status);
    }

    public function test_cannot_start_in_progress_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'Fix', 'event_type' => 'corrective',
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'assigned_to_id' => $this->supervisor->id,
        ]);
        $this->authSupervisor()->postJson("/api/v1/maintenance-events/{$event->id}/start")
            ->assertStatus(422);
    }

    public function test_supervisor_can_cancel_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'Fix', 'event_type' => 'corrective',
            'status' => MaintenanceEvent::STATUS_PENDING,
        ]);
        $this->authSupervisor()->postJson("/api/v1/maintenance-events/{$event->id}/cancel")
            ->assertStatus(200);
        $this->assertEquals(MaintenanceEvent::STATUS_CANCELLED, $event->fresh()->status);
    }

    public function test_cannot_delete_completed_event(): void
    {
        $event = MaintenanceEvent::create([
            'title' => 'X', 'event_type' => 'planned',
            'status' => MaintenanceEvent::STATUS_COMPLETED,
        ]);
        $this->authAdmin()->deleteJson("/api/v1/maintenance-events/{$event->id}")
            ->assertStatus(422);
    }

    public function test_events_paginated(): void
    {
        for ($i = 0; $i < 3; $i++) {
            MaintenanceEvent::create([
                'title' => "Event {$i}", 'event_type' => 'planned',
                'status' => MaintenanceEvent::STATUS_PENDING,
            ]);
        }
        $r = $this->authAdmin()->getJson('/api/v1/maintenance-events');
        $r->assertStatus(200)->assertJsonStructure(['data', 'meta']);
        $this->assertCount(3, $r->json('data'));
    }
}
