<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Inspection;
use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductionDowntime;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Dashboard\PlantOverviewService;
use App\Support\ModuleRegistry;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The dashboard's "Needs action" list (`overview.needs_action`): one row per
 * thing that is wrong, worst and oldest first, each with at most one button
 * that leads to an existing page or runs the existing stop escalation.
 */
class DashboardNeedsActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-05-26 '.$time);
    }

    /** @return array{total: int, items: array<int, array<string, mixed>>} */
    private function needsAction(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->inertiaProps('overview.needs_action');
    }

    /** @return array<string, mixed> */
    private function item(string $key, ?User $user = null): array
    {
        $item = collect($this->needsAction($user)['items'])->firstWhere('key', $key);
        $this->assertNotNull($item, "Item {$key} missing from needs_action");

        return $item;
    }

    private function station(Line $line, ?string $state = null, string $since = '08:00'): Workstation
    {
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        if ($state) {
            WorkstationState::create([
                'workstation_id' => $station->id, 'state' => $state,
                'started_at' => $this->at($since), 'ended_at' => null,
            ]);
        }

        return $station;
    }

    private function openStop(Line $line, ?Workstation $station, string $since = '09:00'): ProductionDowntime
    {
        return ProductionDowntime::create([
            'line_id' => $line->id,
            'workstation_id' => $station?->id,
            'downtime_reason_id' => DowntimeReason::factory()->unplanned()->create(['name' => 'Jam'])->id,
            'needs_reason' => false,
            'started_at' => $this->at($since),
            'ended_at' => null,
        ]);
    }

    /** A station working an order since 08:00, faulted at 09:00 with an open stop. */
    private function escalatableDownLine(string $name = 'Line Five'): array
    {
        $line = Line::factory()->create(['name' => $name]);
        $station = $this->station($line, WorkstationState::FAULT, '09:00');
        $stop = $this->openStop($line, $station);
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        Batch::factory()->create([
            'work_order_id' => $order->id, 'workstation_id' => $station->id,
            'status' => Batch::STATUS_IN_PROGRESS, 'started_at' => $this->at('08:00'),
        ]);

        return [$line, $station, $stop, $order];
    }

    private function lowStock(array $attrs = []): Material
    {
        return Material::factory()->create(['stock_quantity' => 12.5, 'min_stock_level' => 40, 'unit_of_measure' => 'kg'] + $attrs);
    }

    private function failedInspection(array $attrs = []): Inspection
    {
        return Inspection::factory()->failed()->create(['lot_number' => 'L-77', 'completed_at' => $this->at('09:30')] + $attrs);
    }

    private function overdueAction(array $issueAttrs = []): IssueAction
    {
        return IssueAction::factory()->overdue()->create([
            'issue_id' => Issue::factory()->create($issueAttrs)->id,
            'title' => 'Replace seal',
        ]);
    }

    private function withoutModule(string $key): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), [$key])));
    }

    // ── item types ─────────────────────────────────────────────────────

    public function test_empty_plant_has_nothing_to_act_on(): void
    {
        $this->assertSame(['total' => 0, 'items' => []], $this->needsAction());
    }

    public function test_down_line_with_running_order_offers_escalation(): void
    {
        [$line, , $stop] = $this->escalatableDownLine();

        $item = $this->item('line_down:'.$line->id);

        $this->assertSame('line_down', $item['type']);
        $this->assertSame('critical', $item['severity']);
        $this->assertSame('Line Five is down', $item['title']);
        $this->assertSame('Jam', $item['description']);
        $this->assertSame($this->at('09:00')->toIso8601String(), $item['since']);
        $this->assertSame([
            'kind' => 'escalate',
            'label' => 'Open ticket',
            'href' => "/admin/shift-monitor/downtimes/{$stop->id}/escalate",
        ], $item['action']);
    }

    public function test_down_line_without_running_order_links_to_shift_monitor(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '09:00');
        $this->openStop($line, $station);

        $item = $this->item('line_down:'.$line->id);

        $this->assertSame('link', $item['action']['kind']);
        $this->assertSame('Shift Monitor', $item['action']['label']);
        $this->assertSame('/admin/shift-monitor?workstation='.$station->id, $item['action']['href']);
    }

    public function test_batch_finished_before_the_stop_does_not_make_it_escalatable(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '09:00');
        $this->openStop($line, $station);
        Batch::factory()->create([
            'workstation_id' => $station->id, 'status' => Batch::STATUS_DONE,
            'started_at' => $this->at('06:00'), 'completed_at' => $this->at('08:30'),
        ]);

        $this->assertSame('link', $this->item('line_down:'.$line->id)['action']['kind']);
    }

    public function test_line_down_for_maintenance_links_to_shift_monitor(): void
    {
        $line = Line::factory()->create();
        MaintenanceEvent::create([
            'title' => 'PM', 'event_type' => MaintenanceEvent::TYPE_PLANNED,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $line->id, 'started_at' => $this->at('09:00'),
        ]);

        $item = $this->item('line_down:'.$line->id);

        $this->assertSame('Maintenance', $item['description']);
        $this->assertSame('/admin/shift-monitor', $item['action']['href']);
    }

    public function test_already_escalated_stop_offers_view_ticket(): void
    {
        [$line, , $stop, $order] = $this->escalatableDownLine();
        $issue = Issue::factory()->create(['work_order_id' => $order->id, 'production_downtime_id' => $stop->id]);

        $this->assertSame(
            ['kind' => 'link', 'label' => 'View ticket', 'href' => '/admin/issues?issue='.$issue->id],
            $this->item('line_down:'.$line->id)['action'],
        );
    }

    public function test_blocked_order_line_resolves_to_the_order(): void
    {
        $line = Line::factory()->create(['name' => 'Line Three']);
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_BLOCKED]);

        $item = $this->item('line_blocked:'.$line->id);

        $this->assertSame('warning', $item['severity']);
        $this->assertSame('Line Three is blocked', $item['title']);
        $this->assertSame('Work order blocked', $item['description']);
        $this->assertSame(['kind' => 'link', 'label' => 'Resolve', 'href' => '/admin/work-orders/'.$order->id], $item['action']);
    }

    public function test_waiting_line_without_an_order_has_no_button(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::WAITING);

        $item = $this->item('line_blocked:'.$line->id);

        $this->assertSame('Waiting for material', $item['description']);
        $this->assertNull($item['action']);
    }

    public function test_low_stock_material(): void
    {
        $material = $this->lowStock(['name' => 'Steel sheet']);
        Material::factory()->create(['stock_quantity' => 50, 'min_stock_level' => 40]); // fine
        $this->lowStock(['is_active' => false]); // inactive: ignored

        $items = $this->needsAction()['items'];
        $this->assertCount(1, $items);
        $this->assertSame('material_low:'.$material->id, $items[0]['key']);
        $this->assertSame('warning', $items[0]['severity']);
        $this->assertSame('Steel sheet below minimum', $items[0]['title']);
        $this->assertSame('12.5 of 40 kg', $items[0]['description']);
        $this->assertSame('/admin/materials/'.$material->id, $items[0]['action']['href']);
    }

    public function test_failed_inspection_awaiting_disposition(): void
    {
        $inspection = $this->failedInspection();
        $this->failedInspection(['disposition' => Inspection::DISPOSITION_SCRAP]); // decided: done
        Inspection::factory()->passed()->create();

        $items = $this->needsAction()['items'];
        $this->assertCount(1, $items);
        $item = $items[0];
        $this->assertSame('inspection_failed:'.$inspection->id, $item['key']);
        $this->assertSame('critical', $item['severity']);
        $this->assertSame('Inbound inspection failed', $item['title']);
        $this->assertSame($inspection->material->name.' · lot L-77', $item['description']);
        $this->assertSame(['kind' => 'link', 'label' => 'Review', 'href' => '/operator/inspections/'.$inspection->id], $item['action']);
    }

    public function test_quarantined_lot(): void
    {
        $lot = MaterialLot::factory()->quarantine()->create([
            'lot_number' => 'Q-1', 'hold_reason' => 'Damaged', 'held_at' => $this->at('07:00'),
        ]);
        MaterialLot::factory()->create(); // released

        $items = $this->needsAction()['items'];
        $this->assertCount(1, $items);
        $this->assertSame('Lot Q-1 in quarantine', $items[0]['title']);
        $this->assertSame($lot->material->name.' · Damaged', $items[0]['description']);
        $this->assertSame($this->at('07:00')->toIso8601String(), $items[0]['since']);
        $this->assertSame(['kind' => 'link', 'label' => 'Review', 'href' => '/admin/material-lots/'.$lot->id], $items[0]['action']);
    }

    public function test_overdue_corrective_action_links_to_its_issue(): void
    {
        $action = $this->overdueAction();
        IssueAction::factory()->create(['due_date' => now()->addDay()->toDateString()]); // not yet due
        IssueAction::factory()->verified()->create(['due_date' => now()->subDays(5)->toDateString()]); // settled

        $items = $this->needsAction()['items'];
        $this->assertCount(1, $items);
        $this->assertSame('Corrective action overdue', $items[0]['title']);
        $this->assertSame("Replace seal · Issue #{$action->issue_id} · due 2026-05-23", $items[0]['description']);
        $this->assertSame(['kind' => 'link', 'label' => 'Open', 'href' => '/admin/issues?issue='.$action->issue_id], $items[0]['action']);
        $this->assertStringNotContainsString('NC-', $items[0]['description']);
    }

    // ── order and size ─────────────────────────────────────────────────

    public function test_critical_before_warning_then_oldest_first(): void
    {
        MaterialLot::factory()->quarantine()->create(['held_at' => $this->at('06:00')]); // oldest, warning
        $this->lowStock(); // warning, now
        $this->failedInspection(['completed_at' => $this->at('09:45')]); // critical, newest critical
        [$line] = $this->escalatableDownLine(); // critical, 09:00

        $types = array_column($this->needsAction()['items'], 'type');

        $this->assertSame(['line_down', 'inspection_failed', 'lot_quarantined', 'material_low'], $types);
    }

    public function test_total_counts_everything_but_items_are_capped(): void
    {
        Material::factory()->count(PlantOverviewService::NEEDS_ACTION_LIMIT + 5)
            ->create(['stock_quantity' => 1, 'min_stock_level' => 10]);

        $data = $this->needsAction();

        $this->assertSame(PlantOverviewService::NEEDS_ACTION_LIMIT + 5, $data['total']);
        $this->assertCount(PlantOverviewService::NEEDS_ACTION_LIMIT, $data['items']);
    }

    public function test_query_count_does_not_grow_with_what_is_wrong(): void
    {
        $this->actingAs($this->admin);
        $service = app(PlantOverviewService::class);

        $build = function (int $n) {
            foreach (range(1, $n) as $i) {
                $this->escalatableDownLine("Line {$i}");
                $this->lowStock();
                $this->failedInspection();
                MaterialLot::factory()->quarantine()->create();
                $this->overdueAction();
            }
        };

        $count = function () use ($service) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->needsAction($this->admin);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $build(1);
        $service->needsAction($this->admin); // warm the permission cache
        $small = $count();
        $build(9);
        $this->assertSame(50, $service->needsAction($this->admin)['total']);
        $large = $count();

        $this->assertSame($small, $large, 'needsAction() must not issue per-item queries');
    }

    // ── modules ────────────────────────────────────────────────────────

    public function test_materials_module_off_hides_stock_and_lot_rows(): void
    {
        $this->lowStock();
        MaterialLot::factory()->quarantine()->create();
        $this->withoutModule('materials');

        $this->assertSame(['total' => 0, 'items' => []], $this->needsAction());
    }

    public function test_quality_module_off_hides_overdue_actions(): void
    {
        $this->overdueAction();
        $this->withoutModule('quality');

        $this->assertSame(0, $this->needsAction()['total']);
    }

    public function test_maintenance_module_off_hides_failed_inspections(): void
    {
        $this->failedInspection();
        $this->withoutModule('maintenance');

        $this->assertSame(0, $this->needsAction()['total']);
    }

    public function test_quality_module_off_turns_view_ticket_back_into_escalation(): void
    {
        [$line, , $stop, $order] = $this->escalatableDownLine();
        Issue::factory()->create(['work_order_id' => $order->id, 'production_downtime_id' => $stop->id]);
        $this->withoutModule('quality');

        // Issues page unreachable → the existing endpoint answers "Already escalated".
        $this->assertSame('escalate', $this->item('line_down:'.$line->id)['action']['kind']);
    }

    // ── permissions ────────────────────────────────────────────────────

    public function test_user_with_dashboard_only_sees_rows_without_buttons(): void
    {
        $this->escalatableDownLine();
        WorkOrder::factory()->create(['status' => WorkOrder::STATUS_BLOCKED]);
        $this->lowStock();
        $this->failedInspection();
        MaterialLot::factory()->quarantine()->create();
        $this->overdueAction();

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->givePermissionTo(TabRegistry::permission('dashboard'));

        $data = $this->needsAction($operator);

        $this->assertCount(6, $data['items']);
        $this->assertSame([null], array_values(array_unique(array_column($data['items'], 'action'), SORT_REGULAR)));
    }

    public function test_shift_monitor_access_without_escalation_role_gets_the_monitor_link(): void
    {
        [$line, $station] = $this->escalatableDownLine();

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->givePermissionTo(TabRegistry::permission('dashboard'), TabRegistry::permission('shift_monitor'));

        $action = $this->item('line_down:'.$line->id, $operator)['action'];

        $this->assertSame('link', $action['kind']);
        $this->assertSame('/admin/shift-monitor?workstation='.$station->id, $action['href']);
    }

    public function test_supervisor_without_orders_tab_escalates_but_cannot_resolve_blocked_lines(): void
    {
        [$downLine] = $this->escalatableDownLine();
        $blockedLine = Line::factory()->create();
        WorkOrder::factory()->create(['line_id' => $blockedLine->id, 'status' => WorkOrder::STATUS_BLOCKED]);

        Role::findByName('Supervisor')->revokePermissionTo(TabRegistry::permission('orders'));
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $supervisor->givePermissionTo(TabRegistry::permission('dashboard'), TabRegistry::permission('shift_monitor'));

        $this->assertSame('escalate', $this->item('line_down:'.$downLine->id, $supervisor)['action']['kind']);
        $this->assertNull($this->item('line_blocked:'.$blockedLine->id, $supervisor)['action']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    // ── escalation, end to end ─────────────────────────────────────────

    public function test_open_ticket_escalates_the_stop_and_turns_into_view_ticket(): void
    {
        IssueType::factory()->create(['is_active' => true]);
        [$line, , $stop, $order] = $this->escalatableDownLine();

        $href = $this->item('line_down:'.$line->id)['action']['href'];

        $this->actingAs($this->admin)->postJson($href, ['note' => null])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Escalated to maintenance'));

        $issue = Issue::where('production_downtime_id', $stop->id)->sole();
        $this->assertSame($order->id, $issue->work_order_id);

        $this->assertSame(
            ['kind' => 'link', 'label' => 'View ticket', 'href' => '/admin/issues?issue='.$issue->id],
            $this->item('line_down:'.$line->id)['action'],
        );

        // A second click (stale page, second supervisor) files nothing new.
        $this->actingAs($this->admin)->postJson($href, ['note' => null])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Already escalated'));
        $this->assertSame(1, Issue::where('production_downtime_id', $stop->id)->count());
    }

    public function test_escalation_is_still_refused_without_a_running_order(): void
    {
        IssueType::factory()->create(['is_active' => true]);
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '09:00');
        $stop = $this->openStop($line, $station);

        $this->actingAs($this->admin)
            ->postJson(route('admin.shift-monitor.escalate', $stop->id), ['note' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
        $this->assertSame(0, Issue::count());
    }

    public function test_escalation_is_still_refused_for_an_operator(): void
    {
        IssueType::factory()->create(['is_active' => true]);
        [, , $stop] = $this->escalatableDownLine();

        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->givePermissionTo(TabRegistry::permission('shift_monitor'));

        $this->actingAs($operator)
            ->postJson(route('admin.shift-monitor.escalate', $stop->id), ['note' => null])
            ->assertForbidden();
        $this->assertSame(0, Issue::count());
    }

    // ── tenancy ────────────────────────────────────────────────────────

    public function test_needs_action_is_tenant_isolated(): void
    {
        $tenants = [];
        foreach (['A', 'B'] as $name) {
            $tenant = Tenant::create(['name' => "Tenant {$name}"]);
            $admin = User::factory()->create(['tenant_id' => $tenant->id]);
            $admin->assignRole('Admin');

            $line = Line::factory()->create(['name' => "Line of {$name}", 'tenant_id' => $tenant->id]);
            $this->station($line, WorkstationState::FAULT);
            $material = $this->lowStock(['name' => "Steel of {$name}", 'tenant_id' => $tenant->id]);
            MaterialLot::factory()->quarantine()->create(['material_id' => $material->id, 'tenant_id' => $tenant->id]);
            $this->failedInspection(['material_id' => $material->id, 'tenant_id' => $tenant->id]);
            $order = WorkOrder::factory()->create(['line_id' => $line->id, 'tenant_id' => $tenant->id]);
            $this->overdueAction(['work_order_id' => $order->id]);

            $tenants[$name] = compact('admin', 'line', 'material');
        }

        // An issue tied to nothing tenant-scoped can't be attributed: left out.
        $this->overdueAction(['work_order_id' => null]);

        foreach ($tenants as $name => $t) {
            $data = $this->needsAction($t['admin']);
            $this->assertSame(5, $data['total'], "Tenant {$name} total");
            $this->assertCount(5, $data['items']);
            $keys = array_column($data['items'], 'key');
            $this->assertContains('line_down:'.$t['line']->id, $keys);
            $this->assertContains('material_low:'.$t['material']->id, $keys);
            foreach ($data['items'] as $item) {
                $other = $name === 'A' ? 'B' : 'A';
                $this->assertStringNotContainsString("of {$other}", $item['title'].' '.$item['description']);
            }
        }
    }
}
