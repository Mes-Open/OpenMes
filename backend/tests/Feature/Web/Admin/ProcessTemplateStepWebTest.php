<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the literal step-management URLs that the process-template Show page
 * posts to (/steps, /steps/{step}, /steps/{step}/move-*). The React page
 * hardcodes these paths, so a route-path rename would 404 the Save/Add/Delete/
 * Move actions — regression guard for the production "Save Changes → 404 popup".
 */
class ProcessTemplateStepWebTest extends TestCase
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

    private function template(): array
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);

        return [$productType, $template];
    }

    private function base(ProductType $pt, ProcessTemplate $tpl): string
    {
        return "/admin/product-types/{$pt->id}/process-templates/{$tpl->id}";
    }

    public function test_admin_can_add_step(): void
    {
        [$pt, $tpl] = $this->template();

        $response = $this->actingAs($this->admin)
            ->post($this->base($pt, $tpl).'/steps', ['name' => 'Embroidery file check']);

        $response->assertRedirect();
        $this->assertDatabaseHas('template_steps', [
            'process_template_id' => $tpl->id,
            'name' => 'Embroidery file check',
        ]);
    }

    public function test_admin_can_set_required_operators_on_a_step(): void
    {
        [$pt, $tpl] = $this->template();

        $this->actingAs($this->admin)
            ->post($this->base($pt, $tpl).'/steps', [
                'name' => 'Two-person press',
                'required_operators' => 2,
            ])->assertRedirect();

        $this->assertDatabaseHas('template_steps', [
            'process_template_id' => $tpl->id,
            'name' => 'Two-person press',
            'required_operators' => 2,
        ]);
    }

    public function test_admin_can_set_isa95_workstation_type_and_standard_times(): void
    {
        [$pt, $tpl] = $this->template();
        $type = \App\Models\WorkstationType::factory()->create();

        $this->actingAs($this->admin)
            ->post($this->base($pt, $tpl).'/steps', [
                'name' => 'CNC mill',
                'workstation_type_id' => $type->id,
                'setup_time_minutes' => 12,
                'run_time_per_unit_minutes' => 1.5,
            ])->assertRedirect();

        $this->assertDatabaseHas('template_steps', [
            'process_template_id' => $tpl->id,
            'name' => 'CNC mill',
            'workstation_type_id' => $type->id,
            'setup_time_minutes' => 12,
            'run_time_per_unit_minutes' => 1.5,
        ]);
    }

    public function test_admin_can_set_equipment_parameters_on_a_step(): void
    {
        [$pt, $tpl] = $this->template();

        $this->actingAs($this->admin)
            ->post($this->base($pt, $tpl).'/steps', [
                'name' => 'Reflow oven',
                'parameters' => ['temperature_c' => '250', 'humidity_pct' => '40'],
            ])->assertRedirect();

        $step = \App\Models\TemplateStep::where('process_template_id', $tpl->id)
            ->where('name', 'Reflow oven')->firstOrFail();
        $this->assertSame(['temperature_c' => '250', 'humidity_pct' => '40'], $step->parameters);
    }

    public function test_admin_can_update_step(): void
    {
        [$pt, $tpl] = $this->template();
        $step = TemplateStep::factory()->create(['process_template_id' => $tpl->id, 'step_number' => 1]);

        $response = $this->actingAs($this->admin)
            ->put($this->base($pt, $tpl)."/steps/{$step->id}", [
                'name' => 'Renamed step',
                'estimated_duration_minutes' => 10,
            ]);

        $response->assertRedirect();
        $response->assertStatus(302); // not the 404 the frontend used to hit
        $this->assertDatabaseHas('template_steps', [
            'id' => $step->id,
            'name' => 'Renamed step',
        ]);
    }

    public function test_admin_can_delete_step(): void
    {
        [$pt, $tpl] = $this->template();
        $step = TemplateStep::factory()->create(['process_template_id' => $tpl->id, 'step_number' => 1]);

        $response = $this->actingAs($this->admin)
            ->delete($this->base($pt, $tpl)."/steps/{$step->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('template_steps', ['id' => $step->id]);
    }

    public function test_admin_can_move_step_up(): void
    {
        [$pt, $tpl] = $this->template();
        TemplateStep::factory()->create(['process_template_id' => $tpl->id, 'step_number' => 1]);
        $second = TemplateStep::factory()->create(['process_template_id' => $tpl->id, 'step_number' => 2]);

        $response = $this->actingAs($this->admin)
            ->post($this->base($pt, $tpl)."/steps/{$second->id}/move-up");

        $response->assertRedirect();
        $this->assertDatabaseHas('template_steps', ['id' => $second->id, 'step_number' => 1]);
    }
}
