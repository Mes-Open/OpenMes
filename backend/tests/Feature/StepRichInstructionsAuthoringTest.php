<?php

namespace Tests\Feature;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin authoring of rich step instructions: attaching media and checklist
 * items to a template step.
 */
class StepRichInstructionsAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private ProductType $productType;

    private ProcessTemplate $template;

    private TemplateStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id]);
        $this->step = TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => 1]);
    }

    private function base(): string
    {
        return "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}";
    }

    public function test_admin_attaches_a_checklist_item(): void
    {
        $this->actingAs($this->admin)
            ->post($this->base().'/checklist-items', [
                'template_step_id' => $this->step->id,
                'label' => 'Verify torque',
                'is_required' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('template_step_checklist_items', [
            'template_step_id' => $this->step->id,
            'label' => 'Verify torque',
            'is_required' => true,
        ]);
    }

    public function test_admin_uploads_a_pdf_media(): void
    {
        $this->actingAs($this->admin)
            ->post($this->base().'/media', [
                'media_type' => 'pdf',
                'template_step_id' => $this->step->id,
                'title' => 'Assembly drawing',
                'file' => UploadedFile::fake()->create('drawing.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('template_step_media', [
            'template_step_id' => $this->step->id,
            'media_type' => 'pdf',
            'title' => 'Assembly drawing',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_media_upload_rejects_a_type_mismatch(): void
    {
        $this->actingAs($this->admin)
            ->post($this->base().'/media', [
                'media_type' => 'pdf',
                'template_step_id' => $this->step->id,
                'file' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('template_step_media', 0);
    }

    public function test_operator_cannot_author_instructions(): void
    {
        $this->actingAs($this->operator)
            ->post($this->base().'/checklist-items', [
                'template_step_id' => $this->step->id,
                'label' => 'x',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('template_step_checklist_items', 0);
    }
}
