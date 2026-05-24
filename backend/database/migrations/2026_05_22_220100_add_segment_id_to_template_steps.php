<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->foreignId('process_segment_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->index('process_segment_id');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            // Drop the FK first so the column can be removed safely on MySQL.
            $table->dropForeign(['process_segment_id']);
            $table->dropIndex(['process_segment_id']);
            $table->dropColumn('process_segment_id');
        });
    }
};
