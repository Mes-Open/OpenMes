<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The plant's quality targets (Settings → System → Production targets): the
 * highest scrap rate and the lowest first-pass yield a shift may run at.
 *
 * The one place these values are read, so the dashboard and any report agree
 * on them. Stored as JSON floats in `system_settings` like every other setting;
 * a missing or unreadable row reads as the default rather than as zero, so a
 * fresh install never shows every shift as off target.
 *
 * The OEE target is not stored here — it comes from each line's planning
 * efficiency ({@see \App\Services\Dashboard\ProductionTargets}).
 */
class ProductionTargetSettings
{
    public const SCRAP_KEY = 'target_max_scrap_rate_pct';

    public const FPY_KEY = 'target_min_fpy_pct';

    public const DEFAULT_MAX_SCRAP = 2.0;

    public const DEFAULT_MIN_FPY = 95.0;

    public static function maxScrapRate(): float
    {
        return self::read(self::SCRAP_KEY, self::DEFAULT_MAX_SCRAP);
    }

    public static function minFirstPassYield(): float
    {
        return self::read(self::FPY_KEY, self::DEFAULT_MIN_FPY);
    }

    public static function save(float $maxScrapRate, float $minFirstPassYield): void
    {
        foreach ([self::SCRAP_KEY => $maxScrapRate, self::FPY_KEY => $minFirstPassYield] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode(round($value, 1)), 'updated_at' => now()],
            );
        }
    }

    private static function read(string $key, float $default): float
    {
        $raw = DB::table('system_settings')->where('key', $key)->value('value');
        $value = $raw === null ? null : json_decode($raw, true);

        return is_numeric($value) && $value >= 0 && $value <= 100 ? (float) $value : $default;
    }
}
