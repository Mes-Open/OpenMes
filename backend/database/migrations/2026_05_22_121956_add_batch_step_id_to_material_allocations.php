<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            // Which step actually consumed (or will consume) this material.
            // Nullable for legacy rows and for BOM items with consumed_at=start
            // that aren't tied to a particular step.
            $table->foreignId('batch_step_id')->nullable()->after('batch_id')
                ->constrained()->nullOnDelete();
            $table->index('batch_step_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->dropForeign(['batch_step_id']);
            $table->dropColumn('batch_step_id');
        });
    }
};
