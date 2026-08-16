<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Optional product photo on a product type: upload on create/edit, replace,
 * remove, and the authenticated stream endpoint.
 */
class ProductTypeImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    /**
     * Give a product type a stored photo without going through an
     * authenticated upload — so the guest/authorization cases below start from
     * a genuinely unauthenticated session.
     */
    private function withImage(ProductType $productType): ProductType
    {
        $path = 'product-type-images/'.$productType->id.'/'.Str::random(40).'.jpg';
        Storage::put($path, 'pixels');

        $productType->forceFill([
            'image_path' => $path,
            'image_mime' => 'image/jpeg',
        ])->save();

        return $productType;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'WIDGET-A',
            'name' => 'Widget Type A',
            'unit_of_measure' => 'pcs',
            'is_active' => true,
        ], $overrides);
    }

    /** A full edit submission, as the form sends it. */
    private function edit(ProductType $productType, array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)
            ->put("/admin/product-types/{$productType->id}", $this->payload($overrides));
    }

    private function productType(): ProductType
    {
        return ProductType::factory()->create(['code' => 'WIDGET-A']);
    }

    // ── Happy path ───────────────────────────────────────────────────────

    public function test_admin_can_create_a_product_type_with_an_image(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/product-types', $this->payload([
            'image' => UploadedFile::fake()->image('widget.jpg', 800, 600),
        ]));

        $response->assertRedirect('/admin/product-types')->assertSessionHas('success');

        $productType = ProductType::firstWhere('code', 'WIDGET-A');
        $this->assertNotNull($productType->image_path);
        $this->assertSame('image/jpeg', $productType->image_mime);
        Storage::assertExists($productType->image_path);

        // Server-generated random filename — never the client's name
        $this->assertStringNotContainsString('widget.jpg', $productType->image_path);
    }

    public function test_the_image_is_optional_on_create(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/product-types', $this->payload());

        $response->assertRedirect('/admin/product-types')->assertSessionHasNoErrors();

        $productType = ProductType::firstWhere('code', 'WIDGET-A');
        $this->assertNull($productType->image_path);
        $this->assertNull($productType->image_mime);
    }

    public function test_admin_can_add_an_image_when_editing(): void
    {
        $productType = $this->productType();

        $this->edit($productType, ['image' => UploadedFile::fake()->image('widget.png', 400, 400)])
            ->assertRedirect('/admin/product-types');

        $productType->refresh();
        $this->assertNotNull($productType->image_path);
        $this->assertSame('image/png', $productType->image_mime);
        Storage::assertExists($productType->image_path);
    }

    public function test_uploading_a_new_image_replaces_and_deletes_the_old_file(): void
    {
        $productType = $this->productType();

        $this->edit($productType, ['image' => UploadedFile::fake()->image('first.jpg', 300, 300)]);
        $first = $productType->refresh()->image_path;

        $this->edit($productType, ['image' => UploadedFile::fake()->image('second.png', 300, 300)]);
        $second = $productType->refresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
        $this->assertSame('image/png', $productType->image_mime);
    }

    public function test_remove_image_clears_the_photo_and_deletes_the_file(): void
    {
        $productType = $this->withImage($this->productType());
        $path = $productType->image_path;

        $this->edit($productType, ['remove_image' => true])->assertRedirect('/admin/product-types');

        $productType->refresh();
        $this->assertNull($productType->image_path);
        $this->assertNull($productType->image_mime);
        Storage::assertMissing($path);
    }

    public function test_editing_without_touching_the_image_keeps_it(): void
    {
        $productType = $this->withImage($this->productType());
        $path = $productType->image_path;

        $this->edit($productType, ['name' => 'Renamed Widget'])->assertRedirect('/admin/product-types');

        $productType->refresh();
        $this->assertSame('Renamed Widget', $productType->name);
        $this->assertSame($path, $productType->image_path);
        Storage::assertExists($path);
    }

    public function test_a_new_upload_wins_over_the_remove_flag(): void
    {
        $productType = $this->withImage($this->productType());

        $this->edit($productType, [
            'image' => UploadedFile::fake()->image('replacement.jpg', 300, 300),
            'remove_image' => true,
        ])->assertRedirect('/admin/product-types');

        $productType->refresh();
        $this->assertNotNull($productType->image_path);
        Storage::assertExists($productType->image_path);
    }

    public function test_a_soft_deleted_product_type_keeps_its_image_but_a_purge_drops_the_file(): void
    {
        $productType = $this->withImage($this->productType());
        $path = $productType->image_path;

        $productType->delete();
        Storage::assertExists($path); // restorable from Trash — file must survive

        $productType->forceDelete();
        Storage::assertMissing($path);
    }

    // ── Validation ───────────────────────────────────────────────────────

    public function test_a_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/product-types', $this->payload([
                'image' => UploadedFile::fake()->create('payload.pdf', 20, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('product_types', ['code' => 'WIDGET-A']);
    }

    public function test_an_svg_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/product-types', $this->payload([
                'image' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ]))
            ->assertSessionHasErrors('image');
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/product-types', $this->payload([
                'image' => UploadedFile::fake()->create('huge.jpg', 6 * 1024, 'image/jpeg'),
            ]))
            ->assertSessionHasErrors('image');
    }

    // ── Streaming endpoint ───────────────────────────────────────────────

    public function test_admin_can_stream_the_image(): void
    {
        $productType = $this->withImage($this->productType());

        $this->actingAs($this->admin)
            ->get("/admin/product-types/{$productType->id}/image")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_streaming_a_product_type_without_an_image_is_404(): void
    {
        $productType = $this->productType();

        $this->actingAs($this->admin)
            ->get("/admin/product-types/{$productType->id}/image")
            ->assertNotFound();
    }

    public function test_guests_cannot_stream_the_image(): void
    {
        $productType = $this->withImage($this->productType());

        $this->get("/admin/product-types/{$productType->id}/image")->assertRedirect('/login');
    }

    public function test_non_admins_cannot_stream_the_image(): void
    {
        $productType = $this->withImage($this->productType());

        $this->actingAs($this->operator)
            ->get("/admin/product-types/{$productType->id}/image")
            ->assertForbidden();
    }

    public function test_non_admins_cannot_upload_an_image(): void
    {
        $productType = $this->productType();

        $this->edit($productType, ['image' => UploadedFile::fake()->image('widget.jpg', 300, 300)], $this->operator)
            ->assertForbidden();

        $this->assertNull($productType->refresh()->image_path);
    }

    // ── Inertia props ────────────────────────────────────────────────────

    public function test_the_edit_and_show_pages_expose_the_image_url(): void
    {
        $productType = $this->withImage($this->productType());

        $this->actingAs($this->admin)
            ->get("/admin/product-types/{$productType->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('productType.image_url', fn ($url) => str_contains((string) $url, "/admin/product-types/{$productType->id}/image"))
            );

        $this->actingAs($this->admin)
            ->get("/admin/product-types/{$productType->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('productType.image_url', fn ($url) => str_contains((string) $url, "/admin/product-types/{$productType->id}/image"))
            );
    }
}
