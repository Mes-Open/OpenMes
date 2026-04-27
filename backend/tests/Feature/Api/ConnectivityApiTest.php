<?php

namespace Tests\Feature\Api;

use App\Models\MachineConnection;
use App\Models\MachineMessage;
use App\Models\MachineTopic;
use App\Models\MqttConnection;
use App\Models\TopicMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectivityApiTest extends TestCase
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

    private function makeConnection(array $attrs = []): MachineConnection
    {
        return MachineConnection::create(array_merge([
            'name' => 'Conn 1', 'protocol' => 'mqtt', 'is_active' => true,
            'status' => 'disconnected',
        ], $attrs));
    }

    public function test_admin_can_list_connections(): void
    {
        $this->makeConnection();
        $r = $this->authAdmin()->getJson('/api/v1/connectivity/connections');
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_supervisor_cannot_list_connections(): void
    {
        $this->authSupervisor()->getJson('/api/v1/connectivity/connections')->assertStatus(403);
    }

    public function test_operator_cannot_list_connections(): void
    {
        $this->authOperator()->getJson('/api/v1/connectivity/connections')->assertStatus(403);
    }

    public function test_admin_can_view_connection(): void
    {
        $c = $this->makeConnection();
        $r = $this->authAdmin()->getJson("/api/v1/connectivity/connections/{$c->id}");
        $r->assertStatus(200)->assertJsonPath('data.id', $c->id);
    }

    public function test_admin_can_toggle_connection(): void
    {
        $c = $this->makeConnection(['is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/connectivity/connections/{$c->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($c->fresh()->is_active);
    }

    public function test_admin_can_delete_connection(): void
    {
        $c = $this->makeConnection();
        $this->authAdmin()->deleteJson("/api/v1/connectivity/connections/{$c->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('machine_connections', ['id' => $c->id]);
    }

    public function test_mqtt_password_redacted(): void
    {
        $c = $this->makeConnection();
        MqttConnection::create([
            'machine_connection_id' => $c->id,
            'broker_host' => 'localhost', 'broker_port' => 1883,
            'password_encrypted' => 'secret-stuff',
        ]);
        $r = $this->authAdmin()->getJson("/api/v1/connectivity/connections/{$c->id}/mqtt");
        $r->assertStatus(200);
        $this->assertArrayNotHasKey('password_encrypted', $r->json('data'));
    }

    public function test_topics_filter_by_connection(): void
    {
        $c1 = $this->makeConnection();
        $c2 = $this->makeConnection(['name' => 'Conn 2']);
        MachineTopic::create(['machine_connection_id' => $c1->id, 'topic_pattern' => 'a/+', 'is_active' => true]);
        MachineTopic::create(['machine_connection_id' => $c2->id, 'topic_pattern' => 'b/+', 'is_active' => true]);

        $r = $this->authAdmin()->getJson("/api/v1/connectivity/topics?machine_connection_id={$c1->id}");
        $this->assertCount(1, $r->json('data'));
    }

    public function test_admin_can_delete_topic(): void
    {
        $c = $this->makeConnection();
        $t = MachineTopic::create(['machine_connection_id' => $c->id, 'topic_pattern' => 'x/y', 'is_active' => true]);
        $this->authAdmin()->deleteJson("/api/v1/connectivity/topics/{$t->id}")->assertStatus(200);
    }

    public function test_admin_can_toggle_topic(): void
    {
        $c = $this->makeConnection();
        $t = MachineTopic::create(['machine_connection_id' => $c->id, 'topic_pattern' => 'x/y', 'is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/connectivity/topics/{$t->id}/toggle-active")->assertStatus(200);
        $this->assertFalse($t->fresh()->is_active);
    }

    public function test_mappings_listed(): void
    {
        $c = $this->makeConnection();
        $t = MachineTopic::create(['machine_connection_id' => $c->id, 'topic_pattern' => 'x', 'is_active' => true]);
        TopicMapping::create([
            'machine_topic_id' => $t->id, 'field_path' => 'value',
            'action_type' => 'update_batch_step', 'priority' => 0, 'is_active' => true,
        ]);
        $r = $this->authAdmin()->getJson("/api/v1/connectivity/mappings?machine_topic_id={$t->id}");
        $this->assertCount(1, $r->json('data'));
    }

    public function test_admin_can_delete_mapping(): void
    {
        $c = $this->makeConnection();
        $t = MachineTopic::create(['machine_connection_id' => $c->id, 'topic_pattern' => 'x', 'is_active' => true]);
        $m = TopicMapping::create([
            'machine_topic_id' => $t->id, 'field_path' => 'v',
            'action_type' => 'update_batch_step', 'priority' => 0, 'is_active' => true,
        ]);
        $this->authAdmin()->deleteJson("/api/v1/connectivity/mappings/{$m->id}")->assertStatus(200);
    }

    public function test_messages_paginated_and_filtered(): void
    {
        $c = $this->makeConnection();
        for ($i = 0; $i < 3; $i++) {
            MachineMessage::create([
                'machine_connection_id' => $c->id,
                'topic' => "t/{$i}",
                'raw_payload' => '{}',
                'processing_status' => 'ok',
                'received_at' => now(),
            ]);
        }
        $r = $this->authAdmin()->getJson("/api/v1/connectivity/messages?machine_connection_id={$c->id}");
        $r->assertStatus(200)->assertJsonStructure(['data', 'meta']);
        $this->assertCount(3, $r->json('data'));
    }

    public function test_supervisor_cannot_access_messages(): void
    {
        $this->authSupervisor()->getJson('/api/v1/connectivity/messages')->assertStatus(403);
    }
}
