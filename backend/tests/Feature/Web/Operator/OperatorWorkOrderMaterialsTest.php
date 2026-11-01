<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The order page's Materials tab: every BOM line against stock — need, have,
 * missing, the store's on-hand and what other batches have reserved — so the
 * operator sees the covered lines next to the short ones, not only a list of
 * what is missing.
 */
class OperatorWorkOrderMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Admin', 'Supervisor', 'Operator'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
    }

    private function material(string $code, float $stock, float $reserved = 0): Material
    {
        return Material::factory()->create([
            'code' => $code,
            'material_type_id' => MaterialType::factory()->create()->id,
            'unit_of_measure' => 'pcs',
            'stock_quantity' => $stock,
            'reserved_quantity' => $reserved,
        ]);
    }

    private function bomLine(Material $material, float $perUnit): array
    {
        return [
            'material_id' => $material->id,
            'material_code' => $material->code,
            'material_name' => $material->name,
            'unit_of_measure' => 'pcs',
            'quantity_per_unit' => $perUnit,
            'scrap_percentage' => 0,
            'consumed_at' => 'start',
        ];
    }

    private function order(array $bom, float $planned = 10): WorkOrder
    {
        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => $planned,
            'status' => WorkOrder::STATUS_PENDING,
            'process_snapshot' => ['bom' => $bom],
        ]);
    }

    private function show(WorkOrder $order)
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $this->line->id])
            ->get(route('operator.work-order.detail', $order));
    }

    public function test_covered_and_short_lines_are_listed_side_by_side(): void
    {
        $screw = $this->material('SCREW', 500);
        // 30 on the shelf, 25 of them spoken for by other batches: 5 usable.
        $housing = $this->material('HOUSING', 30, reserved: 25);

        $this->show($this->order([$this->bomLine($screw, 4), $this->bomLine($housing, 1)]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operator/WorkOrderDetail')
                ->has('materialLines', 2)
                ->where('materialLines.0.material_code', 'SCREW')
                ->where('materialLines.0.need', 40)
                ->where('materialLines.0.have', 40)
                ->where('materialLines.0.missing', 0)
                ->where('materialLines.0.short', false)
                ->where('materialLines.1.material_code', 'HOUSING')
                ->where('materialLines.1.need', 10)
                ->where('materialLines.1.have', 5)
                ->where('materialLines.1.missing', 5)
                ->where('materialLines.1.on_hand', 30)
                ->where('materialLines.1.reserved', 25)
                ->where('materialLines.1.short', true)
                ->has('materialShortages', 1)
            );
    }

    public function test_lines_of_the_same_material_are_summed(): void
    {
        $glue = $this->material('GLUE', 100);

        $this->show($this->order([$this->bomLine($glue, 1), $this->bomLine($glue, 2)]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('materialLines', 1)
                ->where('materialLines.0.need', 30)
                ->where('materialLines.0.short', false)
            );
    }

    public function test_a_component_missing_from_the_stock_list_reads_as_short(): void
    {
        $ghost = ['material_code' => 'GHOST', 'material_name' => 'Ghost part', 'unit_of_measure' => 'pcs', 'quantity_per_unit' => 1, 'scrap_percentage' => 0];

        $this->show($this->order([$ghost]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('materialLines.0.material_code', 'GHOST')
                ->where('materialLines.0.material_exists', false)
                ->where('materialLines.0.have', 0)
                ->where('materialLines.0.missing', 10)
                ->where('materialLines.0.short', true)
            );
    }

    public function test_an_order_without_a_bom_has_no_material_lines(): void
    {
        $this->show($this->order([]))
            ->assertInertia(fn (Assert $page) => $page->has('materialLines', 0));
    }

    public function test_guests_and_users_without_an_operator_role_cannot_open_the_page(): void
    {
        $order = $this->order([]);

        $this->get(route('operator.work-order.detail', $order))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->withSession(['selected_line_id' => $this->line->id])
            ->get(route('operator.work-order.detail', $order))
            ->assertForbidden();
    }
}
