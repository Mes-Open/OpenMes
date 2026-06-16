<?php

use App\Models\Batch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the new READY batch-step status on existing data. `batch_steps.status`
 * is a free string column, so there's no schema change — we just promote the
 * already-startable PENDING steps (first step, or one whose predecessor is
 * DONE/SKIPPED) to READY so in-flight batches match the new model. No-op for
 * finished batches (they have no PENDING steps).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('batches') || ! Schema::hasTable('batch_steps')) {
            return;
        }

        Batch::query()->with('steps')->chunkById(200, function ($batches) {
            foreach ($batches as $batch) {
                $batch->promoteReadySteps();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('batch_steps')) {
            return;
        }

        // Revert READY back to PENDING (its prior state).
        \App\Models\BatchStep::query()
            ->where('status', \App\Models\BatchStep::STATUS_READY)
            ->update(['status' => \App\Models\BatchStep::STATUS_PENDING]);
    }
};
