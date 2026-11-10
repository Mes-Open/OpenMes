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

    private function opcuaConnectionWithPassword(): MachineConnection
    {
        $conn = $this->opcuaConnection();
        $conn->opcuaConnection->update([
            'auth_mode' => 'username', 'username' => 'mes',
            'password_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('plc-secret'),
        ]);

        return $conn;
    }

    public function test_gateway_token_receives_the_saved_opcua_password(): void
    {
        $conn = $this->opcuaConnectionWithPassword();

        $this->withToken($this->gatewayToken())
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk()
            ->assertJsonPath('opcua.username', 'mes')
            ->assertJsonPath('opcua.password', 'plc-secret');
    }

    public function test_other_users_do_not_receive_the_opcua_password(): void
    {
        $conn = $this->opcuaConnectionWithPassword();
        Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        // A session, and an operator's full-access token, both pass the route.
        $this->actingAs($operator)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk()
            ->assertJsonPath('opcua.password', null);

        $this->app['auth']->forgetGuards();
        $this->withToken($operator->createToken('full')->plainTextToken)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk()
            ->assertJsonPath('opcua.password', null);
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
        $this->assertEquals(1, MachineEvent::where('event_type', 'state_change')->where('workstation_id', $ws->id)->count());
        // Posting refreshed the runtime heartbeat
        $this->assertTrue(app(RuntimeMonitor::class)->isAlive('opcua', $conn->id));
    }

    public function test_gateway_ingest_resolves_by_node_id(): void
    {
        $conn = $this->opcuaConnection();
        $ws = Workstation::factory()->create(['line_id' => Line::factory()]);
        $tag = MachineTag::create([
            'machine_connection_id' => $conn->id, 'workstation_id' => $ws->id,
            'name' => 'Good', 'address' => 'ns=2;s=Good', 'signal_type' => 'good_count', 'data_type' => 'int32',
        ]);

        $service = app(\App\Services\Machine\MachineCounterService::class);
        $service->configure($service->forSource($tag), ['workstation_id' => $ws->id, 'batch_step_id' => null, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Gateway test'], $this->admin->id);

        $this->actingAs($this->admin)->postJson("/api/v1/machine-connections/{$conn->id}/signals", [
            'readings' => [['node_id' => 'ns=2;s=Good', 'value' => 0, 'ts' => now()->toISOString()]],
        ])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/machine-connections/{$conn->id}/signals", [
            'readings' => [['node_id' => 'ns=2;s=Good', 'value' => 7, 'ts' => now()->toISOString()]],
        ])->assertJsonPath('accepted', 1);

        // Scoped to this test's own workstation: the assertion is about the
        // event this ingest produced, not about the table being empty.
        $counter = MachineEvent::where('event_type', 'counter')
            ->where('workstation_id', $ws->id)
            ->latest('event_timestamp')
            ->first();
        $this->assertEquals(7, $counter->payload['delta']);
    }

    // ── Gateway token scope (#387) ───────────────────────────────

    private function gatewayToken(): string
    {
        return $this->admin->createToken('opcua-gateway', ['machine-gateway'])->plainTextToken;
    }

    public function test_guest_cannot_fetch_gateway_config(): void
    {
        $conn = $this->opcuaConnection();

        $this->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")->assertUnauthorized();
    }

    public function test_gateway_token_can_use_the_gateway_endpoints(): void
    {
        $conn = $this->opcuaConnection();
        $token = $this->gatewayToken();

        $this->withToken($token)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk()
            ->assertJsonPath('connection.id', $conn->id);

        $this->withToken($token)
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat")
            ->assertOk();

        $this->assertTrue(app(RuntimeMonitor::class)->isAlive('opcua', $conn->id));
    }

    public function test_full_access_token_still_works_for_the_gateway(): void
    {
        $conn = $this->opcuaConnection();
        $token = $this->admin->createToken('legacy')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertOk();
    }

    public function test_token_without_gateway_ability_is_rejected(): void
    {
        $conn = $this->opcuaConnection();
        $token = $this->admin->createToken('other', ['password:change'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/machine-connections/{$conn->id}/gateway-config")
            ->assertForbidden();
    }

    public function test_gateway_token_is_rejected_by_the_rest_of_the_api(): void
    {
        $this->withToken($this->gatewayToken())
            ->getJson('/api/v1/lines')
            ->assertForbidden();
    }

    public function test_gateway_token_is_rejected_by_module_routes(): void
    {
        // A module registers its own `api` + `auth:sanctum` group outside
        // routes/api.php (e.g. modules/Enterprise) — the block must still apply.
        \Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])
            ->get('/api/v1/test-module-route', fn () => response()->json(['ok' => true]));

        $this->withToken($this->gatewayToken())
            ->getJson('/api/v1/test-module-route')
            ->assertForbidden();
    }

    public function test_full_token_and_session_still_reach_module_routes(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])
            ->get('/api/v1/test-module-route', fn () => response()->json(['ok' => true]));

        $this->withToken($this->admin->createToken('full')->plainTextToken)
            ->getJson('/api/v1/test-module-route')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/test-module-route')
            ->assertOk();
    }

    public function test_gateway_token_cannot_be_refreshed_into_a_full_token(): void
    {
        $this->withToken($this->gatewayToken())
            ->postJson('/api/auth/refresh')
            ->assertForbidden();

        $this->assertSame(1, $this->admin->tokens()->count());
    }

    // ── Error heartbeat ──────────────────────────────────────────

    public function test_error_heartbeat_marks_connection_error_but_gateway_alive(): void
    {
        $conn = $this->opcuaConnection();

        $this->withToken($this->gatewayToken())
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat", [
                'status' => 'error',
                'message' => 'Cannot connect to opc.tcp://plc:4840: ECONNREFUSED',
            ])
            ->assertOk();

        $conn->refresh();
        $this->assertSame(MachineConnection::STATUS_ERROR, $conn->status);
        $this->assertStringContainsString('ECONNREFUSED', $conn->status_message);

        $rt = app(RuntimeMonitor::class)->connectionRuntime($conn);
        $this->assertTrue($rt['alive']);
        $this->assertStringContainsString('ECONNREFUSED', $rt['error']);
    }

    public function test_error_heartbeat_leaves_station_freshness_unchanged(): void
    {
        $conn = $this->opcuaConnection();
        $ws = Workstation::factory()->create(['line_id' => Line::factory()]);
        MachineTag::create([
            'machine_connection_id' => $conn->id, 'workstation_id' => $ws->id,
            'name' => 'State', 'address' => 'ns=2;s=State', 'signal_type' => 'state', 'data_type' => 'int16',
        ]);
        $state = WorkstationState::create([
            'workstation_id' => $ws->id, 'state' => 'RUNNING', 'source' => 'machine', 'started_at' => now()->subHour(),
        ]);
        $old = now()->subHour()->startOfSecond();
        WorkstationState::whereKey($state->id)->update(['updated_at' => $old]);
        $token = $this->gatewayToken();

        $this->withToken($token)
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat", ['status' => 'error', 'message' => 'Lost connection'])
            ->assertOk();
        $this->assertTrue($state->fresh()->updated_at->equalTo($old));

        // Positive control: a healthy heartbeat does refresh it.
        $this->withToken($token)
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat")
            ->assertOk();
        $this->assertTrue($state->fresh()->updated_at->greaterThan($old));
    }

    public function test_ok_heartbeat_clears_a_previous_error(): void
    {
        $conn = $this->opcuaConnection();
        $conn->markError('Cannot connect');

        $this->withToken($this->gatewayToken())
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat")
            ->assertOk();

        $conn->refresh();
        $this->assertSame(MachineConnection::STATUS_CONNECTED, $conn->status);
        $this->assertNull(app(RuntimeMonitor::class)->connectionRuntime($conn)['error']);
    }

    public function test_error_heartbeat_requires_a_message(): void
    {
        $conn = $this->opcuaConnection();

        $this->withToken($this->gatewayToken())
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat", ['status' => 'error'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_heartbeat_rejects_unknown_status(): void
    {
        $conn = $this->opcuaConnection();

        $this->withToken($this->gatewayToken())
            ->postJson("/api/v1/machine-connections/{$conn->id}/heartbeat", ['status' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    // ── Runtime hints ────────────────────────────────────────────

    public function test_runtime_command_includes_the_api_token_and_settings_link(): void
    {
        $rt = app(RuntimeMonitor::class)->connectionRuntime($this->opcuaConnection());

        $this->assertStringContainsString('OPENMES_API_TOKEN=<token>', $rt['docker']);
        $this->assertSame('/settings/api-tokens?scope=machine-gateway', $rt['token_url']);
        $this->assertNull($rt['endpoint_warning']);
    }

    /**
     * @dataProvider loopbackEndpoints
     */
    public function test_runtime_warns_about_a_loopback_endpoint(string $endpoint): void
    {
        $conn = $this->opcuaConnection();
        $conn->opcuaConnection->update(['endpoint_url' => $endpoint]);

        $rt = app(RuntimeMonitor::class)->connectionRuntime($conn->fresh());

        $this->assertStringContainsString('host.docker.internal', $rt['endpoint_warning']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function loopbackEndpoints(): array
    {
        return [
            'localhost' => ['opc.tcp://localhost:4840'],
            'ipv4' => ['opc.tcp://127.0.0.1:4840'],
            'ipv6 short' => ['opc.tcp://[::1]:4840'],
            'ipv6 expanded' => ['opc.tcp://[0:0:0:0:0:0:0:1]:4840'],
            'ipv6 zero padded' => ['opc.tcp://[0000:0000:0000:0000:0000:0000:0000:0001]:4840/path'],
        ];
    }

    public function test_runtime_does_not_warn_about_another_ipv6_host(): void
    {
        $conn = $this->opcuaConnection();
        $conn->opcuaConnection->update(['endpoint_url' => 'opc.tcp://[2001:db8::1]:4840']);

        $rt = app(RuntimeMonitor::class)->connectionRuntime($conn->fresh());

        $this->assertNull($rt['endpoint_warning']);
    }
}
