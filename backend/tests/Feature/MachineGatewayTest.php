<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MachineGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function opcuaConnection(): MachineConnection
    {
        $conn = MachineConnection::create([
            'name' => 'Line A PLC', 'protocol' => 'opcua', 'is_active' => true, 'status' => 'disconnected',
        ]);
        \App\Models\OpcuaConnection::create([
            'machine_connection_id' => $conn->id,
            'endpoint_url' => 'opc.tcp://plc:4840',
            'security_policy' => 'None', 'security_mode' => 'None', 'auth_mode' => 'anonymous',
            'publishing_interval_ms' => 1000,
        ]);

        return $conn;
    }

    // ── Runtime monitor ──────────────────────────────────────────

    public function test_runtime_reports_not_running_then_running(): void
    {
        $monitor = app(RuntimeMonitor::class);
        $conn = $this->opcuaConnection();

        $this->assertFalse($monitor->connectionRuntime($conn)['alive']);

        $monitor->heartbeat('opcua', $conn->id);
        $this->assertTrue($monitor->connectionRuntime($conn)['alive']);
    }

    public function test_runtime_includes_start_instructions(): void
    {
        $conn = $this->opcuaConnection();
        $rt = app(RuntimeMonitor::class)->connectionRuntime($conn);

        $this->assertStringContainsString('opcua-gateway', $rt['docker']);
        $this->assertEquals('OPC UA gateway', $rt['label']);
    }

    // ── Gateway config + ingest ──────────────────────────────────

    public function test_gateway_config_returns_nodes(): void
    {
        $conn = $this->opcuaConnection();
        $ws = Workstation::factory()->create(['line_id' => Line::factory()]);
        MachineTag::create([
            'machine_connection_id' => $conn->id, 'workstation_id' => $ws->id,
            'name' => 'State', 'address' => 'ns=2;s=State', 'signal_type' => 'state',
            'data_type' => 'int16', 'transform' => ['value_map' => ['1' => 'RUNNING']],
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk()
            ->assertJsonPath('opcua.endpoint_url', 'opc.tcp://plc:4840')
            ->assertJsonPath('tags.0.node_id', 'ns=2;s=State');
    }

    public function test_gateway_ingest_drives_pipeline_and_heartbeat(): void
    {
        $conn = $this->opcuaConnection();
        $ws = Workstation::factory()->create(['line_id' => Line::factory()]);
        $stateTag = MachineTag::create([
            'machine_connection_id' => $conn->id, 'workstation_id' => $ws->id,
            'name' => 'State', 'address' => 'ns=2;s=State', 'signal_type' => 'state',
            'data_type' => 'int16', 'transform' => ['value_map' => ['1' => 'RUNNING', '3' => 'FAULT']],
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/machine-connections/{$conn->id}/signals", [
                'readings' => [['tag_id' => $stateTag->id, 'value' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', 1);

        $this->assertEquals('RUNNING', WorkstationState::where('workstation_id', $ws->id)->whereNull('ended_at')->first()->state);
        $this->assertEquals(1, MachineEvent::where('event_type', 'state_change')->count());
        // Posting refreshed the runtime heartbeat
        $this->assertTrue(app(RuntimeMonitor::class)->isAlive('opcua', $conn->id));
    }

    public function test_gateway_ingest_resolves_by_node_id(): void
    {
        $conn = $this->opcuaConnection();
        $ws = Workstation::factory()->create(['line_id' => Line::factory()]);
        MachineTag::create([
            'machine_connection_id' => $conn->id, 'workstation_id' => $ws->id,
            'name' => 'Good', 'address' => 'ns=2;s=Good', 'signal_type' => 'good_count', 'data_type' => 'int32',
        ]);

        $this->actingAs($this->admin)->postJson("/api/v1/machine-connections/{$conn->id}/signals", [
            'readings' => [['node_id' => 'ns=2;s=Good', 'value' => 0]],
        ])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/machine-connections/{$conn->id}/signals", [
            'readings' => [['node_id' => 'ns=2;s=Good', 'value' => 7]],
        ])->assertJsonPath('accepted', 1);

        $counter = MachineEvent::where('event_type', 'counter')->first();
        $this->assertEquals(7, $counter->payload['delta']);
    }
}
