<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stop the machine reported itself has a placeholder reason (AUTO-*) but no
 * human judgement behind it. `needs_reason` marks those rows so the shift
 * monitor can surface them as "needs a cause" and a supervisor can classify
 * them; classifying stamps who did it and when.
 *
 * Existing rows keep needs_reason = false: they were entered by hand and their
 * reason is already someone's decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_downtimes', function (Blueprint $table) {
            $table->boolean('needs_reason')->default(false)->after('downtime_reason_id');
            $table->timestamp('classified_at')->nullable()->after('needs_reason');
            $table->foreignId('classified_by_id')->nullable()->after('classified_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['workstation_id', 'needs_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('production_downtimes', function (Blueprint $table) {
            $table->dropIndex(['workstation_id', 'needs_reason']);
            $table->dropConstrainedForeignId('classified_by_id');
            $table->dropColumn(['needs_reason', 'classified_at']);
        });
    }
};
