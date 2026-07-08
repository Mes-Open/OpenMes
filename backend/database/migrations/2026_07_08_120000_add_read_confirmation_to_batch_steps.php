<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the template step's `requires_confirmation` flag onto the runtime
 * batch step (like `instruction`, `is_optional`, ...), so an operator step
 * knows whether the critical instructions must be read-acknowledged before it
 * can be completed. The `confirmed_at` / `confirmed_by` columns that record the
 * acknowledgement already exist (2026_04_29_300006_add_confirmation_fields_to_steps_tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->boolean('requires_confirmation')
                ->default(false)
                ->after('instruction')
                ->comment('Operator must acknowledge reading the instructions before completing this step.');
        });
    }

    public function down(): void
    {
        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropColumn('requires_confirmation');
        });
    }
};
