<?php

namespace Tests\Feature\Web;

use App\Extension\FilterRegistry;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Modules extend the traceability console: `traceability.resolve` answers for
 * a code core does not know, `traceability.sections` adds sections to a result.
 */
class TraceabilityModuleHooksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin'));
    }

    private function section(string $title, array $rows): array
    {
        return [
            'key' => 'cartons',
            'title' => $title,
            'columns' => [['key' => 'code', 'label' => 'Carton', 'mono' => true, 'link' => true]],
            'rows' => $rows,
        ];
    }

    public function test_a_module_adds_sections_to_a_core_result_and_gets_its_subject(): void
    {
        $batch = Batch::factory()->create(['lot_number' => 'FG-7']);
        $seen = null;
        app(FilterRegistry::class)->addFilter('traceability.sections', function (array $sections, array $context) use (&$seen) {
            $seen = $context;

            return $context['type'] === 'batch'
                ? [...$sections, $this->section('Cartons', [['code' => 'CT0000001']])]
                : $sections;
        });

        $this->actingAs($this->admin)->get('/admin/traceability?q=FG-7')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result.type', 'batch')
                ->where('result.sections.0.title', 'Cartons')
                ->where('result.sections.0.rows.0.code', 'CT0000001'));

        $this->assertSame('batch', $seen['type']);
        $this->assertTrue($batch->is($seen['model']));
        $this->assertSame('FG-7', $seen['term']);
    }

    public function test_a_code_only_a_module_knows_is_resolved_by_the_module(): void
    {
        app(FilterRegistry::class)->addFilter('traceability.resolve', fn ($found, array $context) => $found
            ?? ($context['term'] === 'PL000042' ? [
                'kind' => 'Pallet', 'id' => 'PL000042', 'status' => 'Closed',
                'facts' => [['label' => 'Cartons', 'value' => '2']],
                'sections' => [$this->section('Content', [['code' => 'CT0000001']])],
            ] : null));

        $this->actingAs($this->admin)->get('/admin/traceability?q=PL000042')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result.type', 'module')
                ->where('result.data.id', 'PL000042')
                ->where('result.data.sections.0.rows.0.code', 'CT0000001')
                ->where('result.sections', []));
    }

    public function test_an_unknown_code_still_finds_nothing(): void
    {
        app(FilterRegistry::class)->addFilter('traceability.resolve', fn ($found) => $found);

        $this->actingAs($this->admin)->get('/admin/traceability?q=NOPE-1')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('result', null));
    }
}
