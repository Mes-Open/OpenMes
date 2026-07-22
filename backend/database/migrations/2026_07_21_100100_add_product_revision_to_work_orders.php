<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work orders reference a specific product revision (#180). Nullable so existing
 * and revision-less orders are unchanged; the FK nulls out if a revision is ever
 * hard-deleted, and the immutable revision block lives in process_snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('product_revision_id')
                ->nullable()
                ->after('product_type_id')
                ->constrained('product_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_revision_id');
        });
    }
};
