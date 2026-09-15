<?php

namespace Tests\Feature\Seeders;

use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Services\Material\BomExplosionService;
use Database\Seeders\AirFilterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo BOM has to be deep enough to show what the MRP actually does.
 *
 * It used to carry a single sub-assembly one level under one product, so an
 * explosion reached raw material in one hop and netting never had to descend.
 * The two cases worth demonstrating — a shortage that only appears two levels
 * down, and one sub-assembly shared by two products — were both invisible.
 */
class DemoBomHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AirFilterDemoSeeder::class);
    }

    private function templateFor(string $productCode): ProcessTemplate
    {
        return ProcessTemplate::whereHas('productType', fn ($q) => $q->where('code', $productCode))
            ->firstOrFail();
    }

    public function test_every_sub_assembly_can_actually_be_made(): void
    {
        $subs = Material::where('is_manufactured', true)->get();

        $this->assertGreaterThanOrEqual(5, $subs->count(), 'The demo needs a real sub-assembly tree.');

        foreach ($subs as $sub) {
            // Without a producing routing the explosion cannot descend — the
            // line is manufactured in name only and netting stops there.
            $this->assertNotNull(
                $sub->producing_process_template_id,
                "{$sub->code} is manufactured but has no routing to make it.",
            );
        }
    }

    public function test_the_carbon_tree_is_three_levels_deep(): void
    {
        $tree = app(BomExplosionService::class)->tree($this->templateFor('CARBON'), 100);

        $depth = function (array $nodes) use (&$depth): int {
            $deepest = 0;
            foreach ($nodes as $node) {
                $below = empty($node['children']) ? 0 : $depth($node['children']);
                $deepest = max($deepest, 1 + $below);
            }

            return $deepest;
        };

        // Carbon X2 → cartridge → mesh cage → mesh + seal.
        $this->assertGreaterThanOrEqual(3, $depth($tree), 'The carbon BOM stopped descending too early.');
    }

    public function test_exploding_reaches_raw_material_through_the_sub_assemblies(): void
    {
        $leaves = app(BomExplosionService::class)->leafRequirements($this->templateFor('CARBON'), 100);

        $codes = collect($leaves)->pluck('material_code')->filter()->all();

        // These sit two levels under the product, behind the mesh cage. If the
        // explosion flattened at the cartridge they would never appear.
        $this->assertContains('MESH-RET', $codes);
        $this->assertContains('SEAL-EPDM', $codes);

        // And nothing manufactured should survive into the leaves.
        $manufactured = Material::where('is_manufactured', true)->pluck('code')->all();
        foreach ($manufactured as $code) {
            $this->assertNotContains($code, $codes, "{$code} was left unexploded in the leaves.");
        }
    }

    public function test_a_sub_assembly_is_shared_by_two_products(): void
    {
        $shell = Material::where('code', 'MOULDHOUS')->firstOrFail();

        $parents = collect(['PREFILTER', 'CARBON'])->filter(function (string $product) use ($shell) {
            return collect(app(BomExplosionService::class)->tree($this->templateFor($product), 1))
                ->contains(fn ($node) => ($node['material_code'] ?? null) === $shell->code);
        });

        // Netting has to add both parents' demand together before deciding how
        // many shells to make; one parent would never exercise that.
        $this->assertCount(2, $parents, 'The shared shell is no longer shared.');
    }
}
