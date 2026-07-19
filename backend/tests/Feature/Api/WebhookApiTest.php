<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_webhooks(): void
    {
        Webhook::factory()->create(['name' => 'Slack', 'is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/webhooks')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'url', 'events', 'events_count', 'is_active', 'last_triggered_at']],
            ])
            ->assertJsonFragment(['name' => 'Slack']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $webhook = Webhook::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/webhooks/{$webhook->id}/toggle-active")
            ->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_admin_can_delete_webhook(): void
    {
        $webhook = Webhook::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/webhooks/{$webhook->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('webhooks', ['id' => $webhook->id]);
    }

    public function test_admin_can_create_webhook(): void
    {
        $r = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/webhooks', [
            'name' => 'New hook',
            'url' => 'https://8.8.8.8/hook',
            'events' => ['work_order.status_changed'],
            'is_active' => true,
        ]);

        $r->assertStatus(201)->assertJsonPath('data.name', 'New hook');
        // Secret is generated but never returned.
        $this->assertStringNotContainsString('secret', $r->getContent());
        $this->assertDatabaseHas('webhooks', ['name' => 'New hook']);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/webhooks', ['name' => '', 'url' => 'not-a-url', 'events' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'url', 'events']);
    }

    public function test_admin_can_update_webhook(): void
    {
        $webhook = Webhook::factory()->create(['name' => 'Old', 'events' => ['issue.created']]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/webhooks/{$webhook->id}", [
                'name' => 'Renamed',
                'url' => 'https://8.8.8.8/v2',
                'events' => ['batch.completed'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.events', ['batch.completed']);
    }

    public function test_event_types_catalog_is_listable(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/webhook-event-types')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['key', 'label']]]);
    }

    public function test_operator_cannot_create_webhook(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/webhooks', [
                'name' => 'x', 'url' => 'https://example.com', 'events' => ['issue.created'],
            ])
            ->assertStatus(403);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/webhooks')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/webhooks')
            ->assertStatus(403);
    }
}
