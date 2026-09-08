<?php

namespace Tests\Feature\Connectivity;

use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\MqttConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The MQTT device form can assign the device to a production line (the default
 * target for its topic mappings).
 */
class MqttConnectionLineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Beam sensor',
            'broker_host' => 'broker.local',
            'broker_port' => 1883,
            'keep_alive_seconds' => 60,
            'qos_default' => 0,
            'connect_timeout' => 10,
            'reconnect_delay_seconds' => 5,
        ], $override);
    }

    public function test_admin_can_assign_a_line_when_creating_a_device(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.connectivity.mqtt.store'), $this->payload(['line_id' => $line->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('machine_connections', [
            'name' => 'Beam sensor',
            'line_id' => $line->id,
        ]);
    }

    public function test_admin_can_change_and_clear_the_assigned_line(): void
    {
        $line = Line::factory()->create();
        $conn = MachineConnection::create([
            'name' => 'Dev', 'protocol' => 'mqtt', 'line_id' => $line->id,
            'is_active' => false, 'status' => 'disconnected',
        ]);
        MqttConnection::create([
            'machine_connection_id' => $conn->id, 'broker_host' => 'x', 'broker_port' => 1883,
            'keep_alive_seconds' => 60, 'qos_default' => 0, 'connect_timeout' => 10, 'reconnect_delay_seconds' => 5,
        ]);

        // Clear the line.
        $this->actingAs($this->admin)
            ->put(route('admin.connectivity.mqtt.update', $conn), $this->payload(['line_id' => null]))
            ->assertRedirect();

        $this->assertNull($conn->fresh()->line_id);
    }

    public function test_a_nonexistent_line_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.connectivity.mqtt.store'), $this->payload(['line_id' => 999999]))
            ->assertSessionHasErrors('line_id');
    }

    public function test_a_line_from_another_tenant_is_rejected(): void
    {
        // A line that belongs to tenant B.
        $tenant = app(TenantContext::class);
        $tenantB = Tenant::factory()->create();
        $tenant->set($tenantB->id);
        $foreignLine = Line::factory()->create();
        $tenant->clear();

        // The admin (no tenant) must not be able to bind the device to it.
        $this->actingAs($this->admin)
            ->post(route('admin.connectivity.mqtt.store'), $this->payload(['line_id' => $foreignLine->id]))
            ->assertSessionHasErrors('line_id');

        $this->assertDatabaseMissing('machine_connections', ['line_id' => $foreignLine->id]);
    }

    public function test_a_non_admin_cannot_create_a_device(): void
    {
        $operator = tap(User::factory()->create(), fn ($u) => $u->assignRole('Operator'));

        $this->actingAs($operator)
            ->post(route('admin.connectivity.mqtt.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('machine_connections', 0);
    }

    public function test_invalid_action_params_json_is_rejected_and_not_persisted(): void
    {
        $conn = MachineConnection::create([
            'name' => 'C', 'protocol' => 'mqtt', 'is_active' => false, 'status' => 'disconnected',
        ]);
        $topic = MachineTopic::create([
            'machine_connection_id' => $conn->id, 'topic_pattern' => 't', 'payload_format' => 'json', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.connectivity.mqtt.topics.mappings.store', [$conn, $topic]),
            ['action_type' => 'log_event', 'priority' => 100, 'action_params' => '{not valid json']
        )->assertSessionHasErrors('action_params');

        $this->assertDatabaseCount('topic_mappings', 0);
    }
}
