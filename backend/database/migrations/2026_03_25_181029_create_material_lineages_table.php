<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lineages', function (Blueprint $table) {
            $table->id();
            $table->string('material_lot_no'); // Lot numbering for raw material
            $table->string('final_unit_no')->nullable(); // Final product SN
            $table->foreignId('workstation_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Operator at time
            $table->foreignId('batch_id')->constrained();
            $table->foreignId('batch_step_id')->constrained();
            $table->string('process_id'); // From snapshot
            $table->jsonb('parameters')->nullable(); // Temperature, Torque, Pressure used for this specific unit
            $table->timestamp('processed_at', 6);
            $table->timestamps();

            $table->index(['material_lot_no', 'final_unit_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lineages');
    }
};
