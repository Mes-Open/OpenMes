<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('batch_step_id')
                ->constrained()->nullOnDelete();
            $table->string('source', 30)->nullable()->after('material_id');
        });

        // Make work_order_id nullable so non-conformances raised from inbound
        // inspection (and other contexts) don't require a production WO.
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn(['material_id', 'source']);
            $table->foreignId('work_order_id')->nullable(false)->change();
        });
    }
};
