<?php

namespace Tests\Feature;

use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrap_reason_and_entry_can_be_created(): void
    {
        $reason = ScrapReason::factory()->create([
            'code' => 'TEST-1',
            'category' => ScrapReason::CATEGORY_MACHINE,
        ]);

        $entry = ScrapEntry::factory()->create([
            'scrap_reason_id' => $reason->id,
            'quantity' => 12.5,
        ]);

        $this->assertDatabaseHas('scrap_reasons', ['code' => 'TEST-1', 'category' => 'machine']);
        $this->assertDatabaseHas('scrap_entries', ['scrap_reason_id' => $reason->id]);
        $this->assertSame('12.50', (string) $entry->quantity);
        $this->assertTrue($reason->scrapEntries()->whereKey($entry->id)->exists());
    }

    public function test_work_order_total_scrap_and_quality_accessors(): void
    {
        $wo = WorkOrder::factory()->create(['produced_qty' => 100]);
        $reason = ScrapReason::factory()->create();

        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $reason->id, 'quantity' => 10]);
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $reason->id, 'quantity' => 15]);

        $this->assertEqualsWithDelta(25.0, $wo->totalScrapQty(), 0.001);
        // good = 100 - 25 = 75 → 75%
        $this->assertEqualsWithDelta(75.0, $wo->qualityPct(), 0.001);
    }

    public function test_quality_pct_is_null_when_nothing_produced(): void
    {
        $wo = WorkOrder::factory()->create(['produced_qty' => 0]);

        $this->assertNull($wo->qualityPct());
    }

    public function test_quality_pct_clamps_to_zero_when_scrap_exceeds_production(): void
    {
        $wo = WorkOrder::factory()->create(['produced_qty' => 10]);
        $reason = ScrapReason::factory()->create();
        ScrapEntry::factory()->create(['work_order_id' => $wo->id, 'scrap_reason_id' => $reason->id, 'quantity' => 25]);

        $this->assertSame(0.0, $wo->qualityPct());
    }

    public function test_default_scrap_reasons_are_seeded(): void
    {
        $this->seed(\Database\Seeders\ScrapReasonsSeeder::class);

        $this->assertDatabaseCount('scrap_reasons', 5);
        $this->assertDatabaseHas('scrap_reasons', ['code' => 'MACH-FAIL', 'category' => 'machine']);
    }
}
