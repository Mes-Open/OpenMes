<?php

namespace Tests\Feature\Api;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected string $adminToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    public function test_can_list_templates_for_product_type(): void
    {
        $pt = ProductType::factory()->create();
        ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'version' => 1]);
        ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'version' => 2]);

        $response = $this->authOperator()->getJson("/api/v1/product-types/{$pt->id}/process-templates");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_template(): void
    {
        $pt = ProductType::factory()->create();

        $response = $this->authAdmin()->postJson("/api/v1/product-types/{$pt->id}/process-templates", [
            'name' => 'Initial process',
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Initial process')
            ->assertJsonPath('data.version', 1);
    }

    public function test_create_template_auto_bumps_version(): void
    {
        $pt = ProductType::factory()->create();
        ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'version' => 3]);

        $response = $this->authAdmin()->postJson("/api/v1/product-types/{$pt->id}/process-templates", [
            'name' => 'V4',
        ]);
        $response->assertStatus(201)->assertJsonPath('data.version', 4);
    }

    public function test_admin_can_add_step(): void
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id]);

        $response = $this->authAdmin()->postJson("/api/v1/process-templates/{$template->id}/steps", [
            'name' => 'Cut metal',
            'instruction' => 'Use the laser cutter',
            'estimated_duration_minutes' => 15,
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cut metal')
            ->assertJsonPath('data.step_number', 1);
    }

    public function test_step_numbers_auto_increment(): void
    {
        $template = ProcessTemplate::factory()->create();
        $this->authAdmin()->postJson("/api/v1/process-templates/{$template->id}/steps", ['name' => 'A']);
        $r2 = $this->authAdmin()->postJson("/api/v1/process-templates/{$template->id}/steps", ['name' => 'B']);
        $r2->assertJsonPath('data.step_number', 2);
    }

    public function test_admin_can_reorder_steps(): void
    {
        $template = ProcessTemplate::factory()->create();
        $s1 = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1, 'name' => 'A']);
        $s2 = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 2, 'name' => 'B']);
        $s3 = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 3, 'name' => 'C']);

        $this->authAdmin()->postJson("/api/v1/process-templates/{$template->id}/steps/reorder", [
            'step_ids' => [$s3->id, $s1->id, $s2->id],
        ])->assertStatus(200);

        $this->assertEquals(1, $s3->fresh()->step_number);
        $this->assertEquals(2, $s1->fresh()->step_number);
        $this->assertEquals(3, $s2->fresh()->step_number);
    }

    public function test_reorder_rejects_steps_from_other_template(): void
    {
        $t1 = ProcessTemplate::factory()->create();
        $t2 = ProcessTemplate::factory()->create();
        $s1 = TemplateStep::factory()->create(['process_template_id' => $t1->id, 'step_number' => 1]);
        $stranger = TemplateStep::factory()->create(['process_template_id' => $t2->id, 'step_number' => 1]);

        $this->authAdmin()->postJson("/api/v1/process-templates/{$t1->id}/steps/reorder", [
            'step_ids' => [$s1->id, $stranger->id],
        ])->assertStatus(422);
    }

    public function test_admin_can_update_step(): void
    {
        $template = ProcessTemplate::factory()->create();
        $step = TemplateStep::factory()->create(['process_template_id' => $template->id, 'name' => 'Old']);

        $this->authAdmin()->patchJson("/api/v1/template-steps/{$step->id}", [
            'name' => 'New',
        ])->assertStatus(200);
        $this->assertDatabaseHas('template_steps', ['id' => $step->id, 'name' => 'New']);
    }

    public function test_admin_can_delete_step(): void
    {
        $template = ProcessTemplate::factory()->create();
        $step = TemplateStep::factory()->create(['process_template_id' => $template->id]);

        $this->authAdmin()->deleteJson("/api/v1/template-steps/{$step->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('template_steps', ['id' => $step->id]);
    }

    public function test_operator_cannot_create_template(): void
    {
        $pt = ProductType::factory()->create();
        $this->authOperator()->postJson("/api/v1/product-types/{$pt->id}/process-templates", [
            'name' => 'X',
        ])->assertStatus(403);
    }

    public function test_admin_can_delete_template(): void
    {
        $template = ProcessTemplate::factory()->create();
        $this->authAdmin()->deleteJson("/api/v1/process-templates/{$template->id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('process_templates', ['id' => $template->id]);
    }

    public function test_admin_can_toggle_active(): void
    {
        $template = ProcessTemplate::factory()->create(['is_active' => true]);
        $this->authAdmin()->postJson("/api/v1/process-templates/{$template->id}/toggle-active")
            ->assertStatus(200);
        $this->assertFalse($template->fresh()->is_active);
    }
}
