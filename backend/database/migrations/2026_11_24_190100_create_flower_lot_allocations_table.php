<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which jasmine batch a flower lot was paid into (付窨), with the seep count
 * and weight. Kept separate from the lot so one lot can serve more than one
 * batch and the history stays visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_lot_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('flower_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();

            $table->decimal('weight_kg', 12, 3);
            $table->unsignedSmallInteger('seep_count')->default(1);

            $table->foreignId('allocated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['flower_lot_id', 'batch_id']);
            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_lot_allocations');
    }
};
