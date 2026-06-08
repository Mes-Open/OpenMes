<?php

namespace Tests\Feature;

use App\Models\ProcessTemplate;
use App\Models\ProcessTemplatePhoto;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerStepProcessPhotoTest extends TestCase
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

        $this->operator = User::factory()->create(['account_type' => 'operator']);
        $this->operator->assignRole('Operator');

        $this->productType = ProductType::factory()->create();
        $this->template = ProcessTemplate::factory()->create(['product_type_id' => $this->productType->id]);
        $this->step = TemplateStep::factory()->create(['process_template_id' => $this->template->id, 'step_number' => 1]);
    }

    private function uploadUrl(): string
    {
        return "/admin/product-types/{$this->productType->id}/process-templates/{$this->template->id}/photos";
    }

    public function test_admin_can_attach_a_photo_to_a_step(): void
    {
        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('step.jpg', 100, 100),
            'template_step_id' => $this->step->id,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $photo = ProcessTemplatePhoto::first();
        $this->assertSame($this->step->id, $photo->template_step_id);
        $this->assertSame($this->template->id, $photo->process_template_id);
        Storage::assertExists($photo->storage_path);
    }

    public function test_uploading_a_second_step_photo_replaces_the_first(): void
    {
        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'template_step_id' => $this->step->id,
        ]);
        $first = ProcessTemplatePhoto::first();

        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('b.jpg'),
            'template_step_id' => $this->step->id,
        ]);

        // Exactly one photo for the step; the old row and its file are gone.
        $this->assertSame(1, ProcessTemplatePhoto::where('template_step_id', $this->step->id)->count());
        $this->assertDatabaseMissing('process_template_photos', ['id' => $first->id]);
        Storage::assertMissing($first->storage_path);
    }

    public function test_step_must_belong_to_the_template(): void
    {
        $otherStep = TemplateStep::factory()->create([
            'process_template_id' => ProcessTemplate::factory()->create()->id,
        ]);

        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('x.jpg'),
            'template_step_id' => $otherStep->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('process_template_photos', 0);
    }

    public function test_step_photos_do_not_count_against_the_general_template_cap(): void
    {
        // Fill the general cap with non-step photos.
        ProcessTemplatePhoto::factory()
            ->count(20)
            ->create(['process_template_id' => $this->template->id, 'template_step_id' => null]);

        // A step photo still goes through despite the general cap being full.
        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('step.jpg'),
            'template_step_id' => $this->step->id,
        ])->assertSessionHas('success');

        $this->assertSame(1, ProcessTemplatePhoto::whereNotNull('template_step_id')->count());
    }

    public function test_operator_sees_step_photo_keyed_by_step_number(): void
    {
        // Photo on template step #1.
        ProcessTemplatePhoto::factory()->create([
            'process_template_id' => $this->template->id,
            'template_step_id' => $this->step->id,
        ]);
        // A general photo (no step) should land in processPhotos, not stepPhotos.
        ProcessTemplatePhoto::factory()->create([
            'process_template_id' => $this->template->id,
            'template_step_id' => null,
        ]);

        // Work order whose snapshot points at this template.
        $wo = WorkOrder::factory()->create([
            'line_id' => \App\Models\Line::factory()->create()->id,
            'product_type_id' => $this->productType->id,
            'process_snapshot' => [
                'template_id' => $this->template->id,
                'template_name' => $this->template->name,
                'template_version' => 1,
                'steps' => [],
                'bom' => [],
            ],
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $wo->line_id])
            ->get("/operator/work-order/{$wo->id}");

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];

        $this->assertArrayHasKey(1, $props['stepPhotos']); // keyed by step_number
        $this->assertCount(1, $props['processPhotos']);    // the general one only
    }
}
