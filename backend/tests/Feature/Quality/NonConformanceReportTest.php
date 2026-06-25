<?php

namespace Tests\Feature\Quality;

use App\Models\Issue;
use App\Models\IssueAction;
use App\Models\IssueType;
use App\Models\User;
use App\Services\Quality\NonConformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Non-conformance reporting (#11): Pareto by issue type, disposition summary
 * and overdue-action count — exposed via the API endpoint, the admin web page
 * and the shared report service.
 */
class NonConformanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_pareto_groups_by_type_with_cumulative_percentage(): void
    {
        $typeA = IssueType::factory()->create(['name' => 'Crack']);
        $typeB = IssueType::factory()->create(['name' => 'Scratch']);
        Issue::factory()->count(3)->create(['issue_type_id' => $typeA->id, 'non_conforming_qty' => 2]);
        Issue::factory()->count(1)->create(['issue_type_id' => $typeB->id, 'non_conforming_qty' => 5]);

        $pareto = app(NonConformanceReportService::class)->pareto(now()->subDay(), now()->addDay());

        $this->assertSame(4, $pareto['total_count']);
        $this->assertEqualsWithDelta(11.0, $pareto['total_nc_qty'], 0.001);
        // Sorted desc by count: Crack (3) first, then Scratch (1).
        $this->assertSame('Crack', $pareto['types'][0]['name']);
        $this->assertSame(3, $pareto['types'][0]['count']);
        $this->assertEqualsWithDelta(75.0, $pareto['types'][0]['pct'], 0.001);
        $this->assertEqualsWithDelta(75.0, $pareto['types'][0]['cumulative_pct'], 0.001);
        $this->assertEqualsWithDelta(100.0, $pareto['types'][1]['cumulative_pct'], 0.001);
    }

    public function test_disposition_summary_counts_every_bucket(): void
    {
        Issue::factory()->count(2)->scrap()->create();
        Issue::factory()->rework()->create();

        $summary = app(NonConformanceReportService::class)->dispositionSummary();

        $this->assertSame(2, $summary['scrap']);
        $this->assertSame(1, $summary['rework']);
        $this->assertSame(0, $summary['return_to_supplier']);
        $this->assertArrayHasKey('use_as_is', $summary);
    }

    public function test_overdue_actions_count_only_counts_outstanding_past_due(): void
    {
        $issue = Issue::factory()->create();
        IssueAction::factory()->overdue()->create(['issue_id' => $issue->id]);
        IssueAction::factory()->overdue()->create(['issue_id' => $issue->id]);
        // Future due date — not overdue.
        IssueAction::factory()->create(['issue_id' => $issue->id, 'due_date' => now()->addWeek()->toDateString()]);
        // Past due but already verified — not outstanding.
        IssueAction::factory()->verified()->create(['issue_id' => $issue->id, 'due_date' => now()->subWeek()->toDateString()]);

        $this->assertSame(2, app(NonConformanceReportService::class)->overdueActionsCount());
    }

    public function test_api_pareto_endpoint_returns_data(): void
    {
        $type = IssueType::factory()->create(['name' => 'Crack']);
        Issue::factory()->count(2)->create(['issue_type_id' => $type->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/reports/non-conformance-pareto')
            ->assertOk()
            ->assertJsonPath('data.pareto.total_count', 2)
            ->assertJsonStructure(['data' => ['pareto', 'disposition_summary', 'overdue_actions', 'period']]);
    }

    public function test_api_pareto_endpoint_is_forbidden_for_operator(): void
    {
        $this->actingAs($this->operator, 'sanctum')
            ->getJson('/api/v1/reports/non-conformance-pareto')
            ->assertForbidden();
    }

    public function test_admin_can_open_the_web_report_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/non-conformance-reports')
            ->assertOk();
    }

    public function test_operator_cannot_open_the_web_report_page(): void
    {
        $this->actingAs($this->operator)
            ->get('/admin/non-conformance-reports')
            ->assertForbidden();
    }
}
