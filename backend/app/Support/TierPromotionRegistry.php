<?php

namespace App\Support;

use App\Enums\Tier;
use Illuminate\Support\Facades\DB;

/**
 * Automatic customer tier promotion by completed-order count. Thresholds are
 * the minimum number of completed orders to reach each tier and are persisted
 * in `system_settings.tier_promotion_thresholds` (mirroring ModuleRegistry).
 * Promotion is upgrade-only — a customer is never demoted here.
 */
class TierPromotionRegistry
{
    public const SETTING_KEY = 'tier_promotion_thresholds';

    public const DEFAULT_THRESHOLDS = ['silver' => 5, 'gold' => 20, 'vip' => 50];

    /** @return array{silver:int,gold:int,vip:int} */
    public static function thresholds(): array
    {
        try {
            $row = DB::table('system_settings')->where('key', self::SETTING_KEY)->first();
        } catch (\Throwable) {
            return self::DEFAULT_THRESHOLDS;
        }

        $vals = $row ? json_decode($row->value, true) : null;
        if (! is_array($vals)) {
            return self::DEFAULT_THRESHOLDS;
        }

        return [
            'silver' => (int) ($vals['silver'] ?? self::DEFAULT_THRESHOLDS['silver']),
            'gold' => (int) ($vals['gold'] ?? self::DEFAULT_THRESHOLDS['gold']),
            'vip' => (int) ($vals['vip'] ?? self::DEFAULT_THRESHOLDS['vip']),
        ];
    }

    /** The highest tier a customer with this many completed orders qualifies for. */
    public static function tierForOrders(int $orders): Tier
    {
        $t = self::thresholds();

        return match (true) {
            $orders >= $t['vip'] => Tier::Vip,
            $orders >= $t['gold'] => Tier::Gold,
            $orders >= $t['silver'] => Tier::Silver,
            default => Tier::Bronze,
        };
    }
}
