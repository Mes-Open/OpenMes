<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default score→priority band thresholds (upper bounds). A score ≤ 20 → P1,
 * ≤ 40 → P2, ≤ 60 → P3, ≤ 80 → P4, otherwise P5. Editable on the Priority
 * Settings page; see App\Support\PriorityBandRegistry.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'priority_bands'],
            [
                'value' => json_encode([20, 40, 60, 80]),
                'description' => 'Score upper bounds mapping a work-order score to priority 1–5.',
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'priority_bands')->delete();
    }
};
