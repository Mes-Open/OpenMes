<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the SN label station offers at a bench (null = everything): the first
 * bench only starts units on their PSN, the labelling bench only binds the
 * ready serial label, component benches only scan parts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->json('unit_label_actions')->nullable()->after('operator_screens');
        });
    }

    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn('unit_label_actions');
        });
    }
};
