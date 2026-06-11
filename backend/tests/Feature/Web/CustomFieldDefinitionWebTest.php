<?php

namespace Tests\Feature\Web;

use App\Models\CustomFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldDefinitionWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->admin)->get('/admin/custom-fields')->assertOk();
    }

    public function test_operator_cannot_access(): void
    {
        $this->actingAs($this->operator)->get('/admin/custom-fields')->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/custom-fields')->assertRedirect('/login');
    }

    public function test_admin_can_create_a_text_field(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/custom-fields', [
            'entity_type' => 'material',
            'key' => 'supplier_ref',
            'label' => 'Supplier reference',
            'type' => 'text',
            'required' => true,
            'position' => 5,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.custom-fields.index'));
        $this->assertDatabaseHas('custom_field_definitions', [
            'entity_type' => 'material', 'key' => 'supplier_ref', 'type' => 'text', 'required' => true,
        ]);
    }

    public function test_select_requires_options(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/custom-fields/create')
            ->post('/admin/custom-fields', [
                'entity_type' => 'material', 'key' => 'color', 'label' => 'Colour', 'type' => 'select',
            ])
            ->assertSessionHasErrors('config.options');

        $this->assertDatabaseMissing('custom_field_definitions', ['key' => 'color']);
    }

    public function test_select_options_are_persisted(): void
    {
        $this->actingAs($this->admin)->post('/admin/custom-fields', [
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Colour', 'type' => 'select',
            'config' => ['options' => [
                ['value' => 'red', 'label' => 'Red'],
                ['value' => 'blue', 'label' => 'Blue'],
            ]],
        ])->assertSessionHasNoErrors();

        $def = CustomFieldDefinition::where('key', 'color')->firstOrFail();
        $this->assertCount(2, $def->config['options']);
        $this->assertSame('red', $def->config['options'][0]['value']);
    }

    public function test_invalid_key_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/custom-fields/create')
            ->post('/admin/custom-fields', [
                'entity_type' => 'material', 'key' => 'Bad Key!', 'label' => 'X', 'type' => 'text',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_unknown_entity_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/custom-fields/create')
            ->post('/admin/custom-fields', [
                'entity_type' => 'not_an_entity', 'key' => 'x', 'label' => 'X', 'type' => 'text',
            ])
            ->assertSessionHasErrors('entity_type');
    }

    public function test_duplicate_key_per_entity_is_rejected(): void
    {
        CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Colour', 'type' => 'text', 'position' => 0,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/custom-fields/create')
            ->post('/admin/custom-fields', [
                'entity_type' => 'material', 'key' => 'color', 'label' => 'Other', 'type' => 'text',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_admin_can_update(): void
    {
        $def = CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Colour', 'type' => 'text', 'position' => 0,
        ]);

        $this->actingAs($this->admin)->put("/admin/custom-fields/{$def->id}", [
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Renamed', 'type' => 'text',
            'required' => true, 'is_active' => true,
        ])->assertRedirect(route('admin.custom-fields.index'));

        $this->assertSame('Renamed', $def->fresh()->label);
        $this->assertTrue($def->fresh()->required);
    }

    public function test_admin_can_toggle_and_delete(): void
    {
        $def = CustomFieldDefinition::create([
            'entity_type' => 'material', 'key' => 'color', 'label' => 'Colour', 'type' => 'text', 'position' => 0,
        ]);

        $this->actingAs($this->admin)->post("/admin/custom-fields/{$def->id}/toggle-active");
        $this->assertFalse($def->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/custom-fields/{$def->id}");
        $this->assertDatabaseMissing('custom_field_definitions', ['id' => $def->id]);
    }
}
