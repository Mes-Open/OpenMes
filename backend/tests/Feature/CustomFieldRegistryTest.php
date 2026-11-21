<?php

namespace Tests\Feature;

use App\Models\Concerns\HasCustomFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every entity in config/custom_fields.php is offered in the "Entity" dropdown
 * when an admin defines a custom field, so each one has to be a real model with
 * a table. An entry pointing at a class that does not exist lets the admin
 * define fields that can never be filled in or shown anywhere.
 */
class CustomFieldRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_entity_is_a_model_that_can_hold_custom_fields(): void
    {
        foreach (config('custom_fields.entities') as $alias => $entity) {
            $this->assertTrue(class_exists($entity['model']), "custom-field entity '{$alias}' points at a missing model {$entity['model']}");
            $this->assertContains(HasCustomFields::class, class_uses_recursive($entity['model']), "{$entity['model']} does not use HasCustomFields");
            $this->assertTrue(Schema::hasColumn($entity['table'], 'custom_fields'), "table {$entity['table']} has no custom_fields column");
        }
    }

    public function test_the_definition_form_does_not_offer_missing_entities(): void
    {
        $admin = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post('/admin/custom-fields', [
            'entity_type' => 'site', 'key' => 'region', 'label' => 'Region', 'type' => 'text',
        ])->assertSessionHasErrors('entity_type');
    }
}
