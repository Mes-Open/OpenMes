<?php

namespace Tests\Feature\Schedule\Insights;

use App\Models\Line;
use App\Models\PlanningInsightAcknowledgement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a planner can do from the insights drawer — acknowledge, move an order
 * to the first shift with enough people, bring in a substitute — and undo.
 *
 * The plant: CNC Turning with Jan, Ewa and Adam; Jan is away on Tuesday and a
 * 3-person job is planned for Tuesday morning. Assembly A has Marta and Olga
 * and nothing planned.
 */
class PlanningInsightActionsTest extends TestCase
{
    use PlansWithStaff;
    use RefreshDatabase;

    private Line $cnc;

    private Line $asm;

    /** @var array<string, User> */
    private array $people;

    private WorkOrder $order;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlant();
        $this->withAbsenceProvider();
    }

    private function shortTuesday(): void
    {
        $this->cnc = $this->plantLine('CNC Turning', 'CNC');
        $this->asm = $this->plantLine('Assembly A', 'ASM');
        $this->people = [
            'jan' => $this->operator('Jan Kowalski', $this->cnc),
            'ewa' => $this->operator('Ewa Lis', $this->cnc),
            'adam' => $this->operator('Adam Nowak', $this->cnc),
            'marta' => $this->operator('Marta Nowak', $this->asm),
            'olga' => $this->operator('Olga Wrona', $this->asm),
        ];
        $this->absent($this->people['jan'], '2026-11-24');
        $this->order = $this->planned($this->cnc, '2026-11-24', 1, operators: 3);
        $this->key = 'short:'.$this->cnc->id.':2026-11-24:1';
    }

    private function act(?User $user, string $action, array $body)
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->postJson("/admin/schedule/insights/{$action}", $this->board() + $body);
    }

    // ── acknowledge ─────────────────────────────────────────────────────────

    public function test_acknowledging_moves_the_insight_to_resolved_today(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();

        $response = $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertOk();

        $this->assertSame([], $response->json('drawer.insights'));
        $this->assertSame(1, $response->json('drawer.summary.resolved'));
        $resolved = $response->json('drawer.resolved.0');
        $this->assertSame($this->key, $resolved['insight_key']);
        $this->assertSame('acknowledge', $resolved['action']);
        $this->assertSame('Admin User', $resolved['by']);
        $this->assertSame('CNC Turning short on Tue 24.11 Morning', $resolved['title']);
        $this->assertDatabaseHas('planning_insight_acknowledgements', [
            'insight_key' => $this->key, 'insight_type' => 'short_shift', 'acknowledged_by_id' => $admin->id,
        ]);
    }

    public function test_an_acknowledged_insight_reopens_when_its_facts_change(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertOk();

        $this->absent($this->people['ewa'], '2026-11-24');

        $insight = $this->insight($this->drawer($admin), 'short_shift');
        $this->assertSame($this->key, $insight['key']);
        $this->assertSame(1, $insight['numbers']['available']);
    }

    public function test_acknowledge_validates_the_key(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();

        $this->act($admin, 'acknowledge', [])->assertUnprocessable()->assertJsonValidationErrors('insight_key');
        $this->act($admin, 'acknowledge', ['insight_key' => 'drop table'])->assertUnprocessable()->assertJsonValidationErrors('insight_key');
        // Well-formed, but no such insight on this board.
        $this->act($admin, 'acknowledge', ['insight_key' => 'short:'.$this->cnc->id.':2026-11-25:1'])->assertUnprocessable()->assertJsonValidationErrors('insight_key');
        // Already dealt with: a conflict, not a validation error.
        $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertOk();
        $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertStatus(409);
    }

    public function test_acknowledge_needs_a_planner(): void
    {
        $this->shortTuesday();

        $this->act(null, 'acknowledge', ['insight_key' => $this->key])->assertUnauthorized();
        $this->act($this->planner('Operator'), 'acknowledge', ['insight_key' => $this->key])->assertForbidden();
        $this->act($this->planner('Supervisor'), 'acknowledge', ['insight_key' => $this->key])->assertOk();
        $this->assertDatabaseCount('planning_insight_acknowledgements', 1);
    }

    // ── move ────────────────────────────────────────────────────────────────

    public function test_move_puts_the_order_on_the_first_shift_with_enough_people(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();

        $response = $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $this->order->id])->assertOk();

        $order = $this->order->fresh();
        $this->assertSame('2026-11-25 06:00', $order->planned_start_at->format('Y-m-d H:i'));
        $this->assertSame(1, $order->shift_number);
        $this->assertSame('2026-11-25', $order->end_date->toDateString());
        $this->assertSame($this->cnc->id, $order->line_id);
        // The badges follow: Tuesday morning is empty, Wednesday morning exactly enough.
        $cells = collect($response->json('staffing.cells'));
        $this->assertSame(0, $cells->first(fn ($c) => $c['line_id'] === $this->cnc->id && $c['date'] === '2026-11-24' && $c['shift'] === 1)['needed']);
        $wed = $cells->first(fn ($c) => $c['line_id'] === $this->cnc->id && $c['date'] === '2026-11-25' && $c['shift'] === 1);
        $this->assertSame([3, 3], [$wed['available'], $wed['needed']]);
        $this->assertSame([], $response->json('drawer.insights'));
        $this->assertSame('move', $response->json('drawer.resolved.0.action'));
        $ack = PlanningInsightAcknowledgement::sole();
        $this->assertSame($this->order->id, $ack->payload['work_order_id']);
        $this->assertNotNull($ack->payload['change_log_id']);
        $this->assertStringContainsString('Wed 25.11 Morning', $ack->summary);
    }

    public function test_move_validates_and_refuses_orders_the_insight_does_not_offer(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $other = $this->planned($this->asm, '2026-11-24', 1);

        $this->act($admin, 'move', ['insight_key' => $this->key])->assertUnprocessable()->assertJsonValidationErrors('work_order_id');
        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('work_order_id');
        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('insight_key');
        $this->assertSame('2026-11-24', $this->order->fresh()->planned_start_at->toDateString());
    }

    public function test_move_needs_a_planner(): void
    {
        $this->shortTuesday();
        $body = ['insight_key' => $this->key, 'work_order_id' => $this->order->id];

        $this->act(null, 'move', $body)->assertUnauthorized();
        $this->act($this->planner('Operator'), 'move', $body)->assertForbidden();
        $this->assertSame('2026-11-24', $this->order->fresh()->planned_start_at->toDateString());
    }

    public function test_move_into_an_occupied_slot_asks_first(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        // Something with an exact time sits on Wednesday evening, after the shifts.
        WorkOrder::factory()->create([
            'line_id' => $this->cnc->id, 'status' => WorkOrder::STATUS_PENDING, 'process_snapshot' => ['steps' => [], 'bom' => []],
            'planned_start_at' => '2026-11-25 22:30:00', 'planned_end_at' => '2026-11-25 23:30:00', 'due_date' => '2026-11-30',
        ]);

        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $this->order->id])
            ->assertStatus(409)->assertJson(['conflict' => true]);
        $this->assertDatabaseCount('planning_insight_acknowledgements', 0);

        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $this->order->id, 'force_conflict' => true])->assertOk();
        $this->assertSame('2026-11-25', $this->order->fresh()->planned_start_at->toDateString());
    }

    // ── substitute ──────────────────────────────────────────────────────────

    public function test_a_substitute_is_assigned_to_the_line_and_the_shift_is_covered(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();

        $response = $this->act($admin, 'substitute', ['insight_key' => $this->key, 'user_id' => $this->people['marta']->id])->assertOk();

        $this->assertTrue($this->cnc->users()->whereKey($this->people['marta']->id)->exists());
        $this->assertTrue($this->asm->users()->whereKey($this->people['marta']->id)->exists(), 'she keeps her own line');
        $tue = collect($response->json('staffing.cells'))->first(fn ($c) => $c['line_id'] === $this->cnc->id && $c['date'] === '2026-11-24' && $c['shift'] === 1);
        $this->assertSame([3, 3], [$tue['available'], $tue['needed']]);
        $this->assertSame([], $response->json('drawer.insights'));
        $this->assertSame('substitute', $response->json('drawer.resolved.0.action'));
        $this->assertSame('Marta Nowak assigned to CNC Turning by Admin User.', $response->json('message'));
    }

    public function test_substitute_refuses_people_who_are_not_suggested(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();

        $this->act($admin, 'substitute', ['insight_key' => $this->key])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        // Already on the line, and away that day.
        $this->act($admin, 'substitute', ['insight_key' => $this->key, 'user_id' => $this->people['jan']->id])->assertUnprocessable();
        // Not an operator.
        $this->act($admin, 'substitute', ['insight_key' => $this->key, 'user_id' => $admin->id])->assertUnprocessable();
        $this->assertFalse($this->cnc->users()->whereKey($admin->id)->exists());
    }

    public function test_a_person_away_that_day_is_not_suggested(): void
    {
        $this->shortTuesday();
        $this->absent($this->people['marta'], '2026-11-24');

        $insight = $this->insight($this->drawer($this->planner()), 'short_shift');

        $ids = array_column(array_filter($insight['actions'], fn ($a) => $a['kind'] === 'substitute'), 'user_id');
        $this->assertSame([$this->people['olga']->id], array_values($ids));
    }

    public function test_substitute_needs_a_planner(): void
    {
        $this->shortTuesday();
        $body = ['insight_key' => $this->key, 'user_id' => $this->people['marta']->id];

        $this->act(null, 'substitute', $body)->assertUnauthorized();
        $this->act($this->planner('Operator'), 'substitute', $body)->assertForbidden();
        $this->assertFalse($this->cnc->users()->whereKey($this->people['marta']->id)->exists());
    }

    // ── undo ────────────────────────────────────────────────────────────────

    private function undo(?User $user, PlanningInsightAcknowledgement $ack)
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->postJson("/admin/schedule/insights/acknowledgements/{$ack->id}/undo", $this->board());
    }

    public function test_undoing_a_move_puts_the_order_back_and_reopens_the_insight(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $this->order->id])->assertOk();
        $ack = PlanningInsightAcknowledgement::sole();

        $response = $this->undo($admin, $ack)->assertOk();

        $this->assertSame('2026-11-24 06:00', $this->order->fresh()->planned_start_at->format('Y-m-d H:i'));
        $this->assertSoftDeleted($ack);
        $this->assertSame($admin->id, $ack->fresh()->deleted_by_id);
        $this->assertSame($this->key, $response->json('drawer.insights.0.key'));
        $this->assertSame(0, $response->json('drawer.summary.resolved'));
    }

    public function test_undoing_a_substitute_takes_them_off_the_line(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $this->act($admin, 'substitute', ['insight_key' => $this->key, 'user_id' => $this->people['marta']->id])->assertOk();

        $this->undo($admin, PlanningInsightAcknowledgement::sole())->assertOk();

        $this->assertFalse($this->cnc->users()->whereKey($this->people['marta']->id)->exists());
        $this->assertTrue($this->asm->users()->whereKey($this->people['marta']->id)->exists());
        $this->assertSoftDeleted(PlanningInsightAcknowledgement::withTrashed()->sole());
    }

    public function test_undoing_a_move_is_refused_once_the_order_moved_again(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $this->act($admin, 'move', ['insight_key' => $this->key, 'work_order_id' => $this->order->id])->assertOk();
        $this->actingAs($admin)->putJson("/admin/schedule/{$this->order->id}", ['planned_start_at' => '2026-11-26T06:00:00', 'end_date' => '2026-11-26', 'planned_end_at' => '2026-11-27T00:00:00'])->assertOk();

        $this->undo($admin, PlanningInsightAcknowledgement::sole())->assertStatus(409);

        $this->assertSame('2026-11-26', $this->order->fresh()->planned_start_at->toDateString());
        $this->assertNotSoftDeleted(PlanningInsightAcknowledgement::sole());
    }

    public function test_undo_needs_a_planner_of_the_same_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Admin A']);
        $adminA->assignRole('Admin');
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $adminB->assignRole('Admin');
        $this->actingAs($adminA);
        $this->shortTuesday();
        $this->act($adminA, 'acknowledge', ['insight_key' => $this->key])->assertOk();
        $ack = PlanningInsightAcknowledgement::sole();
        $this->assertSame($tenantA->id, $ack->tenant_id);

        auth()->logout();
        $this->undo(null, $ack)->assertUnauthorized();
        $this->undo($this->planner('Operator'), $ack)->assertForbidden();
        // Another plant neither sees nor undoes it.
        $this->assertSame([], $this->drawer($adminB)['drawer']['resolved']);
        $this->undo($adminB, $ack)->assertNotFound();
        $this->act($adminB, 'acknowledge', ['insight_key' => $this->key])->assertUnprocessable();
        $this->assertNotSoftDeleted($ack);

        $this->undo($adminA, $ack)->assertOk();
        $this->assertSoftDeleted($ack);
    }

    public function test_an_undone_insight_can_be_acknowledged_again(): void
    {
        $this->shortTuesday();
        $admin = $this->planner();
        $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertOk();
        $this->undo($admin, PlanningInsightAcknowledgement::sole())->assertOk();

        $this->act($admin, 'acknowledge', ['insight_key' => $this->key])->assertOk();

        $this->assertSame(2, PlanningInsightAcknowledgement::withTrashed()->count());
        $this->assertSame(1, PlanningInsightAcknowledgement::count());
    }
}
