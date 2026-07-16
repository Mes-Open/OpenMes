<?php

namespace Tests\Feature\Api\V1;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProcessTemplate;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
    }

    public function test_index_includes_bom_items_count_per_material(): void
    {
        $material = Material::factory()->create(['tenant_id' => $this->user->tenant_id]);
        // Unique (process_template_id, material_id) — one line per distinct template.
        BomItem::factory()->create([
            'material_id' => $material->id,
            'process_template_id' => ProcessTemplate::factory()->create()->id,
        ]);
        BomItem::factory()->create([
            'material_id' => $material->id,
            'process_template_id' => ProcessTemplate::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/materials');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $material->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['bom_items_count']);
    }

    public function test_show_includes_reserved_and_available_quantities(): void
    {
        $material = Material::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'stock_quantity' => 100,
            'reserved_quantity' => 30,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk();
        $this->assertEqualsWithDelta(30.0, (float) $response->json('data.reserved_quantity'), 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $response->json('data.available_quantity'), 0.0001);
    }

    public function test_show_returns_lots_movements_and_bom_usage_payload(): void
    {
        $material = Material::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'stock_quantity' => 100,
        ]);

        MaterialLot::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'material_id' => $material->id,
            'quantity_received' => 50,
            'quantity_available' => 40,
        ]);

        StockMovement::create([
            'material_id' => $material->id,
            'tenant_id' => $this->user->tenant_id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'quantity' => 50,
            'balance_after' => 100,
            'performed_at' => now(),
            'performed_by' => $this->user->id,
        ]);

        $template = ProcessTemplate::factory()->create();
        BomItem::factory()->create([
            'material_id' => $material->id,
            'process_template_id' => $template->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'lots' => [['id', 'lot_number', 'quantity_received', 'quantity_available', 'status', 'is_expired']],
                    'recent_movements' => [['id', 'movement_type', 'quantity', 'balance_after', 'performed_at']],
                    'bom_usage' => [['id', 'quantity_per_unit', 'scrap_percentage', 'process_template']],
                ],
            ]);

        $this->assertCount(1, $response->json('data.lots'));
        $this->assertCount(1, $response->json('data.recent_movements'));
        $this->assertCount(1, $response->json('data.bom_usage'));
    }
}
