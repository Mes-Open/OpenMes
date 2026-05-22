<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link Worker → Personnel Class (nullable for backward compatibility).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('personnel_class_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
            $table->index('personnel_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropIndex(['personnel_class_id']);
            $table->dropForeign(['personnel_class_id']);
            $table->dropColumn('personnel_class_id');
        });
    }
};
