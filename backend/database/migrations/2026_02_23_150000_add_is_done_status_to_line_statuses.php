<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_statuses', function (Blueprint $table) {
            $table->boolean('is_done_status')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('line_statuses', function (Blueprint $table) {
            $table->dropColumn('is_done_status');
        });
    }
};
