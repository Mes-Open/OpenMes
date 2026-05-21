<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->foreignId('schedule_id')
                ->nullable()
                ->after('workstation_id')
                ->constrained('maintenance_schedules')
                ->nullOnDelete();
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropIndex(['schedule_id']);
            $table->dropColumn('schedule_id');
        });
    }
};
