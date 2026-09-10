<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in per-template switch (#290): 'batch' (default, unchanged behavior) keeps
 * every unit in a batch progressing through BatchStep together; 'unit' lets
 * individual serialized pieces progress through steps independently via the
 * new unit_steps table (see 2026_09_09_100002_create_unit_steps_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_templates', function (Blueprint $table) {
            $table->string('execution_mode', 10)->default('batch')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('process_templates', function (Blueprint $table) {
            $table->dropColumn('execution_mode');
        });
    }
};
