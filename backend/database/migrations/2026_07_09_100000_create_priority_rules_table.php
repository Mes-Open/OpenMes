<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable rules for the automatic work-order priority scoring. Each active
 * rule inspects one source field (see App\Enums\PriorityRuleSource), applies a
 * comparison (App\Enums\PriorityCondition) and, when it matches, adds its
 * (possibly negative) points to the order's total score. The summed score is
 * mapped to a 1–5 priority via the configurable bands (App\Support\PriorityBandRegistry).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('field_source', 50);
            $table->string('condition_type', 20);
            // Compared value(s). Stored as strings and cast per source at scoring
            // time (numeric for most sources, the tier string for customer.tier).
            $table->string('condition_value', 100)->nullable();
            $table->string('condition_value_max', 100)->nullable()
                ->comment('Upper bound for the "between" condition');
            $table->integer('points')->default(0)
                ->comment('Points added when the rule matches (may be negative)');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_rules');
    }
};
