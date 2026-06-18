<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Material type is an optional field: a material can be created/updated without
 * one, but an invalid (non-existent) type is still rejected.
 */
class MaterialTypeOptionalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_material_can_be_created_without_a_material_type(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/materials', [
                'code' => 'NO-TYPE-1',
                'name' => 'Untyped material',
                'unit_of_measure' => 'pcs',
            ])
            ->assertRedirect(route('admin.materials.index'));

        $this->assertDatabaseHas('materials', [
            'code' => 'NO-TYPE-1',
            'material_type_id' => null,
        ]);
    }

    public function test_material_can_still_be_created_with_a_material_type(): void
    {
        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);

        $this->actingAs($this->admin)
            ->post('/admin/materials', [
                'code' => 'TYPED-1',
                'name' => 'Typed material',
                'material_type_id' => $type->id,
                'unit_of_measure' => 'pcs',
            ])
            ->assertRedirect(route('admin.materials.index'));

        $this->assertDatabaseHas('materials', ['code' => 'TYPED-1', 'material_type_id' => $type->id]);
    }

    public function test_non_existent_material_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/materials', [
                'code' => 'BAD-TYPE-1',
                'name' => 'Bad type',
                'material_type_id' => 999999,
            ])
            ->assertSessionHasErrors('material_type_id');

        $this->assertDatabaseMissing('materials', ['code' => 'BAD-TYPE-1']);
    }

    public function test_material_type_can_be_cleared_on_update(): void
    {
        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'CLR-1', 'name' => 'Clearable', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/materials/{$material->id}", [
                'code' => 'CLR-1',
                'name' => 'Clearable',
                'material_type_id' => '', // ConvertEmptyStringsToNull → null
                'unit_of_measure' => 'pcs',
            ])
            ->assertRedirect(route('admin.materials.index'));

        $this->assertNull($material->fresh()->material_type_id);
    }
}
