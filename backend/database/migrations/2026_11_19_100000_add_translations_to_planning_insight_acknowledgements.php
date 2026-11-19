<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Resolved today" in the viewer's language: the title and summary of an
 * acknowledgement are kept as a translation key with its parameters
 * ({"key": …, "params": {…}, "count": …}) and rendered when read. Rows
 * written before keep only their stored text, which is shown as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_insight_acknowledgements', function (Blueprint $table) {
            $table->json('title_translation')->nullable()->after('title');
            $table->json('summary_translation')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('planning_insight_acknowledgements', function (Blueprint $table) {
            $table->dropColumn(['title_translation', 'summary_translation']);
        });
    }
};
