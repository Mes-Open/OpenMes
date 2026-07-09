<?php

namespace Tests\Feature;

use App\Enums\Tier;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\TierPromotionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_order_updates_customer_totals(): void
    {
        $customer = Customer::factory()->create(['total_orders' => 0, 'total_revenue' => 0]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'unit_price' => 25,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 10]);

        $customer->refresh();
        $this->assertSame(1, $customer->total_orders);
        $this->assertSame('250.00', (string) $customer->total_revenue); // 10 × 25
        $this->assertTrue($wo->fresh()->customer_totals_counted);
    }

    public function test_order_inserted_already_done_is_counted(): void
    {
        // Historical/imported orders created straight as DONE must still accrue.
        $customer = Customer::factory()->create(['total_orders' => 0, 'total_revenue' => 0]);
        WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'unit_price' => 20,
            'status' => WorkOrder::STATUS_DONE,
            'produced_qty' => 4,
        ]);

        $customer->refresh();
        $this->assertSame(1, $customer->total_orders);
        $this->assertSame('80.00', (string) $customer->total_revenue); // 4 × 20
    }

    public function test_completion_is_counted_only_once(): void
    {
        $customer = Customer::factory()->create(['total_orders' => 0, 'total_revenue' => 0]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'unit_price' => 100,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 5]);
        // Reopen then complete again — must not double-count.
        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $wo->update(['status' => WorkOrder::STATUS_DONE]);

        $customer->refresh();
        $this->assertSame(1, $customer->total_orders);
        $this->assertSame('500.00', (string) $customer->total_revenue);
    }

    public function test_order_without_customer_completes_cleanly(): void
    {
        $wo = WorkOrder::factory()->create([
            'customer_id' => null,
            'unit_price' => 50,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 3]);

        $this->assertSame(WorkOrder::STATUS_DONE, $wo->fresh()->status);
    }

    public function test_customer_is_promoted_after_enough_orders(): void
    {
        // Default thresholds: ≥5 → Silver.
        $customer = Customer::factory()->create(['tier' => Tier::Bronze, 'total_orders' => 4]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'unit_price' => 0,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 1]);

        $this->assertSame(5, $customer->fresh()->total_orders);
        $this->assertSame(Tier::Silver, $customer->fresh()->tier);
    }

    public function test_promotion_never_demotes(): void
    {
        // A VIP with few orders must not be knocked down when a new order lands.
        $customer = Customer::factory()->create(['tier' => Tier::Vip, 'total_orders' => 1]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 1]);

        $this->assertSame(Tier::Vip, $customer->fresh()->tier);
    }

    public function test_tier_thresholds_resolve_correctly(): void
    {
        $this->assertSame(Tier::Bronze, TierPromotionRegistry::tierForOrders(4));
        $this->assertSame(Tier::Silver, TierPromotionRegistry::tierForOrders(5));
        $this->assertSame(Tier::Gold, TierPromotionRegistry::tierForOrders(20));
        $this->assertSame(Tier::Vip, TierPromotionRegistry::tierForOrders(50));
    }

    public function test_admin_complete_endpoint_accrues_revenue(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $customer = Customer::factory()->create(['total_orders' => 0, 'total_revenue' => 0]);
        $wo = WorkOrder::factory()->create([
            'customer_id' => $customer->id,
            'unit_price' => 12.5,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($admin)
            ->post("/admin/work-orders/{$wo->id}/complete", ['produced_qty' => 8])
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame(1, $customer->total_orders);
        $this->assertSame('100.00', (string) $customer->total_revenue); // 8 × 12.5
    }
}
