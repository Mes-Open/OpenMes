<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin control to switch a Process Template between Batch and Unit
 * execution mode (#290) — Create/Edit forms and their backing validation.
 */
class ProcessTemplateExecutionModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_creating_a_template_without_execution_mode_defaults_to_batch(): void
    {
        $productType = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            route('admin.product-types.process-templates.store', $productType),
            ['name' => 'Default Route', 'is_active' => 1]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('process_templates', [
            'product_type_id' => $productType->id,
            'name' => 'Default Route',
            'execution_mode' => ProcessTemplate::EXECUTION_MODE_BATCH,
        ]);
    }

    public function test_admin_can_create_a_unit_mode_template(): void
    {
        $productType = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            route('admin.product-types.process-templates.store', $productType),
            ['name' => 'Serialized Route', 'is_active' => 1, 'execution_mode' => 'unit']
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('process_templates', [
            'name' => 'Serialized Route',
            'execution_mode' => 'unit',
        ]);
    }

    public function test_invalid_execution_mode_is_rejected(): void
    {
        $productType = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            route('admin.product-types.process-templates.store', $productType),
            ['name' => 'Bad Mode', 'is_active' => 1, 'execution_mode' => 'parallel-universe']
        );

        $response->assertSessionHasErrors('execution_mode');
        $this->assertDatabaseMissing('process_templates', ['name' => 'Bad Mode']);
    }

    public function test_admin_can_switch_an_existing_template_to_unit_mode(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create([
            'product_type_id' => $productType->id,
            'execution_mode' => ProcessTemplate::EXECUTION_MODE_BATCH,
        ]);

        $response = $this->actingAs($this->admin)->put(
            route('admin.product-types.process-templates.update', [$productType, $template]),
            ['name' => $template->name, 'is_active' => 1, 'execution_mode' => 'unit']
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame('unit', $template->fresh()->execution_mode);
    }

    public function test_edit_form_exposes_the_current_execution_mode(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create([
            'product_type_id' => $productType->id,
            'execution_mode' => 'unit',
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.product-types.process-templates.edit', [$productType, $template])
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/process-templates/Edit')
            ->where('processTemplate.execution_mode', 'unit'));
    }
}
