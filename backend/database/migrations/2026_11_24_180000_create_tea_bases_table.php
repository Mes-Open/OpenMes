<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tea base dossier — the identity card a blender and QA read before a base
 * enters a formula. It is deliberately a small master object: quality values
 * are recorded here so the page can draw a fingerprint without guessing from
 * unrelated batch checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tea_bases', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50);
            $table->string('name', 150);

            // Physical material this dossier describes; the available lots are
            // read from material_lots by material_id.
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();

            // Tea identity — origin, cultivar, grade, season and screen size.
            $table->string('origin', 100)->nullable();
            $table->string('variety', 100)->nullable();
            $table->string('grade', 50)->nullable();
            $table->string('season', 50)->nullable();
            $table->string('screen_size', 50)->nullable();
            $table->string('supplier_name', 150)->nullable();

            // Quality fingerprint. The bar in the UI shows the value against
            // this range/limit; statuses stay explicit so a missing test never
            // reads as "passed".
            $table->decimal('moisture_percent', 5, 2)->nullable();
            $table->decimal('moisture_min', 5, 2)->default(5.00);
            $table->decimal('moisture_max', 5, 2)->default(6.50);

            $table->decimal('pesticide_value', 8, 3)->nullable();
            $table->decimal('pesticide_limit', 8, 3)->nullable();
            $table->string('pesticide_status', 20)->default('PENDING');

            $table->decimal('heavy_metal_value', 8, 3)->nullable();
            $table->decimal('heavy_metal_limit', 8, 3)->nullable();
            $table->string('heavy_metal_status', 20)->default('PENDING');

            $table->decimal('sensory_score', 5, 2)->nullable();
            $table->decimal('sensory_standard', 5, 2)->default(80.00);
            $table->string('sensory_status', 20)->default('PENDING');

            // PENDING | AVAILABLE | FROZEN | RETIRED
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('last_inspected_at')->nullable();

            // Freeze is a controlled hold: reason and responsible user are kept
            // on the record, not only in an audit log.
            $table->text('freeze_reason')->nullable();
            $table->foreignId('frozen_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->index(['status', 'code']);
            $table->index(['origin', 'variety']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tea_bases');
    }
};
