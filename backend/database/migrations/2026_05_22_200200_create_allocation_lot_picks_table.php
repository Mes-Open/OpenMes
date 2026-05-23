<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_lot_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_allocation_id')->constrained()
                ->cascadeOnDelete();
            $table->foreignId('material_lot_id')->constrained()
                ->restrictOnDelete();
            $table->decimal('picked_qty', 12, 4);

            // fefo | fifo | lifo | manual — what strategy chose this lot
            $table->string('picking_strategy', 20)->default('fefo');

            $table->timestamps();

            $table->unique(['material_allocation_id', 'material_lot_id'], 'allocation_lot_picks_unique');
            $table->index('material_lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_lot_picks');
    }
};
