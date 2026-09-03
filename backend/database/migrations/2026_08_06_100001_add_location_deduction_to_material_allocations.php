<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-location deduction bookkeeping on a material allocation.
 *
 * Consumption is recorded more than once for the same allocation — an operator books
 * an actual quantity, corrects it, and batch completion finalises whatever is left —
 * so the location balance has to move by the DIFFERENCE each time. `location_deducted_qty`
 * is how much of this allocation has already been taken off its location; the
 * deduction is always the new total minus this.
 *
 * `consumption_warehouse_id` freezes WHICH location it came off. Resolving it again
 * later could answer differently (a lot gets moved, a line is re-pointed at another
 * store), and a correction must always go back to the location the material actually
 * left — otherwise a correction credits a location that never gave anything up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->foreignId('consumption_warehouse_id')->nullable()->after('work_order_id')
                ->constrained('warehouses')->nullOnDelete()
                ->comment('Location this allocation is consumed from');

            $table->decimal('location_deducted_qty', 12, 4)->default(0)->after('consumed_qty')
                ->comment('How much has already been taken off the location balance');
        });
    }

    public function down(): void
    {
        Schema::table('material_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumption_warehouse_id');
            $table->dropColumn('location_deducted_qty');
        });
    }
};
