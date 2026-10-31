<?php

namespace Tests\Feature\Seeders;

use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\User;
use App\Services\Quality\InboundInspectionService;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PanelFurnitureDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The example companies' inspection plans must be usable: published, so
 * starting an inspection of the material picks them up, and with structured
 * criteria, so the inspection has rows to fill in. They used to be seeded as
 * drafts with bare-string criteria, and every inspection started on a fresh
 * demo had nothing to record and could never be completed.
 */
class DemoInspectionPlansTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<int, class-string>> */
    public static function seeders(): array
    {
        return [
            'print shop' => [PrintShopDemoSeeder::class],
            'machine shop' => [MachineShopDemoSeeder::class],
            'bakery' => [BakeryDemoSeeder::class],
            'panel furniture' => [PanelFurnitureDemoSeeder::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_demo_plans_are_published_and_yield_criteria(string $seeder): void
    {
        $this->seed($seeder);

        $plans = InspectionPlan::all();
        $this->assertNotEmpty($plans);

        foreach ($plans as $plan) {
            $this->assertTrue($plan->isPublished(), "{$plan->name} is a draft, so no inspection would use it.");

            $material = Material::findOrFail($plan->material_id);
            $this->assertTrue(
                InspectionPlan::applicableTo($material)->whereKey($plan->id)->exists(),
                "{$plan->name} is not offered for {$material->name}.",
            );

            foreach ($plan->criteria as $criterion) {
                $this->assertIsArray($criterion);
                $this->assertNotSame('', $criterion['name'] ?? '');
                $this->assertArrayHasKey('type', $criterion);
            }

            $inspection = app(InboundInspectionService::class)
                ->start($material, 'LOT-'.$plan->id, 10, $plan, User::factory()->create());
            $this->assertCount(count($plan->criteria), $inspection->results);
            $this->assertNotContains('Unnamed', $inspection->results->pluck('criterion_name')->all());
        }
    }
}
