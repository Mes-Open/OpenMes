<?php

namespace Tests\Unit\Casts;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the days_of_week normalization cast: whatever representation a
 * row is written with, it reads back as canonical ISO weekday integers so
 * Shift::current() and the capacity view agree.
 */
class DaysOfWeekTest extends TestCase
{
    use RefreshDatabase;

    private function shift(array $days): Shift
    {
        return Shift::create([
            'name' => 'Test',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'days_of_week' => $days,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_day_name_strings_are_normalized_to_iso_integers(): void
    {
        $shift = $this->shift(['monday', 'wednesday', 'friday']);

        $this->assertSame([1, 3, 5], $shift->fresh()->days_of_week);
    }

    public function test_integers_are_preserved_and_sorted_unique(): void
    {
        $shift = $this->shift([5, 1, 3, 1]);

        $this->assertSame([1, 3, 5], $shift->fresh()->days_of_week);
    }

    public function test_mixed_and_invalid_entries_are_filtered(): void
    {
        $shift = $this->shift(['Tue', 2, 'garbage', 9, 0]);

        // 'Tue' & 2 → 2, 0 → 7 (Sunday), 'garbage' & 9 dropped.
        $this->assertSame([2, 7], $shift->fresh()->days_of_week);
    }

    public function test_shift_current_resolves_with_legacy_string_days(): void
    {
        // Anchor "now" to a Wednesday noon, when the shift is active.
        Carbon::setTestNow(Carbon::now()->startOfWeek()->addDays(2)->setTime(12, 0));

        $this->shift(['wednesday']); // stored via cast → [3]

        $this->assertNotNull(Shift::current());

        Carbon::setTestNow();
    }
}
