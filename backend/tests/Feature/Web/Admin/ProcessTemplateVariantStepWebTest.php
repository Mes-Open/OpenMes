<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin authoring of optional / variant steps: the is_optional, variant_group
 * and is_default_variant flags persist and are validated (a default must belong
 * to a group, and a group has at most one default).
 */
class ProcessTemplateVariantStepWebTest extends TestCase
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
        $pt = ProductType::factory()->create();
        $tpl = ProcessTemplate::factory()->create(['product_type_id' => $pt->id]);

        return [$pt, $tpl, "/admin/product-types/{$pt->id}/process-templates/{$tpl->id}"];
    }

    public function test_admin_can_add_optional_step(): void
    {
        [, $tpl, $base] = $this->template();

        $this->actingAs($this->admin)
            ->post($base.'/steps', ['name' => 'Polish', 'is_optional' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('template_steps', [
            'process_template_id' => $tpl->id,
            'name' => 'Polish',
            'is_optional' => true,
            'variant_group' => null,
        ]);
    }

    public function test_admin_can_add_default_variant_step(): void
    {
        [, $tpl, $base] = $this->template();

        $this->actingAs($this->admin)
            ->post($base.'/steps', [
                'name' => 'Gloss finish',
                'variant_group' => 'finish',
                'is_default_variant' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('template_steps', [
            'process_template_id' => $tpl->id,
            'name' => 'Gloss finish',
            'variant_group' => 'finish',
            'is_default_variant' => true,
        ]);
    }

    public function test_default_variant_requires_a_group(): void
    {
        [, , $base] = $this->template();

        $this->actingAs($this->admin)
            ->post($base.'/steps', ['name' => 'Loose default', 'is_default_variant' => true])
            ->assertSessionHasErrors('is_default_variant');
    }

    public function test_group_can_have_only_one_default(): void
    {
        [, $tpl, $base] = $this->template();
        TemplateStep::factory()->create([
            'process_template_id' => $tpl->id,
            'step_number' => 1,
            'variant_group' => 'finish',
            'is_default_variant' => true,
        ]);

        $this->actingAs($this->admin)
            ->post($base.'/steps', [
                'name' => 'Matte finish',
                'variant_group' => 'finish',
                'is_default_variant' => true,
            ])
            ->assertSessionHasErrors('is_default_variant');
    }

    public function test_updating_the_same_default_step_does_not_clash_with_itself(): void
    {
        [, $tpl, $base] = $this->template();
        $step = TemplateStep::factory()->create([
            'process_template_id' => $tpl->id,
            'step_number' => 1,
            'variant_group' => 'finish',
            'is_default_variant' => true,
        ]);

        $this->actingAs($this->admin)
            ->put($base."/steps/{$step->id}", [
                'name' => 'Gloss (renamed)',
                'variant_group' => 'finish',
                'is_default_variant' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
