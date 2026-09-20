<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * How pieces move between the steps of a batch (system setting
 * `production_flow_mode`).
 *
 * - `whole_batch` (default, the historical behaviour): a step is available only
 *   once the previous step is DONE/SKIPPED; completing a step passes everything
 *   not scrapped to the next one.
 * - `transfer`: each step keeps a quantity ledger and the next step opens as
 *   soon as some pieces have passed the previous one, so stations overlap. A
 *   step can only be finished once nothing more can arrive and nothing is left
 *   waiting at it.
 */
final class ProductionFlow
{
    public const SETTING_KEY = 'production_flow_mode';

    public const WHOLE_BATCH = 'whole_batch';

    public const TRANSFER = 'transfer';

    public const MODES = [self::WHOLE_BATCH, self::TRANSFER];

    /** Container key of the scoped cache holding the last read mode. */
    private const CACHE_KEY = 'openmes.production_flow_mode';

    /**
     * Seconds a read mode is reused. The cache is a scoped container instance,
     * so Octane drops it after every request and queue workers after every job;
     * the expiry covers long-running commands (the Modbus poller) that never
     * reset the container, so a changed setting reaches them within seconds.
     */
    private const CACHE_SECONDS = 10;

    public static function mode(): string
    {
        $cache = self::cache();
        if ($cache['mode'] !== null && microtime(true) - $cache['at'] < self::CACHE_SECONDS) {
            return $cache['mode'];
        }

        try {
            $raw = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');
        } catch (\Throwable) {
            return self::WHOLE_BATCH; // no database yet (install) — don't cache
        }

        $mode = $raw === null ? null : json_decode($raw, true);
        $mode = in_array($mode, self::MODES, true) ? $mode : self::WHOLE_BATCH;

        $cache['mode'] = $mode;
        $cache['at'] = microtime(true);

        return $mode;
    }

    /** Drop the cached mode, e.g. after the setting was written some other way. */
    public static function forget(): void
    {
        self::cache()['mode'] = null;
    }

    public static function isTransfer(): bool
    {
        return self::mode() === self::TRANSFER;
    }

    public static function set(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Unknown production flow mode: {$mode}");
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($mode), 'updated_at' => now()],
        );

        self::forget();
    }

    private static function cache(): \ArrayObject
    {
        $app = app();
        if (! $app->bound(self::CACHE_KEY)) {
            $app->scoped(self::CACHE_KEY, fn () => new \ArrayObject(['mode' => null, 'at' => 0.0]));
        }

        return $app->make(self::CACHE_KEY);
    }
}
