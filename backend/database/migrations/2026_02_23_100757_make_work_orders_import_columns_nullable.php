<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // process_snapshot is populated when a batch starts, not at import time
            $table->json('process_snapshot')->nullable()->change();

            // line_id is optional — user may import without assigning a line yet
            $table->foreignId('line_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('process_snapshot')->nullable(false)->change();
            $table->foreignId('line_id')->nullable(false)->change();
        });
    }
};
