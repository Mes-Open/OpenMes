<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What a module needs on the operator's station screen.
 *
 * Two seams and no new mechanism: a display region on the station page, and the
 * rule filter the user and worker forms already use, so a module's own key
 * survives `validated()` on starting and completing a step.
 *
 * Nothing else was needed — StepStarted and StepCompleted are already
 * dispatched from the model observer, inside BatchService's transaction and on
 * every path that moves a step, so a module hears about the work itself without
 * core growing another hook for it.
 */
class OperatorStationSeamTest extends TestCase
{
    use RefreshDatabase;

    private const STATION = 'display.operator.workstation.actor';

    private const RULES = 'validation.operator.step';

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->line = Line::factory()->create();
    }

    private function operator(): User
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($this->line->id);

        return $operator;
    }

    private function actingAtStation(): User
    {
        $operator = $this->operator();
        $this->actingAs($operator)->withSession(['selected_line_id' => $this->line->id]);

        return $operator;
    }

    /** @return list<array<string, mixed>> */
    private function contributionsOn(\Illuminate\Testing\TestResponse $response): array
    {
        // Not via assertInertia's dot paths: a hook point's name contains dots,
        // so every segment would be read as another level of nesting.
        return $response->viewData('page')['props']['hooks'][self::STATION] ?? [];
    }

    private function pendingStep(): BatchStep
    {
        $workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);

        return BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'status' => BatchStep::STATUS_PENDING,
        ]);
    }

    public function test_a_community_install_is_sent_no_contributions(): void
    {
        $this->actingAtStation();

        $response = $this->get(route('operator.workstation'))->assertOk();

        $this->assertSame([], $response->viewData('page')['props']['hooks']);
    }

    public function test_a_module_can_put_something_on_the_station_screen(): void
    {
        app(HookRegistry::class)->listen(self::STATION, [
            'title' => 'Nobody identified',
            'body' => 'Present a badge to the reader.',
        ]);

        $this->actingAtStation();

        $response = $this->get(route('operator.workstation'))->assertOk();

        $this->assertSame([[
            'title' => 'Nobody identified',
            'body' => 'Present a badge to the reader.',
        ]], $this->contributionsOn($response));
    }

    public function test_the_contribution_is_told_which_line_and_station(): void
    {
        $seen = null;
        app(HookRegistry::class)->listen(self::STATION, function ($ctx) use (&$seen) {
            $seen = $ctx;

            return ['title' => 'Present'];
        });

        $this->actingAtStation();
        $this->get(route('operator.workstation'))->assertOk();

        $this->assertSame($this->line->id, $seen['line']->id);
        $this->assertArrayHasKey('workstation', $seen);
    }

    public function test_a_contribution_cannot_smuggle_anything_past_the_whitelist(): void
    {
        app(HookRegistry::class)->listen(self::STATION, [
            'title' => 'Present',
            'onClick' => 'alert(1)',
            'dangerouslySetInnerHTML' => ['__html' => '<script>alert(1)</script>'],
        ]);

        $this->actingAtStation();

        $response = $this->get(route('operator.workstation'))->assertOk();

        $this->assertSame([['title' => 'Present']], $this->contributionsOn($response));
    }

    public function test_a_module_key_is_dropped_from_a_step_start_without_a_rule(): void
    {
        // The starting point: no rule, no key. Nothing objects; the value simply
        // never reaches the controller.
        $step = $this->pendingStep();
        $this->actingAtStation();

        $this->post("/operator/batch-step/{$step->id}/start", ['module_example_code' => 'ABC123'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_module_rule_reaches_the_step_start(): void
    {
        app(FilterRegistry::class)->addFilter(
            self::RULES,
            fn ($rules) => $rules + ['module_example_code' => ['required', 'string', 'max:8']],
        );

        $step = $this->pendingStep();
        $this->actingAtStation();

        // Enforced, which is only possible if the key reached the validator.
        $this->post("/operator/batch-step/{$step->id}/start", ['module_example_code' => str_repeat('X', 40)])
            ->assertSessionHasErrors('module_example_code');
    }

    public function test_the_rule_filter_is_told_which_operation_it_is(): void
    {
        $actions = [];
        app(FilterRegistry::class)->addFilter(self::RULES, function ($rules, $context) use (&$actions) {
            $actions[] = $context['action'];

            return $rules;
        });

        $step = $this->pendingStep();
        $this->actingAtStation();

        $this->post("/operator/batch-step/{$step->id}/start", []);
        $this->post("/operator/batch-step/{$step->id}/complete", []);

        $this->assertSame(['start', 'complete'], $actions);
    }

    public function test_the_rule_filter_carries_the_step(): void
    {
        $context = null;
        app(FilterRegistry::class)->addFilter(self::RULES, function ($rules, $ctx) use (&$context) {
            $context = $ctx;

            return $rules;
        });

        $step = $this->pendingStep();
        $this->actingAtStation();
        $this->post("/operator/batch-step/{$step->id}/start", []);

        $this->assertSame($step->id, $context['step']?->id);
    }

    public function test_core_rules_still_apply_with_a_module_present(): void
    {
        app(FilterRegistry::class)->addFilter(
            self::RULES,
            fn ($rules) => $rules + ['module_example_code' => ['nullable', 'string']],
        );

        $step = $this->pendingStep();
        $this->actingAtStation();

        $this->post("/operator/batch-step/{$step->id}/start", [
            'picks' => [['material_id' => 999999, 'lots' => [['material_lot_id' => 1, 'picked_qty' => 1]]]],
        ])->assertSessionHasErrors('picks.0.material_id');
    }
}
