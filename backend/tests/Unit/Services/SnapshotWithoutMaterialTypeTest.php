<?php

namespace Tests\Unit\Services;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Services\ProcessTemplate\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `materials.material_type_id` is nullable, and the snapshot taken when a work
 * order is raised read the type without allowing for that: one material saved
 * without a type in the bill of materials, and no order could be raised for
 * the product at all.
 */
class SnapshotWithoutMaterialTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_material_without_a_type_does_not_stop_the_snapshot(): void
    {
        $template = ProcessTemplate::factory()->create();

        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => Material::factory()->create(['material_type_id' => null, 'code' => 'NO-TYPE'])->id,
        ]);

        $snapshot = app(SnapshotService::class)->createSnapshot($template);

        $this->assertSame('NO-TYPE', $snapshot['bom'][0]['material_code']);
        $this->assertNull($snapshot['bom'][0]['material_type']);
    }

    public function test_a_material_with_a_type_still_carries_it(): void
    {
        $template = ProcessTemplate::factory()->create();
        $material = Material::factory()->create();

        BomItem::factory()->create(['process_template_id' => $template->id, 'material_id' => $material->id]);

        $snapshot = app(SnapshotService::class)->createSnapshot($template);

        $this->assertSame($material->materialType->code, $snapshot['bom'][0]['material_type']);
    }
}
