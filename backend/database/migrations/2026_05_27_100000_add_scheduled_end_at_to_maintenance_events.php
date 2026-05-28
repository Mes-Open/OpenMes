<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_events', function (Blueprint $table) {
            $table->dropColumn('scheduled_end_at');
        });
    }
};
