<?php

namespace Tests\Feature\Api;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\MachineConnection;
use App\Models\MaintenanceEvent;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemApiTest extends TestCase
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

    // ── Settings ──────────────────────────────────────────────────────────

    public function test_admin_can_list_settings(): void
    {
        $r = $this->authAdmin()->getJson('/api/v1/system/settings');
        $r->assertStatus(200);
        $keys = collect($r->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('allow_overproduction'));
    }

    public function test_admin_can_update_setting(): void
    {
        $this->authAdmin()->putJson('/api/v1/system/settings/allow_overproduction', [
            'value' => true,
        ])->assertStatus(200);

        $row = DB::table('system_settings')->where('key', 'allow_overproduction')->first();
        $this->assertEquals('true', $row->value);
    }

    public function test_supervisor_cannot_update_setting(): void
    {
        $this->authSupervisor()->putJson('/api/v1/system/settings/allow_overproduction', [
            'value' => true,
        ])->assertStatus(403);
    }

    public function test_unknown_setting_returns_404(): void
    {
        $this->authAdmin()->getJson('/api/v1/system/settings/nonexistent')->assertStatus(404);
    }

    // ── Modules ───────────────────────────────────────────────────────────

    public function test_admin_can_list_modules(): void
    {
        $r = $this->authAdmin()->getJson('/api/v1/system/modules');
        $r->assertStatus(200);
        // Modules folder includes Packaging at minimum
        $names = collect($r->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Packaging'));
    }

    public function test_admin_can_enable_disable_module(): void
    {
        $this->authAdmin()->postJson('/api/v1/system/modules/Packaging/enable')->assertStatus(200);
        $this->authAdmin()->postJson('/api/v1/system/modules/Packaging/disable')->assertStatus(200);
    }

    public function test_supervisor_cannot_manage_modules(): void
    {
        $this->authSupervisor()->getJson('/api/v1/system/modules')->assertStatus(403);
    }

    // ── Schedule ──────────────────────────────────────────────────────────

    public function test_schedule_returns_events(): void
    {
        MaintenanceEvent::create([
            'title' => 'Service', 'event_type' => 'planned',
            'status' => 'pending',
            'scheduled_at' => now()->addDays(2),
        ]);
        WorkOrder::factory()->create([
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
        $r = $this->authSupervisor()->getJson(
            '/api/v1/system/schedule?from=' . now()->toDateString() . '&to=' . now()->addDays(7)->toDateString()
        );
        $r->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($r->json('data')));
    }

    public function test_schedule_filtered_by_type(): void
    {
        MaintenanceEvent::create([
            'title' => 'Service', 'event_type' => 'planned',
            'status' => 'pending',
            'scheduled_at' => now()->addDays(2),
        ]);
        WorkOrder::factory()->create(['due_date' => now()->addDays(3)->toDateString()]);

        $r = $this->authSupervisor()->getJson(
            '/api/v1/system/schedule?from=' . now()->toDateString()
            . '&to=' . now()->addDays(7)->toDateString() . '&type=maintenance'
        );
        $types = collect($r->json('data'))->pluck('type')->unique();
        $this->assertTrue($types->contains('maintenance'));
        $this->assertFalse($types->contains('work_order'));
    }

    // ── Alerts ────────────────────────────────────────────────────────────

    public function test_alerts_counts(): void
    {
        $type = IssueType::factory()->create();
        $wo = WorkOrder::factory()->create();
        Issue::factory()->create([
            'work_order_id' => $wo->id,
            'issue_type_id' => $type->id,
            'reported_by_id' => $this->operator->id,
            'status' => Issue::STATUS_OPEN,
        ]);
        MaintenanceEvent::create([
            'title' => 'X', 'event_type' => 'corrective',
            'status' => 'pending',
        ]);
        MachineConnection::create([
            'name' => 'C', 'protocol' => 'mqtt',
            'is_active' => true, 'status' => 'error',
        ]);

        $r = $this->authSupervisor()->getJson('/api/v1/system/alerts/counts');
        $r->assertStatus(200);
        $data = $r->json('data');
        $this->assertEquals(1, $data['issues']);
        $this->assertEquals(1, $data['maintenance']);
        $this->assertEquals(1, $data['machines']);
        $this->assertEquals(3, $data['total']);
    }

    public function test_operator_cannot_see_alerts(): void
    {
        $this->authOperator()->getJson('/api/v1/system/alerts/counts')->assertStatus(403);
    }

    public function test_alerts_filter_by_type(): void
    {
        $type = IssueType::factory()->create();
        $wo = WorkOrder::factory()->create();
        Issue::factory()->create([
            'work_order_id' => $wo->id,
            'issue_type_id' => $type->id,
            'reported_by_id' => $this->operator->id,
            'status' => Issue::STATUS_OPEN,
        ]);
        MaintenanceEvent::create([
            'title' => 'X', 'event_type' => 'corrective',
            'status' => 'pending',
        ]);

        $r = $this->authSupervisor()->getJson('/api/v1/system/alerts?type=maintenance');
        $types = collect($r->json('data'))->pluck('type')->unique();
        $this->assertTrue($types->contains('maintenance'));
        $this->assertFalse($types->contains('issue'));
    }

    // ── Update check ──────────────────────────────────────────────────────

    public function test_update_check_returns_version(): void
    {
        $r = $this->authOperator()->getJson('/api/v1/system/update-check');
        $r->assertStatus(200)
            ->assertJsonStructure(['data' => ['current_version', 'latest_version', 'update_available']]);
    }
}
