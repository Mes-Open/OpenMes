<?php

namespace Tests\Feature\Web;

use App\Models\CustomFieldDefinition;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Custom fields flow through the standard admin Material endpoints: their
 * definitions drive validation, values are coerced/pruned, and they persist
 * into the entity's custom_fields JSON column.
 */
class CustomFieldMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MaterialType $materialType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->materialType = MaterialType::factory()->create();

        // A required dropdown and an optional whole-number field on materials.
        CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Color',
            'type' => 'select', 'required' => true, 'position' => 1,
            'config' => ['options' => [
                ['value' => 'red', 'label' => 'Red'],
                ['value' => 'blue', 'label' => 'Blue'],
            ]],
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'shelf_qty', 'label' => 'Shelf qty',
            'type' => 'integer', 'required' => false, 'position' => 2, 'config' => ['min' => 0],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'MAT-CF-1',
            'name' => 'Custom Field Material',
            'material_type_id' => $this->materialType->id,
            'custom_fields' => ['color' => 'red', 'shelf_qty' => '5'],
        ], $overrides);
    }

    public function test_admin_can_create_material_with_custom_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/materials', $this->payload());

        $response->assertRedirect(route('admin.materials.index'));
        $response->assertSessionHasNoErrors();

        $material = Material::where('code', 'MAT-CF-1')->firstOrFail();
        // shelf_qty was submitted as a string but stored as an int.
        $this->assertSame('red', $material->custom_fields['color']);
        $this->assertSame(5, $material->custom_fields['shelf_qty']);
    }

    public function test_required_custom_field_is_enforced(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/materials/create')
            ->post('/admin/materials', $this->payload(['custom_fields' => ['shelf_qty' => '5']]));

        $response->assertSessionHasErrors('custom_fields.color');
        $this->assertDatabaseMissing('materials', ['code' => 'MAT-CF-1']);
    }

    public function test_invalid_select_option_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/materials/create')
            ->post('/admin/materials', $this->payload(['custom_fields' => ['color' => 'green']]));

        $response->assertSessionHasErrors('custom_fields.color');
        $this->assertDatabaseMissing('materials', ['code' => 'MAT-CF-1']);
    }

    public function test_unknown_custom_field_key_is_pruned(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/materials', $this->payload([
                'custom_fields' => ['color' => 'blue', 'rogue' => 'drop me'],
            ]))
            ->assertSessionHasNoErrors();

        $material = Material::where('code', 'MAT-CF-1')->firstOrFail();
        $this->assertSame('blue', $material->custom_fields['color']);
        $this->assertArrayNotHasKey('rogue', $material->custom_fields);
    }

    public function test_admin_can_update_material_custom_fields(): void
    {
        $material = Material::factory()->create([
            'material_type_id' => $this->materialType->id,
            'custom_fields' => ['color' => 'red'],
        ]);

        $response = $this->actingAs($this->admin)->put("/admin/materials/{$material->id}", [
            'code' => $material->code,
            'name' => $material->name,
            'material_type_id' => $this->materialType->id,
            'is_active' => true,
            'custom_fields' => ['color' => 'blue'],
        ]);

        $response->assertRedirect(route('admin.materials.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('blue', $material->fresh()->custom_fields['color']);
    }
}
