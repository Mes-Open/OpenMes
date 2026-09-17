<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Import\AbstractEntityImporter;
use App\Import\ImportRegistry;
use App\Models\User;
use App\Services\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The seams a module reaches the operator panel, the Import screen and the
 * translation bundle through. Each one must be a no-op for an installation
 * with no modules, and must carry a module's contribution when there is one.
 */
class ModuleSeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_tabs_reach_the_page_sorted_with_a_path_prefix(): void
    {
        $menu = app(MenuRegistry::class);
        $this->assertSame([], $menu->getOperatorItems());

        $menu->addOperatorItem('Waste', url('/operator/waste'), order: 50);
        $menu->addOperatorItem('Team', url('/operator/team?x=1'), order: 30);
        $menu->addOperatorItem('Docs', url('/operator/docs'), order: 40, prefix: '/operator/doc');

        $items = $menu->getOperatorItems();
        $this->assertSame(['Team', 'Docs', 'Waste'], array_column($items, 'label'));
        $this->assertSame('/operator/team', $items[0]['prefix']);
        $this->assertSame('/operator/doc', $items[1]['prefix']);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get('/operator/select-line')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('moduleNav.operator.0.label', 'Team')
                ->where('moduleNav.operator.0.url', url('/operator/team?x=1')));
    }

    public function test_a_module_importer_joins_the_registry_through_the_filter(): void
    {
        $registry = app(ImportRegistry::class);
        $before = $registry->keys();
        $this->assertNotContains('seam_things', $before);

        app(FilterRegistry::class)->addFilter('import.entities', fn (array $e) => [...$e, SeamThingImporter::class]);

        // Already-built instances are rebuilt when the entity list changes.
        $this->assertContains('seam_things', $registry->keys());
        $this->assertSame('seam-things', $registry->fromSlug('seam-things')?->slug());
        $this->assertArrayHasKey('seam_things', $registry->forSection('admin'));
        $this->assertArrayNotHasKey('seam_things', $registry->forSection('supervisor'));
        $this->assertSame(count($before) + 1, count($registry->keys()));
    }
}

class SeamThingImporter extends AbstractEntityImporter
{
    public function key(): string
    {
        return 'seam_things';
    }

    public function label(): string
    {
        return 'Seam things';
    }

    public function description(): string
    {
        return 'A test-only entity.';
    }

    public function fields(): array
    {
        return ['name' => ['label' => 'Name', 'required' => true, 'type' => 'text']];
    }

    public function options(): array
    {
        return [];
    }

    public function optionRules(): array
    {
        return [];
    }

    public function sample(): array
    {
        return ['headers' => ['name'], 'rows' => [['a']]];
    }

    public function import(array $rows, array $options): array
    {
        return ['imported' => count($rows), 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
}
