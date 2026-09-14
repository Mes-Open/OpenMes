<?php

namespace Tests\Unit\Models;

use App\Models\Batch;
use App\Models\BatchStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch::currentStep() answers the same from the loaded `steps` relation as from
 * the database — and without a query, so the operator queue / workstation filter
 * doesn't run two queries per batch.
 */
class BatchCurrentStepTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, string> $statuses step_number => status */
    private function batchWith(array $statuses): Batch
    {
        $batch = Batch::factory()->create();
        foreach ($statuses as $number => $status) {
            BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => $number, 'status' => $status]);
        }

        return $batch->fresh();
    }

    /** @return array<string, array{0: array<int, string>, 1: int|null}> */
    public static function cases(): array
    {
        $done = BatchStep::STATUS_DONE;
        $pending = BatchStep::STATUS_PENDING;
        $ready = BatchStep::STATUS_READY;
        $running = BatchStep::STATUS_IN_PROGRESS;

        return [
            'in progress wins over an earlier ready step' => [[1 => $ready, 2 => $running, 3 => $pending], 2],
            'lowest in-progress step when several run' => [[1 => $done, 3 => $running, 2 => $running], 2],
            // steps() orders by step_number before the READY-first CASE, so the
            // lower step wins; the loaded path must give the same answer.
            'lowest actionable step, ready or pending' => [[1 => $pending, 2 => $ready], 1],
            'lowest pending step when nothing is ready' => [[1 => $done, 3 => $pending, 2 => $pending], 2],
            'none when every step is finished' => [[1 => $done, 2 => BatchStep::STATUS_SKIPPED], null],
        ];
    }

    /**
     * @dataProvider cases
     *
     * @param  array<int, string>  $statuses
     */
    public function test_loaded_relation_matches_the_query(array $statuses, ?int $expectedStep): void
    {
        $fromQuery = $this->batchWith($statuses)->currentStep();
        $loaded = Batch::with('steps')->find($fromQuery?->batch_id ?? Batch::latest('id')->value('id'));

        $fromRelation = $loaded->currentStep();

        $this->assertSame($expectedStep, $fromQuery?->step_number);
        $this->assertSame($fromQuery?->id, $fromRelation?->id);
    }

    public function test_loaded_relation_runs_no_query(): void
    {
        $batch = $this->batchWith([1 => BatchStep::STATUS_DONE, 2 => BatchStep::STATUS_READY]);
        $batch->load('steps');

        DB::enableQueryLog();
        $step = $batch->currentStep();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $step->step_number);
        $this->assertSame(0, $queries);
    }
}
