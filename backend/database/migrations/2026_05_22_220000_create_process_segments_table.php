<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('process_segments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Operation classification
            $table->enum('segment_type', [
                'production',   // value-adding production step
                'inspection',   // quality check
                'maintenance',  // maintenance op
                'setup',        // changeover / setup
                'cleaning',     // cleaning
                'transport',    // material movement
                'other',
            ])->default('production');

            // Default execution parameters
            $table->foreignId('workstation_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->unsignedSmallInteger('required_operators')->default(1);

            // Optional standard instruction (reusable text)
            $table->text('standard_instruction')->nullable();

            // Required skills (JSON array of skill_ids that operator must have)
            $table->json('required_skill_ids')->nullable();

            // Standard parameters (JSON, np. temperature, pressure, sample size)
            $table->json('parameters')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->index(['segment_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_segments');
    }
};
