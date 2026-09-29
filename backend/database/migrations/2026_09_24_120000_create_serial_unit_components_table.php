<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What went into a serialised unit: each component scanned onto it at a
 * station, by whatever identifier the component carries - another serialised
 * unit (a sub-assembly with its own serial), a material lot, or a bare serial
 * on a bought-in part the system has no other record of. Removing a component
 * closes the row (unbound_at) rather than deleting it, so the unit's history
 * still says what was in it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_unit_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_unit_id')->constrained('serial_units')->cascadeOnDelete();
            // The scanned identifier as stored - the one key every component has.
            $table->string('identifier', 100);
            // Resolved links, whichever the identifier turned out to be.
            $table->foreignId('component_serial_unit_id')->nullable()->constrained('serial_units')->nullOnDelete();
            $table->foreignId('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->foreignId('batch_step_id')->nullable()->constrained('batch_steps')->nullOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->foreignId('bound_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bound_at', 6);
            $table->timestamp('unbound_at', 6)->nullable();
            $table->foreignId('unbound_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('unbind_reason', 500)->nullable();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->timestamps();

            $table->index(['serial_unit_id', 'unbound_at']);
            // Recall: which units contain this component.
            $table->index(['identifier', 'tenant_id']);
            $table->index('component_serial_unit_id');
            $table->index('material_lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_unit_components');
    }
};
