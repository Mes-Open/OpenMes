<?php

namespace Tests\Feature;

use App\Enums\PriorityCondition as C;
use App\Enums\PriorityRuleSource as S;
use App\Enums\Tier;
use App\Models\Customer;
use App\Models\PriorityRule;
use App\Models\WorkOrder;
use App\Support\PriorityBandRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityScoringTest extends TestCase
{
    use RefreshDatabase;

    /** Create the example rule set from the feature spec. */
    private function seedRules(): void
    {
        $rules = [
            [S::CustomerTier, C::Equals, 'gold', 30, null],
            [S::CustomerPaymentScore, C::GreaterThan, '80', 20, null],
            [S::CustomerPaymentScore, C::LessThan, '50', -10, null],
            [S::CustomerTotalOrders, C::GreaterThan, '10', 15, null],
            [S::WoDueDate, C::LessThan, '24', 50, null],
            [S::WoDueDate, C::LessThan, '72', 25, null],
            [S::WoPlannedQty, C::GreaterThan, '499', 20, null],
        ];

        foreach ($rules as [$source, $condition, $value, $points, $max]) {
            PriorityRule::factory()->rule($source, $condition, $value, $points, $max)->create();
        }
    }

    public function test_gold_repeat_customer_scores_urgent(): void
    {
        $this->seedRules();
        $customer = Customer::factory()->create([
            'tier' => Tier::Gold, 'payment_score' => 92, 'total_orders' => 45,
        ]);

        // Gold +30, payer>80 +20, repeat>10 +15, due<72 +25, qty>499 +20 = 110.
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'planned_qty' => 500,
            'due_date' => now()->addHours(48),
        ]);

        $this->assertSame(110, $wo->fresh()->priority_score);
        $this->assertSame(5, $wo->fresh()->priority);
    }

    public function test_bronze_late_payer_scores_lowest(): void
    {
        $this->seedRules();
        $customer = Customer::factory()->create([
            'tier' => Tier::Bronze, 'payment_score' => 40, 'total_orders' => 2,
        ]);

        // Only "late payer (<50)" matches → -10 → priority 1.
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'planned_qty' => 100,
            'due_date' => now()->addDays(5),
        ]);

        $this->assertSame(-10, $wo->fresh()->priority_score);
        $this->assertSame(1, $wo->fresh()->priority);
    }

    public function test_scoring_is_a_noop_without_active_rules(): void
    {
        // No rules at all → manual priority is preserved, score stays 0.
        $wo = WorkOrder::factory()->create(['priority' => 7]);

        $this->assertSame(7, $wo->fresh()->priority);
        $this->assertSame(0, $wo->fresh()->priority_score);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        PriorityRule::factory()->inactive()->rule(S::CustomerTier, C::Equals, 'gold', 30)->create();
        $customer = Customer::factory()->create(['tier' => Tier::Gold]);

        $wo = WorkOrder::factory()->create(['customer_id' => $customer->id, 'priority' => 4]);

        // The only rule is inactive → treated as "no rules" → untouched.
        $this->assertSame(0, $wo->fresh()->priority_score);
        $this->assertSame(4, $wo->fresh()->priority);
    }

    public function test_customer_without_relation_skips_customer_rules(): void
    {
        PriorityRule::factory()->rule(S::CustomerTier, C::Equals, 'gold', 30)->create();
        PriorityRule::factory()->rule(S::WoPlannedQty, C::GreaterThan, '10', 5)->create();

        $wo = WorkOrder::factory()->create(['customer_id' => null, 'planned_qty' => 100]);

        // Customer rule can't match (no customer); only the qty rule applies.
        $this->assertSame(5, $wo->fresh()->priority_score);
    }

    public function test_changing_customer_tier_rescores_active_work_orders(): void
    {
        PriorityRule::factory()->rule(S::CustomerTier, C::Equals, 'gold', 30)->create();
        $customer = Customer::factory()->create(['tier' => Tier::Bronze]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $this->assertSame(0, $wo->fresh()->priority_score);

        $customer->update(['tier' => Tier::Gold]);

        $this->assertSame(30, $wo->fresh()->priority_score);
    }

    public function test_score_resets_when_all_rules_are_removed(): void
    {
        $rule = PriorityRule::factory()->rule(S::CustomerTier, C::Equals, 'gold', 30)->create();
        $customer = Customer::factory()->create(['tier' => Tier::Gold]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        $this->assertSame(30, $wo->fresh()->priority_score);

        // Remove every rule, then recalc — the stale score must clear to 0.
        $rule->delete();
        $this->artisan('priority:recalculate')->assertSuccessful();

        $this->assertSame(0, $wo->fresh()->priority_score);
    }

    public function test_band_mapping_is_configurable(): void
    {
        $this->assertSame(2, PriorityBandRegistry::priorityForScore(30)); // default: ≤40 → P2

        PriorityBandRegistry::save([10, 20, 30, 40]);

        $this->assertSame(3, PriorityBandRegistry::priorityForScore(30)); // now ≤30 → P3
        $this->assertSame(5, PriorityBandRegistry::priorityForScore(41)); // above top band
    }

    public function test_recalculate_command_rescoring(): void
    {
        $customer = Customer::factory()->create(['tier' => Tier::Gold]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_PENDING,
        ]);
        // No rules yet → score 0.
        $this->assertSame(0, $wo->fresh()->priority_score);

        PriorityRule::factory()->rule(S::CustomerTier, C::Equals, 'gold', 30)->create();
        $this->artisan('priority:recalculate')->assertSuccessful();

        $this->assertSame(30, $wo->fresh()->priority_score);
    }
}
