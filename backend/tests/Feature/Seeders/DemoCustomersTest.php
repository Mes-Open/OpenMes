<?php

namespace Tests\Feature\Seeders;

use App\Enums\Tier;
use App\Models\Customer;
use App\Models\WorkOrder;
use Database\Seeders\AirFilterDemoSeeder;
use Database\Seeders\BakeryDemoSeeder;
use Database\Seeders\MachineShopDemoSeeder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who the demo work is for.
 *
 * No seeder created a customer, so the Customers page came up empty and every
 * order showed a blank one. That also left priority scoring inert: it reads the
 * customer's tier and payment score, and there was neither.
 */
class DemoCustomersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<int, class-string>> */
    public static function seeders(): array
    {
        return [
            'print shop' => [PrintShopDemoSeeder::class],
            'machine shop' => [MachineShopDemoSeeder::class],
            'bakery' => [BakeryDemoSeeder::class],
            'air filter' => [AirFilterDemoSeeder::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_the_demo_has_customers(string $seeder): void
    {
        $this->seed($seeder);

        $this->assertGreaterThanOrEqual(4, Customer::count(), 'The customers page would look bare.');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_every_order_belongs_to_someone(string $seeder): void
    {
        $this->seed($seeder);

        $this->assertSame(
            0,
            WorkOrder::whereNull('customer_id')->count(),
            'Some orders still show a blank customer.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_tiers_and_payment_scores_vary(string $seeder): void
    {
        $this->seed($seeder);

        // Priority scoring ranks on these two. A demo where everyone is Gold
        // and pays on time makes every order score the same, which is exactly
        // the case the feature exists to distinguish.
        $this->assertGreaterThan(1, Customer::distinct()->count('tier'));
        $this->assertGreaterThan(1, Customer::distinct()->count('payment_score'));

        $tiers = Customer::pluck('tier');
        foreach ($tiers as $tier) {
            $this->assertInstanceOf(Tier::class, $tier);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('seeders')]
    public function test_an_order_keeps_its_customer_across_reseeds(string $seeder): void
    {
        $this->seed($seeder);

        $order = WorkOrder::orderBy('order_no')->firstOrFail();
        $before = $order->customer_id;

        $this->seed($seeder);

        // Assignment is by position, not at random, so re-seeding must not
        // reshuffle who owns what.
        $this->assertSame($before, $order->fresh()->customer_id);
        $this->assertSame(Customer::count(), Customer::distinct()->count('code'));
    }
}
