<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default order-count thresholds for automatic customer tier promotion:
 * ≥5 → Silver, ≥20 → Gold, ≥50 → VIP (below 5 stays Bronze). Editable later;
 * see App\Support\TierPromotionRegistry.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'tier_promotion_thresholds'],
            [
                'value' => json_encode(['silver' => 5, 'gold' => 20, 'vip' => 50]),
                'description' => 'Completed-order counts that promote a customer to each tier.',
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'tier_promotion_thresholds')->delete();
    }
};
