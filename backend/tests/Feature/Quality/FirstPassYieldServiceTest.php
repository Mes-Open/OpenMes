<?php

namespace Tests\Feature\Quality;

use App\Models\Batch;
use App\Models\Line;
use App\Models\QualityCheck;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Services\Quality\FirstPassYieldService;
use App\Support\ModuleRegistry;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * First-pass yield: batches whose first-ever quality check passed, over
 * batches first checked in the window.
 */
class FirstPassYieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private FirstPassYieldService $fpy;

    private Carbon $from;

    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fpy = app(FirstPassYieldService::class);
        $this->from = Carbon::parse('2026-10-20 06:00:00');
        $this->to = Carbon::parse('2026-10-20 14:00:00');
    }

    private function batch(?Line $line = null): Batch
    {
        $order = WorkOrder::factory()->create($line ? ['line_id' => $line->id] : []);

        return Batch::factory()->create(['work_order_id' => $order->id]);
    }

    private function check(Batch $batch, string $at, bool $passed): QualityCheck
    {
        return QualityCheck::factory()->create([
            'batch_id' => $batch->id,
            'checked_at' => Carbon::parse($at),
            'all_passed' => $passed,
        ]);
    }

    public function test_all_first_checks_passing_is_100(): void
    {
        foreach (['07:00', '09:00', '11:00'] as $time) {
            $this->check($this->batch(), "2026-10-20 {$time}", true);
        }

        $this->assertSame(100.0, $this->fpy->forWindow($this->from, $this->to));
    }

    public function test_batch_fixed_on_the_second_check_is_not_first_pass(): void
    {
        foreach (['07:00', '08:00', '09:00'] as $time) {
            $this->check($this->batch(), "2026-10-20 {$time}", true);
        }
        $reworked = $this->batch();
        $this->check($reworked, '2026-10-20 10:00', false);
        $this->check($reworked, '2026-10-20 10:30', true);

        $this->assertSame(75.0, $this->fpy->forWindow($this->from, $this->to));
        $this->assertSame(['first_pass' => 3, 'inspected' => 4], $this->fpy->breakdownForWindow($this->from, $this->to));
    }

    public function test_rounds_to_one_decimal(): void
    {
        $this->check($this->batch(), '2026-10-20 07:00', true);
        $this->check($this->batch(), '2026-10-20 08:00', true);
        $this->check($this->batch(), '2026-10-20 09:00', false);

        $this->assertSame(66.7, $this->fpy->forWindow($this->from, $this->to));
    }

    public function test_periodic_rechecks_of_a_passed_batch_do_not_change_the_result(): void
    {
        $batch = $this->batch();
        $this->check($batch, '2026-10-20 07:00', true);
        $this->check($batch, '2026-10-20 09:00', true);
        $this->check($batch, '2026-10-20 11:00', false);
        $this->check($this->batch(), '2026-10-20 08:00', false);

        $this->assertSame(['first_pass' => 1, 'inspected' => 2], $this->fpy->breakdownForWindow($this->from, $this->to));
        $this->assertSame(50.0, $this->fpy->forWindow($this->from, $this->to));
    }

    public function test_batch_first_checked_in_an_earlier_window_is_not_counted_again(): void
    {
        $carried = $this->batch();
        $this->check($carried, '2026-10-20 05:00', false); // previous shift
        $this->check($carried, '2026-10-20 07:00', true);  // re-check in this shift
        $this->check($this->batch(), '2026-10-20 08:00', true);

        $this->assertSame(['first_pass' => 1, 'inspected' => 1], $this->fpy->breakdownForWindow($this->from, $this->to));
        $this->assertSame(100.0, $this->fpy->forWindow($this->from, $this->to));
    }

    public function test_no_checks_is_null(): void
    {
        $this->check($this->batch(), '2026-10-20 15:00', true); // outside the window

        $this->assertNull($this->fpy->forWindow($this->from, $this->to));
    }

    public function test_quality_module_disabled_is_null(): void
    {
        $this->check($this->batch(), '2026-10-20 07:00', true);
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['quality'])));

        $this->assertNull($this->fpy->forWindow($this->from, $this->to));
        $this->assertNull($this->fpy->breakdownForWindow($this->from, $this->to));
    }

    public function test_filters_by_line(): void
    {
        $one = Line::factory()->create();
        $two = Line::factory()->create();

        $this->check($this->batch($one), '2026-10-20 07:00', true);
        $this->check($this->batch($one), '2026-10-20 08:00', true);
        $this->check($this->batch($two), '2026-10-20 07:30', false);

        $this->assertSame(100.0, $this->fpy->forWindow($this->from, $this->to, $one->id));
        $this->assertSame(0.0, $this->fpy->forWindow($this->from, $this->to, $two->id));
        $this->assertSame(66.7, $this->fpy->forWindow($this->from, $this->to));
        $this->assertNull($this->fpy->forWindow($this->from, $this->to, Line::factory()->create()->id));
    }

    public function test_overnight_shift_window(): void
    {
        $shift = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_active' => true]);
        $window = ShiftWindow::occurrence($shift, Carbon::parse('2026-10-21 02:00:00'));

        $this->check($this->batch(), '2026-10-20 23:30', true);   // in, before midnight
        $this->check($this->batch(), '2026-10-21 02:00', false);  // in, after midnight
        $this->check($this->batch(), '2026-10-21 06:00', false);  // end is exclusive
        $this->check($this->batch(), '2026-10-20 21:59', false);  // before the start

        $this->assertSame(['first_pass' => 1, 'inspected' => 2], $this->fpy->breakdownForWindow($window->start, $window->end));
        $this->assertSame(50.0, $this->fpy->forWindow($window->start, $window->end));
    }

    public function test_many_checks_per_batch_only_the_first_decides(): void
    {
        // Passed first, then many routine re-checks, some failing.
        $passed = $this->batch();
        $this->check($passed, '2026-10-20 06:30', true);
        foreach (['07:00', '08:00', '09:00', '10:00', '11:00', '12:00'] as $i => $time) {
            $this->check($passed, "2026-10-20 {$time}", $i % 2 === 0);
        }

        // Failed first, then passed on every re-check.
        $failed = $this->batch();
        $this->check($failed, '2026-10-20 07:15', false);
        foreach (['07:45', '08:15', '08:45', '09:15'] as $time) {
            $this->check($failed, "2026-10-20 {$time}", true);
        }

        // Two checks at the same instant: the first recorded (lowest id) decides.
        $tied = $this->batch();
        $this->check($tied, '2026-10-20 10:00', true);
        $this->check($tied, '2026-10-20 10:00', false);
        $this->check($tied, '2026-10-20 11:00', false);

        // First checked last shift, re-checked many times in this one: not counted.
        $carried = $this->batch();
        $this->check($carried, '2026-10-20 05:30', true);
        foreach (['06:15', '07:30', '13:59'] as $time) {
            $this->check($carried, "2026-10-20 {$time}", false);
        }

        // Re-checks after the window do not reach back into it.
        $this->check($failed, '2026-10-20 14:00', true);

        $this->assertSame(['first_pass' => 2, 'inspected' => 3], $this->fpy->breakdownForWindow($this->from, $this->to));
        $this->assertSame(66.7, $this->fpy->forWindow($this->from, $this->to));
    }

    public function test_checks_of_deleted_batches_are_ignored(): void
    {
        $deleted = $this->batch();
        $this->check($deleted, '2026-10-20 07:00', false);
        $deleted->delete();
        $this->check($this->batch(), '2026-10-20 08:00', true);

        $this->assertSame(100.0, $this->fpy->forWindow($this->from, $this->to));
    }
}
