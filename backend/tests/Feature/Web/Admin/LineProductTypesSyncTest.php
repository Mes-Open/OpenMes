<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the literal URL the line-detail page posts to when assigning product types
 * to a line: `/admin/lines/{line}/product-types/sync`
 * (resources/js/Pages/admin/lines/Show.jsx). The route path had dropped the
 * `/sync` suffix the frontend sends, so every save 404'd — regression guard.
 */
class LineProductTypesSyncTest extends TestCase
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

    public function test_admin_syncs_product_types_to_a_line_via_the_frontend_url(): void
    {
        $line = Line::factory()->create();
        $a = ProductType::factory()->create();
        $b = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post("/admin/lines/{$line->id}/product-types/sync", [
                'product_type_ids' => [$a->id, $b->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $line->productTypes()->pluck('product_types.id')->all(),
        );
    }

    public function test_sync_replaces_the_previous_assignment(): void
    {
        $line = Line::factory()->create();
        $old = ProductType::factory()->create();
        $new = ProductType::factory()->create();
        $line->productTypes()->sync([$old->id]);

        $this->actingAs($this->admin)
            ->post("/admin/lines/{$line->id}/product-types/sync", ['product_type_ids' => [$new->id]])
            ->assertRedirect();

        $this->assertSame([$new->id], $line->productTypes()->pluck('product_types.id')->all());
    }

    public function test_empty_payload_clears_all_assignments(): void
    {
        $line = Line::factory()->create();
        $pt = ProductType::factory()->create();
        $line->productTypes()->sync([$pt->id]);

        $this->actingAs($this->admin)
            ->post("/admin/lines/{$line->id}/product-types/sync", [])
            ->assertRedirect();

        $this->assertCount(0, $line->productTypes()->get());
    }

    public function test_unknown_product_type_id_is_rejected(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/lines/{$line->id}/product-types/sync", ['product_type_ids' => [999999]])
            ->assertSessionHasErrors('product_type_ids.0');
    }

    public function test_guest_cannot_sync(): void
    {
        $line = Line::factory()->create();

        $this->post("/admin/lines/{$line->id}/product-types/sync", ['product_type_ids' => []])
            ->assertRedirect('/login');
    }
}
