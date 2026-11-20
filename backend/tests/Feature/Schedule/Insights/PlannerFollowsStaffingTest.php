<?php

namespace Tests\Feature\Schedule\Insights;

use App\Events\CollectionChanged;
use App\Sync\ShapeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Planner's badges and drawer follow line-operator changes live: an
 * assignment touches the line, `lines_staffing` carries the line's
 * `updated_at` to every planner — Admin, and a Supervisor without the
 * Dashboard tab — and the Planner watches exactly what changes.
 */
class PlannerFollowsStaffingTest extends TestCase
{
    use PlansWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlant();
    }

    public function test_assigning_and_unassigning_an_operator_broadcasts_a_new_line_timestamp(): void
    {
        $line = $this->plantLine('CNC Turning', 'CNC');
        $jan = $this->operator('Jan Kowalski');
        $admin = $this->planner();
        $stamps = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$stamps, $line) {
            if ($e->collection === 'lines_staffing' && (int) $e->row['id'] === $line->id) {
                $this->assertSame(['id', 'updated_at'], array_keys($e->row));
                $stamps[] = $e->row['updated_at'];
            }
        });

        $this->travelTo(now()->addMinute());
        $this->actingAs($admin)->post("/admin/lines/{$line->id}/assign-operator", ['user_id' => $jan->id])->assertRedirect();
        $this->travelTo(now()->addMinute());
        $this->actingAs($admin)->delete("/admin/lines/{$line->id}/unassign-operator/{$jan->id}")->assertRedirect();

        $this->assertCount(2, $stamps, 'assign and unassign both reach the planner');
        $this->assertNotSame($stamps[0], $stamps[1]);
    }

    public function test_planners_read_the_line_timestamps_others_do_not(): void
    {
        $line = $this->plantLine('CNC Turning', 'CNC');
        $inactive = $this->plantLine('Old line', 'OLD');
        $inactive->update(['is_active' => false]);

        // A Supervisor with the Schedule tab only — no Dashboard tab.
        $supervisor = $this->planner('Supervisor');
        $this->assertFalse($supervisor->can('tab:dashboard'));
        $this->actingAs($this->planner())->getJson('/api/collections/lines_staffing')->assertOk();

        $body = json_encode($this->actingAs($supervisor)->getJson('/api/collections/lines_staffing')->assertOk()->json());
        $this->assertStringContainsString('"updated_at"', $body);
        $this->assertStringContainsString('"id":'.$line->id, $body);
        $this->assertStringNotContainsString('"id":'.$inactive->id.',', $body, 'active lines only');
        $this->assertStringNotContainsString('CNC Turning', $body, 'ids and timestamps only');

        $operator = $this->operator('Jan Kowalski', $line);
        $this->actingAs($operator)->getJson('/api/collections/lines_staffing')->assertForbidden();
        $operator->givePermissionTo(Permission::findOrCreate('tab:schedule', 'web'));
        $this->actingAs($operator->fresh())->getJson('/api/collections/lines_staffing')->assertOk();
    }

    public function test_guests_cannot_read_it(): void
    {
        $this->getJson('/api/collections/lines_staffing')->assertUnauthorized();
    }

    public function test_the_planner_watches_columns_its_shape_carries(): void
    {
        $planner = file_get_contents(resource_path('js/Pages/admin/schedule/Planner.jsx'));
        preg_match_all('/<ShapeChangeWatcher shape="([a-z_]+)" fields=\{\[([^\]]*)\]\}/', $planner, $watchers, PREG_SET_ORDER);
        $this->assertNotEmpty($watchers);

        $registry = app(ShapeRegistry::class);
        $staffing = false;
        foreach ($watchers as [, $shape, $fields]) {
            $columns = $registry->find($shape)?->columns();
            $this->assertNotNull($columns, "the planner watches unknown shape {$shape}");
            preg_match_all("/'([a-z_]+)'/", $fields, $names);
            foreach ($names[1] as $field) {
                $this->assertContains($field, $columns, "{$shape} carries no {$field}, so the planner would never see it change");
            }
            $staffing = $staffing || $shape === 'lines_staffing';
        }
        $this->assertTrue($staffing, 'the planner follows line staffing');
    }
}
