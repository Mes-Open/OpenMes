<?php

namespace Tests\Feature;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Services\Material\BomService;
use App\Services\Material\MaterialSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private MaterialType $rawMaterial;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->rawMaterial = MaterialType::create(['code' => 'raw_material', 'name' => 'Raw Material']);
        MaterialType::create(['code' => 'packaging', 'name' => 'Packaging Material']);
    }

    // ── Material CRUD ───────────────────────────────────────────

    public function test_admin_can_list_materials(): void
    {
        Material::factory()->count(3)->create(['material_type_id' => $this->rawMaterial->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.materials.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_material(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.materials.store'), [
            'code' => 'PP-GRANULAT',
            'name' => 'Polypropylene Granulate',
            'material_type_id' => $this->rawMaterial->id,
            'unit_of_measure' => 'kg',
            'tracking_type' => 'batch',
            'default_scrap_percentage' => 2.5,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.materials.index'));
        $this->assertDatabaseHas('materials', [
            'code' => 'PP-GRANULAT',
            'name' => 'Polypropylene Granulate',
            'tracking_type' => 'batch',
            'default_scrap_percentage' => 2.5,
        ]);
    }

    public function test_material_code_must_be_unique(): void
    {
        Material::factory()->create(['code' => 'DUP-001', 'material_type_id' => $this->rawMaterial->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.materials.store'), [
            'code' => 'DUP-001',
            'name' => 'Duplicate',
            'material_type_id' => $this->rawMaterial->id,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_material(): void
    {
        $material = Material::factory()->create(['material_type_id' => $this->rawMaterial->id]);

        $response = $this->actingAs($this->admin)->put(route('admin.materials.update', $material), [
            'code' => $material->code,
            'name' => 'Updated Name',
            'material_type_id' => $this->rawMaterial->id,
        ]);

        $response->assertRedirect(route('admin.materials.index'));
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => 'Updated Name']);
    }

    public function test_cannot_delete_material_used_in_bom(): void
    {
        $material = Material::factory()->create(['material_type_id' => $this->rawMaterial->id]);
        BomItem::factory()->create(['material_id' => $material->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.materials.destroy', $material));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('materials', ['id' => $material->id]);
    }

    // ── BOM CRUD ────────────────────────────────────────────────

    public function test_admin_can_view_bom(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.product-types.process-templates.bom', [$productType, $template])
        );

        $response->assertStatus(200);
    }

    public function test_admin_can_add_bom_item(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $material = Material::factory()->create(['material_type_id' => $this->rawMaterial->id]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.product-types.process-templates.bom.store', [$productType, $template]),
            [
                'material_id' => $material->id,
                'quantity_per_unit' => 0.025,
                'scrap_percentage' => 2.0,
                'consumed_at' => 'start',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('bom_items', [
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 0.025,
            'scrap_percentage' => 2.0,
        ]);
    }

    public function test_cannot_add_same_material_twice_to_bom(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $material = Material::factory()->create(['material_type_id' => $this->rawMaterial->id]);

        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.product-types.process-templates.bom.store', [$productType, $template]),
            [
                'material_id' => $material->id,
                'quantity_per_unit' => 1,
            ]
        );

        $response->assertSessionHasErrors('material_id');
    }

    public function test_admin_can_remove_bom_item(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $bomItem = BomItem::factory()->create(['process_template_id' => $template->id]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.product-types.process-templates.bom.destroy', [$productType, $template, $bomItem])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('bom_items', ['id' => $bomItem->id]);
    }

    // ── Snapshot with BOM ───────────────────────────────────────

    public function test_snapshot_includes_bom(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $step = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1]);
        $material = Material::factory()->create([
            'material_type_id' => $this->rawMaterial->id,
            'code' => 'PP-001',
            'name' => 'Polypropylene',
            'tracking_type' => 'batch',
        ]);

        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'template_step_id' => $step->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 0.5,
            'scrap_percentage' => 3.0,
            'consumed_at' => 'start',
        ]);

        $template->load(['steps', 'bomItems.material.materialType', 'bomItems.templateStep']);
        $snapshot = $template->toSnapshot();

        $this->assertArrayHasKey('bom', $snapshot);
        $this->assertCount(1, $snapshot['bom']);
        $this->assertEquals('PP-001', $snapshot['bom'][0]['material_code']);
        $this->assertEquals('Polypropylene', $snapshot['bom'][0]['material_name']);
        $this->assertEquals('raw_material', $snapshot['bom'][0]['material_type']);
        $this->assertEquals('batch', $snapshot['bom'][0]['tracking_type']);
        $this->assertEquals(0.5, $snapshot['bom'][0]['quantity_per_unit']);
        $this->assertEquals(3.0, $snapshot['bom'][0]['scrap_percentage']);
        $this->assertEquals('start', $snapshot['bom'][0]['consumed_at']);
        $this->assertEquals(1, $snapshot['bom'][0]['step_number']);
    }

    // ── Requirements Calculation ────────────────────────────────

    public function test_bom_requirements_calculation(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $material = Material::factory()->create([
            'material_type_id' => $this->rawMaterial->id,
            'unit_of_measure' => 'kg',
        ]);

        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 0.5,
            'scrap_percentage' => 10,
        ]);

        $service = app(BomService::class);
        $requirements = $service->calculateRequirements($template, 100);

        $this->assertCount(1, $requirements);
        $this->assertEquals(50.0, $requirements[0]['base_qty']); // 0.5 * 100
        $this->assertEquals(5.0, $requirements[0]['scrap_qty']); // 50 * 10%
        $this->assertEquals(55.0, $requirements[0]['required_qty']); // 50 + 5
    }

    public function test_snapshot_requirements_calculation(): void
    {
        $snapshot = [
            'bom' => [
                [
                    'material_code' => 'PP-001',
                    'material_name' => 'PP Granulate',
                    'material_type' => 'raw_material',
                    'quantity_per_unit' => 0.025,
                    'scrap_percentage' => 2.0,
                    'unit_of_measure' => 'kg',
                    'consumed_at' => 'start',
                    'step_number' => 1,
                ],
            ],
        ];

        $service = app(BomService::class);
        $requirements = $service->calculateFromSnapshot($snapshot, 1000);

        $this->assertEquals(25.0, $requirements[0]['base_qty']); // 0.025 * 1000
        $this->assertEquals(0.5, $requirements[0]['scrap_qty']); // 25 * 2%
        $this->assertEquals(25.5, $requirements[0]['required_qty']);
    }

    // ── Material Import ─────────────────────────────────────────

    public function test_material_import_creates_new(): void
    {
        $service = app(MaterialSyncService::class);

        $result = $service->importFromExternalSystem('subiekt_gt', [
            [
                'external_code' => 'SUR-PP-001',
                'name' => 'Polipropylen',
                'type' => 'raw_material',
                'unit' => 'kg',
            ],
            [
                'external_code' => 'OPK-FOL-001',
                'name' => 'Folia opakowaniowa',
                'type' => 'packaging',
                'unit' => 'pcs',
            ],
        ]);

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEmpty($result['errors']);
        $this->assertDatabaseHas('materials', [
            'external_code' => 'SUR-PP-001',
            'external_system' => 'subiekt_gt',
        ]);
    }

    public function test_material_import_updates_existing(): void
    {
        Material::factory()->create([
            'material_type_id' => $this->rawMaterial->id,
            'external_code' => 'SUR-PP-001',
            'external_system' => 'subiekt_gt',
            'name' => 'Old Name',
        ]);

        $service = app(MaterialSyncService::class);

        $result = $service->importFromExternalSystem('subiekt_gt', [
            [
                'external_code' => 'SUR-PP-001',
                'name' => 'Updated Name',
                'type' => 'raw_material',
            ],
        ]);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertDatabaseHas('materials', [
            'external_code' => 'SUR-PP-001',
            'name' => 'Updated Name',
        ]);
    }

    // ── API Tests ───────────────────────────────────────────────

    public function test_api_list_materials(): void
    {
        Material::factory()->count(3)->create(['material_type_id' => $this->rawMaterial->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/materials');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_api_create_material_admin_only(): void
    {
        // Operator should not be able to create
        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson('/api/v1/materials', [
                'code' => 'TEST-001',
                'name' => 'Test Material',
                'material_type_id' => $this->rawMaterial->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_api_admin_can_create_material(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/materials', [
                'code' => 'TEST-001',
                'name' => 'Test Material',
                'material_type_id' => $this->rawMaterial->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'TEST-001');
    }

    public function test_api_import_materials(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/materials/import', [
                'source_system' => 'subiekt_gt',
                'materials' => [
                    [
                        'external_code' => 'EXT-001',
                        'name' => 'Imported Material',
                        'type' => 'raw_material',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1);
    }

    public function test_api_bom_items_crud(): void
    {
        $template = ProcessTemplate::factory()->create();
        $material = Material::factory()->create(['material_type_id' => $this->rawMaterial->id]);

        // List (empty)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/process-templates/{$template->id}/bom-items");
        $response->assertStatus(200)->assertJsonCount(0, 'data');

        // Create
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/process-templates/{$template->id}/bom-items", [
                'material_id' => $material->id,
                'quantity_per_unit' => 2.5,
                'scrap_percentage' => 5,
                'consumed_at' => 'start',
            ]);
        $response->assertStatus(201);
        $bomItemId = $response->json('data.id');

        // List (1 item)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/process-templates/{$template->id}/bom-items");
        $response->assertStatus(200)->assertJsonCount(1, 'data');

        // Update
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/process-templates/{$template->id}/bom-items/{$bomItemId}", [
                'quantity_per_unit' => 3.0,
            ]);
        $response->assertStatus(200);

        // Delete
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/process-templates/{$template->id}/bom-items/{$bomItemId}");
        $response->assertStatus(200);
    }

    public function test_api_bom_requirements(): void
    {
        $template = ProcessTemplate::factory()->create();
        $material = Material::factory()->create([
            'material_type_id' => $this->rawMaterial->id,
            'unit_of_measure' => 'kg',
        ]);

        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 0.5,
            'scrap_percentage' => 10,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/process-templates/{$template->id}/bom-items/requirements?quantity=100");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals(55.0, $response->json('data.0.required_qty'));
    }

    // ── Default Scrap Inheritance ───────────────────────────────

    public function test_bom_inherits_default_scrap_from_material(): void
    {
        $template = ProcessTemplate::factory()->create();
        $material = Material::factory()->create([
            'material_type_id' => $this->rawMaterial->id,
            'default_scrap_percentage' => 5.0,
        ]);

        $service = app(BomService::class);
        $item = $service->addItem($template, [
            'material_id' => $material->id,
            'quantity_per_unit' => 1.0,
        ]);

        $this->assertEquals(5.0, $item->scrap_percentage);
    }
}
