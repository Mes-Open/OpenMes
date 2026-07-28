<?php

namespace Tests\Feature\Material;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use App\Services\Material\BomExplosionService;
use App\Services\Material\BomService;
use App\Services\Material\NetRequirementsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Multi-level BOMs: a BOM line pointing at a material flagged `is_manufactured`
 * expands into that material's own producing template, recursively.
 */
class HierarchicalBomTest extends TestCase
{
    use RefreshDatabase;

    private MaterialType $type;

    private BomExplosionService $explosion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = MaterialType::create(['code' => 'raw_material', 'name' => 'Raw Material']);
        $this->explosion = app(BomExplosionService::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function template(string $name): ProcessTemplate
    {
        return ProcessTemplate::factory()->create(['name' => $name]);
    }

    private function purchased(string $code): Material
    {
        return Material::factory()->create([
            'code' => $code,
            'material_type_id' => $this->type->id,
            'unit_of_measure' => 'pcs',
        ]);
    }

    /** A subassembly item plus the template that produces it. */
    private function subassembly(string $code, ProcessTemplate $producedBy): Material
    {
        return Material::factory()->create([
            'code' => $code,
            'material_type_id' => $this->type->id,
            'unit_of_measure' => 'pcs',
            'is_manufactured' => true,
            'producing_process_template_id' => $producedBy->id,
        ]);
    }

    private function line(ProcessTemplate $template, Material $material, float $qty, float $scrap = 0): BomItem
    {
        return BomItem::create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => $qty,
            'scrap_percentage' => $scrap,
            'consumed_at' => 'start',
        ]);
    }

    /** @return array<string, float> code => required_qty */
    private function byCode(array $rows): array
    {
        return collect($rows)->mapWithKeys(fn ($r) => [$r['material_code'] => (float) $r['required_qty']])->all();
    }

    /**
     * The structure from the issue, with one extra level so three BOM levels
     * are exercised:
     *
     *   ASSEMBLY-1000
     *   ├── SUBASSEMBLY-1100 × 2
     *   │   ├── SUBASSEMBLY-1150 × 3
     *   │   │   └── PART-1160 × 5
     *   │   ├── PART-1110 × 4
     *   │   └── PART-1120 × 1
     *   └── PART-1200 × 6
     */
    private function threeLevelStructure(): ProcessTemplate
    {
        $t1000 = $this->template('ASSEMBLY-1000');
        $t1100 = $this->template('SUBASSEMBLY-1100');
        $t1150 = $this->template('SUBASSEMBLY-1150');

        $sub1100 = $this->subassembly('SUBASSEMBLY-1100', $t1100);
        $sub1150 = $this->subassembly('SUBASSEMBLY-1150', $t1150);

        $this->line($t1000, $sub1100, 2);
        $this->line($t1000, $this->purchased('PART-1200'), 6);

        $this->line($t1100, $sub1150, 3);
        $this->line($t1100, $this->purchased('PART-1110'), 4);
        $this->line($t1100, $this->purchased('PART-1120'), 1);

        $this->line($t1150, $this->purchased('PART-1160'), 5);

        return $t1000;
    }

    // ── Structure ────────────────────────────────────────────────────────────

    public function test_a_product_can_contain_a_manufactured_subassembly(): void
    {
        $parent = $this->template('ASSEMBLY');
        $childTemplate = $this->template('SUB');
        $this->line($parent, $this->subassembly('SUB-1', $childTemplate), 2);
        $this->line($childTemplate, $this->purchased('PART-A'), 3);

        $tree = $this->explosion->tree($parent, 1);

        $this->assertCount(1, $tree);
        $this->assertTrue($tree[0]['is_manufactured']);
        $this->assertSame('SUB-1', $tree[0]['material_code']);
        $this->assertCount(1, $tree[0]['children'], 'The subassembly expands into its own BOM.');
        $this->assertSame('PART-A', $tree[0]['children'][0]['material_code']);
    }

    public function test_three_levels_are_created_and_displayed(): void
    {
        $tree = $this->explosion->tree($this->threeLevelStructure(), 1);

        // level 0 → level 1 → level 2
        $sub1100 = collect($tree)->firstWhere('material_code', 'SUBASSEMBLY-1100');
        $this->assertSame(0, $sub1100['level']);

        $sub1150 = collect($sub1100['children'])->firstWhere('material_code', 'SUBASSEMBLY-1150');
        $this->assertSame(1, $sub1150['level']);

        $part1160 = collect($sub1150['children'])->firstWhere('material_code', 'PART-1160');
        $this->assertSame(2, $part1160['level']);
        $this->assertSame([], $part1160['children']);
    }

    // ── Recursive requirements ───────────────────────────────────────────────

    public function test_recursive_explosion_returns_correct_leaf_quantities(): void
    {
        $leaves = $this->byCode($this->explosion->leafRequirements($this->threeLevelStructure(), 10));

        // 10 assemblies → 20 subassemblies → 60 of SUB-1150 → 300 of PART-1160.
        $this->assertSame(60.0, $leaves['PART-1200'], '10 × 6 direct');
        $this->assertSame(80.0, $leaves['PART-1110'], '10 × 2 × 4');
        $this->assertSame(20.0, $leaves['PART-1120'], '10 × 2 × 1');
        $this->assertSame(300.0, $leaves['PART-1160'], '10 × 2 × 3 × 5');

        // Subassemblies are resolved away — only buyable leaves remain.
        $this->assertArrayNotHasKey('SUBASSEMBLY-1100', $leaves);
        $this->assertArrayNotHasKey('SUBASSEMBLY-1150', $leaves);
    }

    public function test_scrap_compounds_through_levels(): void
    {
        $parent = $this->template('P');
        $childTemplate = $this->template('C');
        // 10% scrap on the subassembly line, 20% on the part beneath it.
        $this->line($parent, $this->subassembly('SUB', $childTemplate), 2, 10);
        $this->line($childTemplate, $this->purchased('PART'), 5, 20);

        $leaves = $this->byCode($this->explosion->leafRequirements($parent, 100));

        // 100 × 2 × 1.10 = 220 subassemblies; 220 × 5 × 1.20 = 1320 parts.
        $this->assertSame(1320.0, $leaves['PART']);
    }

    public function test_a_component_used_on_several_branches_is_summed_once(): void
    {
        $parent = $this->template('P');
        $childTemplate = $this->template('C');
        $shared = $this->purchased('SHARED');

        $this->line($parent, $this->subassembly('SUB', $childTemplate), 2);
        $this->line($parent, $shared, 3);        // 3 per unit directly
        $this->line($childTemplate, $shared, 4); // and 4 per subassembly

        $leaves = $this->explosion->leafRequirements($parent, 10);
        $shared = collect($leaves)->where('material_code', 'SHARED');

        $this->assertCount(1, $shared, 'One accumulated line, not one per path.');
        // 10 × 3 = 30 direct, plus 10 × 2 × 4 = 80 through the subassembly.
        $this->assertSame(110.0, (float) $shared->first()['required_qty']);
    }

    public function test_a_manufactured_material_without_a_template_is_a_leaf(): void
    {
        $parent = $this->template('P');
        $orphan = Material::factory()->manufactured()->create([
            'code' => 'SUB-NO-BOM',
            'material_type_id' => $this->type->id,
        ]);
        $this->line($parent, $orphan, 2);

        $leaves = $this->byCode($this->explosion->leafRequirements($parent, 5));

        // Flagged make-in-house but not explodable yet, so it stays a requirement.
        $this->assertSame(10.0, $leaves['SUB-NO-BOM']);
    }

    public function test_a_soft_deleted_producing_template_makes_the_material_a_leaf(): void
    {
        $parent = $this->template('P');
        $childTemplate = $this->template('C');
        $sub = $this->subassembly('SUB', $childTemplate);
        $this->line($parent, $sub, 2);
        $this->line($childTemplate, $this->purchased('PART'), 5);

        // Soft delete leaves the FK pointing at a row the relation won't resolve
        // (nullOnDelete only fires on a hard delete), so explosion must not try
        // to descend into a template it can't load.
        $childTemplate->delete();

        $this->assertFalse($sub->fresh()->isExplodable());

        $leaves = $this->byCode($this->explosion->leafRequirements($parent, 10));

        $this->assertSame(20.0, $leaves['SUB'], 'Falls back to a requirement for the subassembly itself.');
        $this->assertArrayNotHasKey('PART', $leaves);
    }

    public function test_a_producing_template_without_the_manufactured_flag_stays_purchased(): void
    {
        $parent = $this->template('P');
        $childTemplate = $this->template('C');
        $this->line($childTemplate, $this->purchased('PART'), 5);

        // Make-or-buy item: the routing is on file but the part is bought for
        // now. Explosion needs both halves, so this is a leaf, not a subassembly.
        $boughtForNow = Material::factory()->create([
            'code' => 'DUAL-SOURCE',
            'material_type_id' => $this->type->id,
            'is_manufactured' => false,
            'producing_process_template_id' => $childTemplate->id,
        ]);
        $this->line($parent, $boughtForNow, 2);

        $this->assertFalse($boughtForNow->isExplodable());

        $leaves = $this->byCode($this->explosion->leafRequirements($parent, 10));

        $this->assertSame(20.0, $leaves['DUAL-SOURCE']);
        $this->assertArrayNotHasKey('PART', $leaves);
    }

    public function test_new_columns_are_exposed_to_the_materials_shape(): void
    {
        // A column missing from the allowlist never reaches the browser, so the
        // make-or-buy flag would be invisible to any UI built on the shape.
        $columns = app(\App\Sync\ShapeRegistry::class)->find('materials')->columns();

        $this->assertContains('is_manufactured', $columns);
        $this->assertContains('producing_process_template_id', $columns);
    }

    // ── Circular references ──────────────────────────────────────────────────

    public function test_a_material_produced_by_the_template_itself_is_rejected(): void
    {
        $template = $this->template('SELF');
        $self = $this->subassembly('SELF-1', $template);

        $this->expectException(ValidationException::class);
        app(BomService::class)->addItem($template, ['material_id' => $self->id, 'quantity_per_unit' => 1]);
    }

    public function test_an_indirect_cycle_is_rejected(): void
    {
        // A consumes B; adding A to B's template would close the loop A→B→A.
        $tA = $this->template('A');
        $tB = $this->template('B');
        $subA = $this->subassembly('SUB-A', $tA);
        $subB = $this->subassembly('SUB-B', $tB);

        $this->line($tA, $subB, 1);

        try {
            app(BomService::class)->addItem($tB, ['material_id' => $subA->id, 'quantity_per_unit' => 1]);
            $this->fail('Expected the circular reference to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('material_id', $e->errors());
        }

        $this->assertDatabaseMissing('bom_items', ['process_template_id' => $tB->id, 'material_id' => $subA->id]);
    }

    public function test_switching_an_existing_line_to_a_cycling_material_is_rejected(): void
    {
        $tA = $this->template('A');
        $tB = $this->template('B');
        $subA = $this->subassembly('SUB-A', $tA);
        $subB = $this->subassembly('SUB-B', $tB);

        $this->line($tA, $subB, 1);
        $harmless = $this->line($tB, $this->purchased('PART'), 1);

        $this->expectException(ValidationException::class);
        app(BomService::class)->updateItem($harmless, ['material_id' => $subA->id]);
    }

    public function test_a_template_that_consumes_a_material_cannot_also_produce_it(): void
    {
        $template = $this->template('T');
        $part = $this->purchased('PART');
        $this->line($template, $part, 1);

        // The mirror entry point: flipping the item master rather than the BOM.
        $this->expectException(ValidationException::class);
        $part->update(['is_manufactured' => true, 'producing_process_template_id' => $template->id]);
    }

    public function test_deeply_nested_but_acyclic_structures_are_allowed(): void
    {
        $templates = collect(range(0, 4))->map(fn ($i) => $this->template("L{$i}"));

        // L0 → L1 → L2 → L3 → L4(leaf part), 2 of each at every level.
        foreach ($templates as $i => $template) {
            if ($i === $templates->count() - 1) {
                $this->line($template, $this->purchased('LEAF'), 2);

                continue;
            }
            $this->line($template, $this->subassembly("SUB-{$i}", $templates[$i + 1]), 2);
        }

        $leaves = $this->byCode($this->explosion->leafRequirements($templates[0], 1));

        $this->assertSame(32.0, $leaves['LEAF'], '2^5 through five levels.');
    }

    // ── Backward compatibility ───────────────────────────────────────────────

    public function test_a_single_level_bom_explodes_to_the_same_numbers_as_before(): void
    {
        $template = $this->template('FLAT');
        $this->line($template, $this->purchased('P1'), 3, 10);
        $this->line($template, $this->purchased('P2'), 1.5);

        $flat = collect(app(BomService::class)->calculateRequirements($template, 200))
            ->mapWithKeys(fn ($r) => [$r['material_code'] => (float) $r['required_qty']])->all();
        $exploded = $this->byCode($this->explosion->leafRequirements($template, 200));

        $this->assertSame($flat, $exploded, 'No manufactured components → identical result.');
        $this->assertSame(660.0, $exploded['P1']);
        $this->assertSame(300.0, $exploded['P2']);
    }

    public function test_existing_materials_default_to_purchased(): void
    {
        $material = $this->purchased('LEGACY');

        $this->assertFalse($material->is_manufactured);
        $this->assertNull($material->producing_process_template_id);
        $this->assertFalse($material->isExplodable());
    }

    // ── MRP ──────────────────────────────────────────────────────────────────

    public function test_mrp_explodes_demand_through_subassemblies(): void
    {
        $parent = $this->threeLevelStructure();

        WorkOrder::factory()->create([
            'product_type_id' => $parent->product_type_id,
            'planned_qty' => 10,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => now()->addDays(3),
        ]);

        $report = app(NetRequirementsService::class)
            ->report(now()->subDay(), now()->addDays(30));

        $required = collect($report['requirements'])->mapWithKeys(
            fn ($r) => [$r['code'] => (float) $r['required_qty']],
        );

        // Same leaf figures the explosion produces — MRP no longer stops at level one.
        $this->assertSame(300.0, $required['PART-1160']);
        $this->assertSame(80.0, $required['PART-1110']);
        $this->assertSame(60.0, $required['PART-1200']);
        $this->assertFalse($required->has('SUBASSEMBLY-1100'));
    }
}
