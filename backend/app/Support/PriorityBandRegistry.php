<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Score→priority band mapping (#priority scoring). Four ascending upper bounds
 * map a summed work-order score to a 1–5 priority: score ≤ bands[0] → 1,
 * ≤ bands[1] → 2, ≤ bands[2] → 3, ≤ bands[3] → 4, otherwise 5.
 *
 * Persisted system-wide in `system_settings.priority_bands` (a JSON array),
 * mirroring App\Support\ModuleRegistry. A missing/invalid value falls back to
 * the sensible default, so a fresh or mid-install database is unaffected.
 */
class PriorityBandRegistry
{
    public const SETTING_KEY = 'priority_bands';

    /** Default upper bounds: ≤20→P1, ≤40→P2, ≤60→P3, ≤80→P4, >80→P5. */
    public const DEFAULT_BANDS = [20, 40, 60, 80];

    /** @return array{0:int,1:int,2:int,3:int} */
    public static function bands(): array
    {
        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->first();
        } catch (\Throwable) {
            return self::DEFAULT_BANDS;
        }

        if (! $row) {
            return self::DEFAULT_BANDS;
        }

        $vals = json_decode($row->value, true);
        if (! is_array($vals) || count($vals) !== 4) {
            return self::DEFAULT_BANDS;
        }

        return array_map('intval', array_values($vals));
    }

    /** Map a summed score to a 1–5 priority using the configured bands. */
    public static function priorityForScore(int $score): int
    {
        foreach (self::bands() as $i => $upper) {
            if ($score <= $upper) {
                return $i + 1;
            }
        }

        return 5;
    }

    /**
     * Persist the band thresholds. Values are coerced to ints and must be
     * strictly ascending; an invalid set falls back to the current/default.
     *
     * @param  array<int, int|string>  $bands
     */
    public static function save(array $bands): void
    {
        $clean = array_map('intval', array_values($bands));

        if (! self::isValid($clean)) {
            $clean = self::bands();
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($clean), 'updated_at' => now()],
        );
    }

    /** Exactly four strictly-ascending thresholds. */
    public static function isValid(array $bands): bool
    {
        if (count($bands) !== 4) {
            return false;
        }

        for ($i = 1; $i < 4; $i++) {
            if ((int) $bands[$i] <= (int) $bands[$i - 1]) {
                return false;
            }
        }

        return true;
    }
}
