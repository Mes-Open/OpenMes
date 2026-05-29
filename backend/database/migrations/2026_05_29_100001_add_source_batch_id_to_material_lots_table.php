<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the batch-output → material-lot link that was previously a
 * hand-rolled `extra_data.source_batch_id` convention. When a batch produces a
 * semi-finished material lot, this FK records which batch made it — enabling a
 * reliable backward trace from a finished lot to its ingredient lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->foreignId('source_batch_id')
                ->nullable()
                ->after('inspection_id')
                ->constrained('batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_batch_id');
        });
    }
};
