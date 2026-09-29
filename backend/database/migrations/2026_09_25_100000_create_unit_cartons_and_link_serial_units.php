<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a serialised unit goes after the line: into a carton (a box of
 * several units with one label listing them), onto a pallet, out of the door.
 * Cartons are opened, filled by scanning units in, closed, and put on a
 * pallet; shipping the pallet ships everything on it. A carton is never
 * deleted - it is closed, and an empty one is simply never filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_cartons', function (Blueprint $table) {
            $table->id();
            $table->string('carton_no', 30);
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('open'); // open | closed
            $table->unsignedInteger('qty')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['carton_no', 'tenant_id']);
            $table->index(['status', 'work_order_id']);
            $table->index('pallet_id');
        });

        Schema::table('serial_units', function (Blueprint $table) {
            $table->foreignId('carton_id')->nullable()->after('batch_id')->constrained('unit_cartons')->nullOnDelete();
            $table->foreignId('pallet_id')->nullable()->after('carton_id')->constrained('pallets')->nullOnDelete();
            $table->timestamp('packed_at')->nullable()->after('produced_at');
            $table->timestamp('shipped_at')->nullable()->after('packed_at');

            $table->index('carton_id');
            $table->index('pallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('serial_units', function (Blueprint $table) {
            // Indexes first: SQLite rebuilds the table on a column drop.
            $table->dropIndex(['carton_id']);
            $table->dropIndex(['pallet_id']);
            $table->dropConstrainedForeignId('carton_id');
            $table->dropConstrainedForeignId('pallet_id');
            $table->dropColumn(['packed_at', 'shipped_at']);
        });

        Schema::dropIfExists('unit_cartons');
    }
};
