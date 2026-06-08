<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 Material Sublot — a logical / physical subdivision of a parent lot.
 *
 * Used when an arriving lot is split into smaller units for traceable
 * consumption (e.g. a 1000 kg drum split into 10 × 100 kg bags). Sublots
 * always belong to exactly one parent lot and inherit its supplier/expiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_sublots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_lot_id')->constrained('material_lots')->cascadeOnDelete();

            // Identifier unique within the parent lot — typically a short suffix (A, 01, BAG-1).
            $table->string('sublot_number', 100);

            $table->decimal('quantity', 14, 4);
            $table->string('unit_of_measure', 20);

            // Sublot-level state — independent of parent lot status so a sublot
            // can be 'reserved' for a batch while the parent is still 'released'.
            $table->string('status', 20)->default('available');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['parent_lot_id', 'sublot_number']);
            $table->index(['parent_lot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_sublots');
    }
};
