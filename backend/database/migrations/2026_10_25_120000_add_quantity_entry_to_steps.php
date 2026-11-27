<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the operator logs the pieces leaving a running step (transfer flow):
 * `quantities` — good / scrap counts typed in, with a small +1 beside them —
 * or `piece` — one large +1 per good piece and one per scrapped piece. Set on
 * the template step and mirrored onto the batch step (like
 * `requires_confirmation`), so an order keeps the choice it was released with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->string('quantity_entry', 16)->default('quantities')->after('requires_confirmation');
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->string('quantity_entry', 16)->default('quantities')->after('requires_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropColumn('quantity_entry');
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn('quantity_entry');
        });
    }
};
