<?php

namespace Tests\Feature;

use App\Http\Requests\StoreProcessTemplatePhotoRequest;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplatePhoto;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcessTemplatePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private ProductType $productType;

    private ProcessTemplate $template;

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
        $this->template = ProcessTemplate::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);
    }

    private function uploadUrl(?ProcessTemplate $template = null, ?ProductType $productType = null): string
    {
        $template ??= $this->template;
        $productType ??= $this->productType;

        return "/admin/product-types/{$productType->id}/process-templates/{$template->id}/photos";
    }

    // ── Happy path ───────────────────────────────────────────────────────

    public function test_admin_can_upload_photo(): void
    {
        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('instruction.jpg', 800, 600),
            'caption' => 'Step 3 assembly detail',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $photo = ProcessTemplatePhoto::first();
        $this->assertNotNull($photo);
        $this->assertSame($this->template->id, $photo->process_template_id);
        $this->assertSame('instruction.jpg', $photo->original_name);
        $this->assertSame('Step 3 assembly detail', $photo->caption);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(800, $photo->width);
        $this->assertSame($this->admin->id, $photo->uploaded_by_id);
        Storage::assertExists($photo->storage_path);

        // Server-generated random filename — never the client's name
        $this->assertStringNotContainsString('instruction', $photo->storage_path);
    }

    public function test_authenticated_user_can_view_photo_with_safe_headers(): void
    {
        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.png', 100, 100),
        ]);
        $photo = ProcessTemplatePhoto::first();

        $response = $this->actingAs($this->operator)
            ->get("/process-templates/{$this->template->id}/photos/{$photo->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_delete_photo_and_file_is_removed(): void
    {
        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $photo = ProcessTemplatePhoto::first();
        $path = $photo->storage_path;

        $response = $this->actingAs($this->admin)
            ->delete($this->uploadUrl()."/{$photo->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('process_template_photos', ['id' => $photo->id]);
        Storage::assertMissing($path);
    }

    // ── Malicious upload rejection ───────────────────────────────────────

    public function test_php_payload_appended_to_image_is_destroyed_on_disk(): void
    {
        // Polyglot: valid PNG + appended PHP webshell
        $file = UploadedFile::fake()->image('innocent.png', 50, 50);
        file_put_contents($file->getRealPath(), '<?php system($_GET["c"]); ?>', FILE_APPEND);

        $this->actingAs($this->admin)
            ->post($this->uploadUrl(), ['photo' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $stored = Storage::get(ProcessTemplatePhoto::first()->storage_path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('system(', $stored);
    }

    public function test_rejects_php_file_disguised_as_jpg(): void
    {
        $file = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "owned"; ?>');

        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), ['photo' => $file]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('process_template_photos', 0);
    }

    public function test_rejects_svg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('xss.svg', $svg);

        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), ['photo' => $file]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('process_template_photos', 0);
    }

    public function test_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg'); // 11 MB

        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), ['photo' => $file]);

        $response->assertSessionHasErrors('photo');
    }

    public function test_enforces_photo_limit_per_template(): void
    {
        ProcessTemplatePhoto::factory()
            ->count(StoreProcessTemplatePhotoRequest::MAX_PHOTOS_PER_TEMPLATE)
            ->create(['process_template_id' => $this->template->id]);

        $response = $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('one-too-many.jpg'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount(
            'process_template_photos',
            StoreProcessTemplatePhotoRequest::MAX_PHOTOS_PER_TEMPLATE,
        );
    }

    // ── Authorization ────────────────────────────────────────────────────

    public function test_guest_cannot_upload_or_view(): void
    {
        $this->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertRedirect('/login');

        $photo = ProcessTemplatePhoto::factory()->create([
            'process_template_id' => $this->template->id,
        ]);

        $this->get("/process-templates/{$this->template->id}/photos/{$photo->id}")
            ->assertRedirect('/login');
    }

    public function test_operator_cannot_upload_or_delete(): void
    {
        $this->actingAs($this->operator)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertForbidden();

        $photo = ProcessTemplatePhoto::factory()->create([
            'process_template_id' => $this->template->id,
        ]);

        $this->actingAs($this->operator)
            ->delete($this->uploadUrl()."/{$photo->id}")
            ->assertForbidden();
    }

    // ── IDOR / scope ─────────────────────────────────────────────────────

    public function test_photo_cannot_be_accessed_through_foreign_template(): void
    {
        $this->actingAs($this->admin)->post($this->uploadUrl(), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $photo = ProcessTemplatePhoto::first();

        $otherTemplate = ProcessTemplate::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
        ]);

        // View through a template the photo does NOT belong to → 404
        $this->actingAs($this->operator)
            ->get("/process-templates/{$otherTemplate->id}/photos/{$photo->id}")
            ->assertNotFound();

        // Delete through a foreign template/product-type pair → 404
        $this->actingAs($this->admin)
            ->delete($this->uploadUrl($otherTemplate)."/{$photo->id}")
            ->assertNotFound();
    }

    public function test_upload_rejected_for_mismatched_product_type(): void
    {
        $otherProductType = ProductType::factory()->create();

        $this->actingAs($this->admin)
            ->post($this->uploadUrl($this->template, $otherProductType), [
                'photo' => UploadedFile::fake()->image('a.jpg'),
            ])
            ->assertNotFound();
    }
}
