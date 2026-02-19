<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->foreignId('workstation_type_id')->nullable()->after('line_id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\WorkstationType::class);
            $table->dropColumn('workstation_type_id');
        });
    }
};
