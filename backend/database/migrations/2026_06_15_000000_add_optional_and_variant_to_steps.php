<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional / variant steps in process templates.
 *
 * - is_optional: the step may be skipped at run time.
 * - variant_group: steps sharing a non-null group are mutually-exclusive
 *   alternatives; exactly one is executed, the siblings are auto-skipped.
 * - is_default_variant: which step in the group is pre-selected for this
 *   template (a template is per product type, so this is the product's default).
 *
 * batch_steps mirror the runtime-relevant flags (+ a skip reason) so execution
 * doesn't have to re-read the immutable process snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('requires_confirmation');
            $table->string('variant_group', 50)->nullable()->after('is_optional');
            $table->boolean('is_default_variant')->default(false)->after('variant_group');
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('status');
            $table->string('variant_group', 50)->nullable()->after('is_optional');
            $table->string('skip_reason', 255)->nullable()->after('variant_group');
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'variant_group', 'is_default_variant']);
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'variant_group', 'skip_reason']);
        });
    }
};
