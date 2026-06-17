<?php

namespace Tests\Feature\Seeders;

use App\Models\WorkOrder;
use Database\Seeders\PrintShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The install-time demo dataset must seed cleanly and include a few unassigned
 * work orders (no production line) so the "awaiting scheduling" path is covered.
 */
class PrintShopDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_and_creates_unassigned_orders(): void
    {
        $this->seed(PrintShopDemoSeeder::class);

        foreach (['WO-2026-008', 'WO-2026-009', 'WO-2026-010'] as $orderNo) {
            $wo = WorkOrder::where('order_no', $orderNo)->first();
            $this->assertNotNull($wo, "{$orderNo} should be seeded");
            $this->assertNull($wo->line_id, "{$orderNo} should be unassigned (no line)");
            $this->assertSame(WorkOrder::STATUS_PENDING, $wo->status);
        }

        // The assigned demo orders still seed normally.
        $this->assertNotNull(WorkOrder::where('order_no', 'WO-2026-001')->first()->line_id);
    }
}
