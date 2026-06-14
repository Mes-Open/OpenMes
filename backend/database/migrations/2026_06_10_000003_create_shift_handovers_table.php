<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->timestamp('shift_start');
            $table->timestamp('shift_end');

            // Balance figures captured at confirmation time.
            $table->unsignedInteger('produced_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->unsignedInteger('good_qty')->default(0);
            $table->unsignedInteger('packed_qty')->default(0);
            $table->unsignedInteger('wip_open_pallets_qty')->default(0);
            $table->unsignedInteger('wip_unpacked_qty')->default(0);
            $table->unsignedInteger('shipped_qty')->default(0);

            $table->json('discrepancies')->nullable()->comment('Named differences flagged at handover');
            $table->json('breakdown')->nullable()->comment('Full snapshot detail for audit');
            $table->text('notes')->nullable();

            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at');

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['line_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
