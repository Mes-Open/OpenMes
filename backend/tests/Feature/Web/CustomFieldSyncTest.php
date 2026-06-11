<?php

namespace Tests\Feature\Web;

use App\Models\CustomFieldDefinition;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5: the Reverb read-path. The custom_field_definitions collection is
 * served as a snapshot, and `custom_fields` rides along on entity collections.
 */
class CustomFieldSyncTest extends TestCase
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

    public function test_definitions_collection_snapshot_is_served(): void
    {
        CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'finish', 'label' => 'Finish', 'type' => 'select', 'position' => 1,
            'config' => ['options' => [['value' => 'matte', 'label' => 'Matte']]],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/collections/custom_field_definitions');

        $response->assertOk()
            ->assertJsonPath('channel', 'col.g.custom_field_definitions');

        $rows = collect($response->json('rows'));
        $this->assertTrue($rows->contains(fn ($r) => $r['key'] === 'finish'));
    }

    public function test_materials_snapshot_includes_custom_fields_column(): void
    {
        $type = MaterialType::factory()->create();
        Material::create([
            'code' => 'CF-SYNC', 'name' => 'Probe', 'material_type_id' => $type->id,
            'default_scrap_percentage' => 0, 'custom_fields' => ['finish' => 'matte'],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/collections/materials')->assertOk();

        $row = collect($response->json('rows'))->firstWhere('code', 'CF-SYNC');
        $this->assertNotNull($row);
        // The column is present in the whitelist (JSON encoded as a string by the
        // raw snapshot query — see ShapeRegistry note).
        $this->assertArrayHasKey('custom_fields', $row);
        $this->assertStringContainsString('matte', (string) $row['custom_fields']);
    }
}
