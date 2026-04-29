<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_check_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at');
            $table->decimal('production_quantity', 10, 2)->nullable();
            $table->boolean('all_passed')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_checks');
    }
};
