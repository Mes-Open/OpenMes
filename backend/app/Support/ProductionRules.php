<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Plant-wide production rules an admin switches in Settings → System → Production.
 *
 * The stored setting is the source of truth. The `openmmes.*` config value
 * (env) is only the fallback for a database without the row — the switches on
 * the settings page used to be saved but never read, because these rules were
 * taken from env alone.
 */
final class ProductionRules
{
    public const ALLOW_OVERPRODUCTION = 'allow_overproduction';

    public const FORCE_SEQUENTIAL_STEPS = 'force_sequential_steps';

    /** May the batches of a work order add up to more than its planned quantity? */
    public static function allowOverproduction(): bool
    {
        return self::flag(self::ALLOW_OVERPRODUCTION, (bool) config('openmmes.allow_overproduction', false));
    }

    /** Must a step wait for the previous one before it can start? */
    public static function forceSequentialSteps(): bool
    {
        return self::flag(self::FORCE_SEQUENTIAL_STEPS, (bool) config('openmmes.force_sequential_steps', true));
    }

    private static function flag(string $key, bool $default): bool
    {
        try {
            $raw = DB::table('system_settings')->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $default; // no database yet (install)
        }

        if ($raw === null) {
            return $default;
        }

        return (bool) (json_decode($raw, true) ?? $default);
    }
}
