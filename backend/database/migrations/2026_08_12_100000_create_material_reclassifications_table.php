<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit anchor for material reclassifications (#99).
 *
 * A CLASS move books two stock_movements on two different materials (no natural
 * join key between them) and a STATUS→quarantine move books zero stock_movements,
 * so the ledger alone can't represent a reclassification. This row correlates the
 * legs (both movements carry source_id = this id) and is the sole audit surface for
 * zero-delta status changes. Ledger-style: not user-deletable, no soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_reclassifications', function (Blueprint $table) {
            $table->id();

            // 'class' = quantity moved between materials; 'status' = lot status change.
            $table->string('type', 20);

            $table->foreignId('source_material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('target_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('source_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();

            // Null for a status-only change.
            $table->decimal('quantity', 14, 4)->nullable();

            // Lot status transition (null for a class move).
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['source_material_id', 'performed_at']);
            $table->index(['source_lot_id', 'performed_at']);
            $table->index(['type', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_reclassifications');
    }
};
