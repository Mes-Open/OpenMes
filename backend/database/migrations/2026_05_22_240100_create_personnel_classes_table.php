<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 Personnel Class — defines a competency template (job role) that
 * groups required skills with minimum certification levels. Workers may be
 * assigned to a class to make capability checks declarative.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('personnel_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            // Array of skill IDs required by this class.
            $table->json('required_skill_ids')->nullable();
            // Per-skill minimum cert_level — {skill_id: cert_level}.
            $table->json('default_required_cert_level')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_classes');
    }
};
