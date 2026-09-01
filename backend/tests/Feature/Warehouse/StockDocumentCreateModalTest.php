<?php

namespace Tests\Feature\Warehouse;

use App\Models\Material;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating a stock document from the list's modal. The modal renders the same
 * form as /admin/stock-documents/create, so the list has to carry the same
 * options — and the post has to answer with back() rather than the redirect to
 * the new document, or the list would lose its filters and paging.
 */
class StockDocumentCreateModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function payload(Material $material, array $extra = []): array
    {
        return [
            'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
            'lines' => [[
                'material_id' => $material->id,
                'quantity' => 5,
                'unit_of_measure' => 'pcs',
            ]],
            ...$extra,
        ];
    }

    public function test_the_list_carries_the_options_its_create_modal_needs(): void
    {
        $this->actingAs($this->admin)->get('/admin/stock-documents')
            ->assertInertia(fn ($page) => $page
                ->has('warehouses')
                ->has('materials')
                ->has('productTypes')
                ->has('types')
                ->has('warehouseCodes')
                ->etc());
    }

    public function test_the_list_and_the_create_page_offer_the_same_options(): void
    {
        $this->actingAs($this->admin);

        $list = $this->get('/admin/stock-documents')->viewData('page')['props'];
        $create = $this->get('/admin/stock-documents/create')->viewData('page')['props'];

        foreach (['warehouses', 'materials', 'productTypes', 'types'] as $prop) {
            $this->assertEquals($create[$prop], $list[$prop], "list and create page disagree on {$prop}");
        }
    }

    public function test_the_list_offers_only_selectable_warehouses_but_names_every_referenced_one(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
        $retired = Warehouse::factory()->rawMaterial()->create(['code' => 'OLD-1', 'is_active' => false]);

        $props = $this->actingAs($this->admin)
            ->get('/admin/stock-documents')
            ->viewData('page')['props'];

        $this->assertNotContains('OLD-1', collect($props['warehouses'])->pluck('code')->all());
        // …but a row pointing at it still renders a code instead of a dash.
        $this->assertSame('OLD-1', $props['warehouseCodes'][$retired->id]);
    }

    public function test_creating_with_stay_returns_to_the_list_instead_of_the_new_document(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from('/admin/stock-documents')
            ->post('/admin/stock-documents', $this->payload($material, ['stay' => 1]));

        $response->assertRedirect('/admin/stock-documents');
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('stock_documents', 1);
    }

    public function test_creating_without_stay_still_redirects_to_the_new_document(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from('/admin/stock-documents')
            ->post('/admin/stock-documents', $this->payload($material));

        $document = StockDocument::sole();
        $response->assertRedirect("/admin/stock-documents/{$document->id}");
    }

    public function test_a_rejected_modal_post_comes_back_with_errors_and_no_document(): void
    {
        Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW-1']);

        $response = $this->actingAs($this->admin)
            ->from('/admin/stock-documents')
            ->post('/admin/stock-documents', [
                'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
                'lines' => [['quantity' => 5]],
                'stay' => 1,
            ]);

        $response->assertRedirect('/admin/stock-documents');
        $response->assertSessionHasErrors('lines.0.material_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_a_guest_cannot_create_a_document(): void
    {
        $material = Material::factory()->create();

        $this->post('/admin/stock-documents', $this->payload($material))
            ->assertRedirect('/login');

        $this->assertDatabaseCount('stock_documents', 0);
    }
}
