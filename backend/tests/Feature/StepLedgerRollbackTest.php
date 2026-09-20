<?php

namespace Tests\Feature;

use App\Models\BatchStep;
use App\Models\ScrapEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StepLedgerRollbackTest extends TestCase
{
    use RefreshDatabase;

    public static function incompatibleData(): array
    {
        return [
            'unclassified scrap' => [0, 0, true, false],
            'classified step scrap' => [0, 1, false, false],
            'fractional good output' => [1.25, 0, false, false],
            'counter exceeds PostgreSQL integer' => [2147483648, 0, false, false],
            'soft-deleted audit quantity' => [1.25, 1, false, true],
        ];
    }

    #[DataProvider('incompatibleData')]
    public function test_unsafe_downgrade_preserves_schema_and_production_facts(float $passed, float $scrap, bool $unclassified, bool $deleted): void
    {
        $step = BatchStep::factory()->create(['passed_qty' => $passed, 'scrap_qty' => $scrap]);
        if ($unclassified) {
            ScrapEntry::factory()->create([
                'work_order_id' => $step->batch->work_order_id,
                'batch_step_id' => $step->id,
                'scrap_reason_id' => null,
                'quantity' => 1,
            ]);
        }
        if ($deleted) {
            $step->delete();
        }
        $migration = require base_path('database/migrations/2026_09_15_100000_add_flow_ledger_to_batch_steps.php');
        try {
            $migration->down();
            $this->fail('A downgrade that would lose production data was allowed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No schema changes were made', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('batch_steps', 'scrap_qty'));
        $stored = BatchStep::withTrashed()->findOrFail($step->id);
        $this->assertEquals($passed, $stored->passed_qty);
        $this->assertEquals($scrap, $stored->scrap_qty);
        // NOT NULL was not partially applied either; unclassified scrap remains writable.
        $entry = ScrapEntry::factory()->create([
            'work_order_id' => $step->batch->work_order_id,
            'scrap_reason_id' => null,
            'quantity' => 1,
        ]);
        $this->assertNull($entry->fresh()->scrap_reason_id);
    }

    public function test_compatible_data_can_roll_back_and_migrate_forward_again(): void
    {
        $step = BatchStep::factory()->create(['status' => BatchStep::STATUS_IN_PROGRESS, 'passed_qty' => 2, 'scrap_qty' => 0]);
        $migration = require base_path('database/migrations/2026_09_15_100000_add_flow_ledger_to_batch_steps.php');
        $migration->down();
        try {
            $this->assertFalse(Schema::hasColumn('batch_steps', 'scrap_qty'));
            $this->assertEquals(2, BatchStep::findOrFail($step->id)->passed_qty);
        } finally {
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('batch_steps', 'scrap_qty'));
        $this->assertEquals(2, $step->fresh()->passed_qty);
    }
}
