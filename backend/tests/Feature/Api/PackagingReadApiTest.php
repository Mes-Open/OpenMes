<?php

namespace Tests\Feature\Api;

use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-only mobile endpoints for the (now core) packaging feature: the pallets
 * list and the label-template catalog. Create/edit stay on the web admin.
 */
class PackagingReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function auth(string $role)
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken);
    }

    // ── Pallets ───────────────────────────────────────────────────────────

    public function test_supervisor_can_list_pallets_with_work_order(): void
    {
        $pallet = Pallet::factory()->create(['location' => 'A-12']);

        $r = $this->auth('Supervisor')->getJson('/api/v1/pallets');

        $r->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'pallet_no', 'order_no', 'qty', 'status', 'quality_status', 'location']]])
            ->assertJsonPath('data.0.id', $pallet->id)
            ->assertJsonPath('data.0.location', 'A-12');
    }

    public function test_pallets_filter_by_status(): void
    {
        Pallet::factory()->create(['status' => 'open']);
        Pallet::factory()->create(['status' => 'closed']);

        $r = $this->auth('Admin')->getJson('/api/v1/pallets?status=closed');

        $r->assertStatus(200);
        foreach ($r->json('data') as $row) {
            $this->assertSame('closed', $row['status']);
        }
    }

    public function test_admin_can_crud_pallet(): void
    {
        $wo = \App\Models\WorkOrder::factory()->create();

        $create = $this->auth('Admin')->postJson('/api/v1/pallets', [
            'work_order_id' => $wo->id, 'qty' => 10, 'status' => 'open', 'location' => 'A-1',
        ]);
        $create->assertStatus(201)->assertJsonPath('data.location', 'A-1');
        $id = $create->json('data.id');

        $this->auth('Admin')->patchJson("/api/v1/pallets/{$id}", [
            'work_order_id' => $wo->id, 'qty' => 20, 'status' => 'closed', 'location' => 'B-2',
        ])->assertStatus(200)->assertJsonPath('data.qty', 20)->assertJsonPath('data.status', 'closed');

        $this->auth('Admin')->deleteJson("/api/v1/pallets/{$id}")->assertStatus(204);
        $this->assertSoftDeleted('pallets', ['id' => $id]);
    }

    public function test_create_pallet_validates_required_fields(): void
    {
        $this->auth('Admin')->postJson('/api/v1/pallets', ['qty' => -5])
            ->assertStatus(422)->assertJsonValidationErrors(['work_order_id', 'status', 'qty']);
    }

    public function test_pallet_form_meta_lists_statuses_and_work_orders(): void
    {
        \App\Models\WorkOrder::factory()->create();
        $this->auth('Supervisor')->getJson('/api/v1/pallets/meta')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['statuses' => [['value', 'label']], 'work_orders' => [['id', 'order_no']]]]);
    }

    public function test_operator_cannot_create_pallet(): void
    {
        $wo = \App\Models\WorkOrder::factory()->create();
        $this->auth('Operator')->postJson('/api/v1/pallets', [
            'work_order_id' => $wo->id, 'status' => 'open',
        ])->assertStatus(403);
    }

    public function test_operator_cannot_list_pallets(): void
    {
        $this->auth('Operator')->getJson('/api/v1/pallets')->assertStatus(403);
    }

    public function test_guest_cannot_list_pallets(): void
    {
        $this->getJson('/api/v1/pallets')->assertStatus(401);
    }

    // ── Label templates ───────────────────────────────────────────────────

    public function test_admin_can_list_label_templates_default_first(): void
    {
        LabelTemplate::create([
            'name' => 'Alt WO', 'type' => 'work_order', 'size' => '80x40',
            'barcode_format' => 'code128', 'fields_config' => ['wo_number' => true, 'barcode' => true],
            'is_default' => false, 'is_active' => true,
        ]);
        LabelTemplate::create([
            'name' => 'Default Pallet', 'type' => 'pallet', 'size' => '100x100',
            'barcode_format' => 'ean13', 'fields_config' => ['pallet_no' => true],
            'is_default' => true, 'is_active' => true,
        ]);

        $r = $this->auth('Admin')->getJson('/api/v1/label-templates');

        $r->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'type', 'type_label', 'size', 'barcode_format', 'fields_count', 'is_default', 'is_active']]])
            ->assertJsonPath('data.0.is_default', true) // default sorts first
            ->assertJsonPath('data.0.fields_count', 1);
    }

    public function test_admin_can_crud_label_template_and_set_default(): void
    {
        $payload = [
            'name' => 'WO label', 'type' => 'work_order', 'size' => '100x50', 'barcode_format' => 'code128',
            'fields_config' => ['wo_number' => true, 'barcode' => true], 'is_default' => true, 'is_active' => true,
        ];
        $create = $this->auth('Admin')->postJson('/api/v1/label-templates', $payload);
        $create->assertStatus(201)->assertJsonPath('data.fields_count', 2)->assertJsonPath('data.is_default', true);
        $id = $create->json('data.id');

        // A second default of the same type demotes the first.
        $second = $this->auth('Admin')->postJson('/api/v1/label-templates', array_merge($payload, ['name' => 'WO label 2']));
        $secondId = $second->json('data.id');
        $this->assertFalse(\App\Models\LabelTemplate::find($id)->is_default);

        $this->auth('Admin')->patchJson("/api/v1/label-templates/{$id}", array_merge($payload, ['name' => 'WO renamed', 'is_default' => false]))
            ->assertStatus(200)->assertJsonPath('data.name', 'WO renamed');

        $this->auth('Admin')->postJson("/api/v1/label-templates/{$id}/set-default")
            ->assertStatus(200)->assertJsonPath('data.is_default', true);
        $this->assertFalse(\App\Models\LabelTemplate::find($secondId)->is_default);

        $this->auth('Admin')->deleteJson("/api/v1/label-templates/{$id}")->assertStatus(204);
        $this->assertSoftDeleted('label_templates', ['id' => $id]);
    }

    public function test_create_label_template_validates_enums(): void
    {
        $this->auth('Admin')->postJson('/api/v1/label-templates', [
            'name' => '', 'type' => 'bogus', 'size' => 'bogus', 'barcode_format' => 'bogus',
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'type', 'size', 'barcode_format']);
    }

    public function test_label_template_form_meta_lists_catalogs(): void
    {
        $this->auth('Supervisor')->getJson('/api/v1/label-templates/meta')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['types' => [['value', 'label']], 'sizes', 'barcode_formats', 'fields']]);
    }

    public function test_operator_cannot_create_label_template(): void
    {
        $this->auth('Operator')->postJson('/api/v1/label-templates', [
            'name' => 'x', 'type' => 'work_order', 'size' => '100x50', 'barcode_format' => 'code128',
        ])->assertStatus(403);
    }

    public function test_operator_cannot_list_label_templates(): void
    {
        $this->auth('Operator')->getJson('/api/v1/label-templates')->assertStatus(403);
    }

    public function test_guest_cannot_list_label_templates(): void
    {
        $this->getJson('/api/v1/label-templates')->assertStatus(401);
    }
}
