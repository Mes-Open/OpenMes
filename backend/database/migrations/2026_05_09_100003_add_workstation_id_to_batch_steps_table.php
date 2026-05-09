<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->foreignId('workstation_id')->nullable()->after('instruction')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropForeign(['workstation_id']);
            $table->dropColumn('workstation_id');
        });
    }
};
