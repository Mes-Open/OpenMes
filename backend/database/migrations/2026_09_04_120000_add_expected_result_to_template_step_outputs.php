<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional pass criterion for a typed operator output (#quality-gate). A number
 * output can set expected_min/expected_max (either or both — one-sided bounds
 * are fine); a boolean or select output sets expected_value (boolean: '1'/'0';
 * select: the single passing option string). Null on all three = no gate,
 * current "must be recorded" behaviour is unchanged. Evaluated by
 * App\Services\WorkOrder\OutputGateEvaluator on every recorded value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_step_outputs', function (Blueprint $table) {
            $table->decimal('expected_min', 12, 4)->nullable()->after('options');
            $table->decimal('expected_max', 12, 4)->nullable()->after('expected_min');
            $table->string('expected_value', 255)->nullable()->after('expected_max');
        });
    }

    public function down(): void
    {
        Schema::table('template_step_outputs', function (Blueprint $table) {
            $table->dropColumn(['expected_min', 'expected_max', 'expected_value']);
        });
    }
};
