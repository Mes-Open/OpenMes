<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number', 100);

            // Where it came from. companies table already exists in OpenMES;
            // we link as supplier when known. Free-text supplier_lot_ref
            // mirrors the field already used on inspections for raw OCR/scan input.
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('supplier_lot_ref', 100)->nullable();

            $table->decimal('received_qty', 12, 4);
            $table->decimal('available_qty', 12, 4);   // remaining after picks
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamp('received_at');

            // Optional link to the inspection that qualified this lot.
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();

            // available | quarantined | expired | depleted
            $table->string('status', 20)->default('available');

            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['material_id', 'lot_number', 'tenant_id'], 'material_lots_unique_per_material');
            $table->index(['material_id', 'status', 'expiry_date']);
            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};
