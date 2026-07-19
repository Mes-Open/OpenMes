<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ViewTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewTemplateApiTest extends TestCase
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

    public function test_admin_can_list_view_templates(): void
    {
        ViewTemplate::create(['name' => 'Compact', 'description' => 'Few columns', 'columns' => ['order_no', 'status']]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/view-templates')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'lines_count', 'columns_count']]])
            ->assertJsonFragment(['name' => 'Compact', 'columns_count' => 2]);
    }

    public function test_admin_can_delete_view_template(): void
    {
        $vt = ViewTemplate::create(['name' => 'Temp', 'columns' => []]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/view-templates/{$vt->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('view_templates', ['id' => $vt->id]);
    }

    public function test_admin_can_create_and_update_view_template(): void
    {
        $cols = [
            ['label' => 'Order', 'key' => 'order_no', 'source' => 'field'],
            ['label' => 'Customer', 'key' => 'customer', 'source' => 'extra_data'],
        ];
        $create = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/view-templates', [
            'name' => 'Line A view', 'description' => 'Two columns', 'columns' => $cols,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.columns_count', 2)->assertJsonPath('data.columns.0.key', 'order_no');
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/v1/view-templates/{$id}", [
            'name' => 'Line A view', 'columns' => [['label' => 'Order', 'key' => 'order_no', 'source' => 'field']],
        ])->assertStatus(200)->assertJsonPath('data.columns_count', 1);
    }

    public function test_create_view_template_validation(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/view-templates', [
            'name' => '', 'columns' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'columns']);

        // A column missing its required source must 422.
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/view-templates', [
            'name' => 'Bad', 'columns' => [['label' => 'X', 'key' => 'x']],
        ])->assertStatus(422)->assertJsonValidationErrors(['columns.0.source']);
    }

    public function test_operator_cannot_create_view_template(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')->postJson('/api/v1/view-templates', [
            'name' => 'x', 'columns' => [['label' => 'X', 'key' => 'x', 'source' => 'field']],
        ])->assertStatus(403);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/view-templates')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/view-templates')
            ->assertStatus(403);
    }
}
