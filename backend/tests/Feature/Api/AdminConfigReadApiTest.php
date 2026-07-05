<?php

namespace Tests\Feature\Api;

use App\Models\CustomFieldDefinition;
use App\Models\IntegrationConfig;
use App\Models\QualityControlTrigger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-only mobile endpoints for two admin-config areas that previously had no
 * mobile screen: external-system integrations and custom field definitions.
 * Editors stay on the web admin; the encrypted integration `api_config` is
 * never exposed.
 */
class AdminConfigReadApiTest extends TestCase
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

    // ── Integrations ──────────────────────────────────────────────────────

    public function test_admin_can_list_integrations_without_secret_config(): void
    {
        IntegrationConfig::create([
            'system_type' => 'subiekt',
            'system_name' => 'Subiekt GT',
            'api_config' => ['token' => 'super-secret'],
            'is_active' => true,
        ]);

        $r = $this->auth('Admin')->getJson('/api/v1/integrations');

        $r->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'system_type', 'system_name', 'is_active', 'material_sources_count']]])
            ->assertJsonPath('data.0.system_name', 'Subiekt GT');
        // The encrypted credential blob must never leak through the read API.
        $this->assertStringNotContainsString('super-secret', $r->getContent());
        $this->assertStringNotContainsString('api_config', $r->getContent());
    }

    public function test_admin_can_create_update_toggle_delete_integration(): void
    {
        $create = $this->auth('Admin')->postJson('/api/v1/integrations', [
            'system_type' => 'subiekt', 'system_name' => 'Subiekt GT', 'is_active' => true,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.system_type', 'subiekt');
        $id = $create->json('data.id');

        $this->auth('Admin')->patchJson("/api/v1/integrations/{$id}", [
            'system_type' => 'subiekt', 'system_name' => 'Subiekt Nexo',
        ])->assertStatus(200)->assertJsonPath('data.system_name', 'Subiekt Nexo');

        $this->auth('Admin')->postJson("/api/v1/integrations/{$id}/toggle-active")
            ->assertStatus(200)->assertJsonPath('data.is_active', false);

        $this->auth('Admin')->deleteJson("/api/v1/integrations/{$id}")->assertStatus(204);
        $this->assertSoftDeleted('integration_configs', ['id' => $id]);
    }

    public function test_create_integration_requires_unique_type(): void
    {
        IntegrationConfig::create(['system_type' => 'dup', 'system_name' => 'A', 'is_active' => true]);

        $this->auth('Admin')->postJson('/api/v1/integrations', [
            'system_type' => 'dup', 'system_name' => 'B',
        ])->assertStatus(422)->assertJsonValidationErrors(['system_type']);
    }

    public function test_operator_cannot_create_integration(): void
    {
        $this->auth('Operator')->postJson('/api/v1/integrations', [
            'system_type' => 'x', 'system_name' => 'X',
        ])->assertStatus(403);
    }

    public function test_supervisor_cannot_list_integrations(): void
    {
        $this->auth('Supervisor')->getJson('/api/v1/integrations')->assertStatus(403);
    }

    public function test_guest_cannot_list_integrations(): void
    {
        $this->getJson('/api/v1/integrations')->assertStatus(401);
    }

    // ── Custom fields ─────────────────────────────────────────────────────

    public function test_admin_can_list_custom_fields(): void
    {
        CustomFieldDefinition::create([
            'entity_type' => 'work_order', 'key' => 'customer_ref', 'label' => 'Customer reference',
            'type' => 'text', 'config' => [], 'required' => true, 'position' => 1, 'is_active' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'work_order', 'key' => 'channel', 'label' => 'Sales channel',
            'type' => 'select', 'config' => ['options' => ['web', 'retail', 'wholesale']],
            'required' => false, 'position' => 2, 'is_active' => true,
        ]);

        $r = $this->auth('Admin')->getJson('/api/v1/custom-fields');

        $r->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'entity_type', 'entity_label', 'key', 'label', 'type', 'type_label', 'options_count', 'required', 'position', 'is_active']]])
            ->assertJsonPath('data.0.key', 'customer_ref')
            ->assertJsonPath('data.1.options_count', 3);
    }

    public function test_admin_can_crud_custom_field(): void
    {
        $create = $this->auth('Admin')->postJson('/api/v1/custom-fields', [
            'entity_type' => 'work_order', 'key' => 'customer_ref', 'label' => 'Customer ref',
            'type' => 'text', 'required' => true, 'is_active' => true,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.key', 'customer_ref');
        $id = $create->json('data.id');

        $this->auth('Admin')->patchJson("/api/v1/custom-fields/{$id}", [
            'entity_type' => 'work_order', 'key' => 'customer_ref', 'label' => 'Customer reference', 'type' => 'text',
        ])->assertStatus(200)->assertJsonPath('data.label', 'Customer reference');

        $this->auth('Admin')->postJson("/api/v1/custom-fields/{$id}/toggle-active")
            ->assertStatus(200)->assertJsonPath('data.is_active', false);

        $this->auth('Admin')->deleteJson("/api/v1/custom-fields/{$id}")->assertStatus(204);
        $this->assertSoftDeleted('custom_field_definitions', ['id' => $id]);
    }

    public function test_select_custom_field_requires_options(): void
    {
        // A select type with no options must 422.
        $this->auth('Admin')->postJson('/api/v1/custom-fields', [
            'entity_type' => 'work_order', 'key' => 'channel', 'label' => 'Channel', 'type' => 'select',
        ])->assertStatus(422)->assertJsonValidationErrors(['config.options']);

        // With options it succeeds and counts them.
        $this->auth('Admin')->postJson('/api/v1/custom-fields', [
            'entity_type' => 'work_order', 'key' => 'channel', 'label' => 'Channel', 'type' => 'select',
            'config' => ['options' => [['value' => 'web', 'label' => 'Web'], ['value' => 'retail', 'label' => 'Retail']]],
        ])->assertStatus(201)->assertJsonPath('data.options_count', 2);
    }

    public function test_custom_field_key_must_be_valid_slug(): void
    {
        $this->auth('Admin')->postJson('/api/v1/custom-fields', [
            'entity_type' => 'work_order', 'key' => 'Bad Key!', 'label' => 'X', 'type' => 'text',
        ])->assertStatus(422)->assertJsonValidationErrors(['key']);
    }

    public function test_form_meta_lists_entities_and_types(): void
    {
        $this->auth('Admin')->getJson('/api/v1/custom-fields/meta')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['entities' => [['value', 'label']], 'types' => [['value', 'label', 'has_options']]]]);
    }

    public function test_operator_cannot_create_custom_field(): void
    {
        $this->auth('Operator')->postJson('/api/v1/custom-fields', [
            'entity_type' => 'work_order', 'key' => 'x', 'label' => 'X', 'type' => 'text',
        ])->assertStatus(403);
    }

    public function test_supervisor_cannot_list_custom_fields(): void
    {
        $this->auth('Supervisor')->getJson('/api/v1/custom-fields')->assertStatus(403);
    }

    public function test_guest_cannot_list_custom_fields(): void
    {
        $this->getJson('/api/v1/custom-fields')->assertStatus(401);
    }

    // ── Quality-control triggers ──────────────────────────────────────────

    public function test_admin_can_list_quality_control_triggers(): void
    {
        QualityControlTrigger::factory()->create(['name' => 'Every 50 units', 'is_blocking' => true]);

        $r = $this->auth('Admin')->getJson('/api/v1/quality-control-triggers');

        $r->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'trigger_type', 'template_name', 'scope', 'is_blocking', 'is_active']]])
            ->assertJsonPath('data.0.name', 'Every 50 units')
            ->assertJsonPath('data.0.is_blocking', true);
    }

    public function test_admin_can_crud_quality_control_trigger(): void
    {
        $create = $this->auth('Admin')->postJson('/api/v1/quality-control-triggers', [
            'name' => 'In-production check', 'trigger_type' => 'in_production', 'is_blocking' => true, 'is_active' => true,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.name', 'In-production check');
        $id = $create->json('data.id');

        $this->auth('Admin')->patchJson("/api/v1/quality-control-triggers/{$id}", [
            'name' => 'Renamed', 'trigger_type' => 'in_production',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Renamed');

        $this->auth('Admin')->postJson("/api/v1/quality-control-triggers/{$id}/toggle-active")
            ->assertStatus(200)->assertJsonPath('data.is_active', false);

        $this->auth('Admin')->deleteJson("/api/v1/quality-control-triggers/{$id}")->assertStatus(204);
        $this->assertSoftDeleted('quality_control_triggers', ['id' => $id]);
    }

    public function test_frequency_trigger_requires_threshold(): void
    {
        $this->auth('Admin')->postJson('/api/v1/quality-control-triggers', [
            'name' => 'Every N', 'trigger_type' => 'every_n_units',
        ])->assertStatus(422)->assertJsonValidationErrors(['threshold_n']);
    }

    public function test_qc_trigger_form_meta_lists_types_and_scopes(): void
    {
        $this->auth('Admin')->getJson('/api/v1/quality-control-triggers/meta')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['types' => [['value', 'label', 'needs_threshold']], 'templates', 'lines', 'workstations', 'product_types']]);
    }

    public function test_operator_cannot_create_quality_control_trigger(): void
    {
        $this->auth('Operator')->postJson('/api/v1/quality-control-triggers', [
            'name' => 'x', 'trigger_type' => 'in_production',
        ])->assertStatus(403);
    }

    public function test_operator_cannot_list_quality_control_triggers(): void
    {
        $this->auth('Operator')->getJson('/api/v1/quality-control-triggers')->assertStatus(403);
    }

    public function test_guest_cannot_list_quality_control_triggers(): void
    {
        $this->getJson('/api/v1/quality-control-triggers')->assertStatus(401);
    }
}
