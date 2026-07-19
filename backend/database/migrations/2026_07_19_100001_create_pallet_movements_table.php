<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger of physical pallet movements (#103): each row records a
     * pallet being relocated from one location to another by a logistics
     * operator, so every move is attributable to a person. Mirrors the
     * stock_movements ledger shape (immutable, no soft deletes).
     */
    public function up(): void
    {
        Schema::create('pallet_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained()->cascadeOnDelete();

            // The logistics operator (Worker) who physically performed the move.
            // Kept even if the worker record is later removed (nullOnDelete) so
            // history isn't silently rewritten.
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();

            // Where the pallet came from / went to. from_location is null for the
            // very first move of a pallet that had no prior location.
            $table->string('from_location', 100)->nullable();
            $table->string('to_location', 100)->nullable();

            $table->timestamp('moved_at');
            $table->text('notes')->nullable();

            // The authenticated account that recorded the move (terminal login),
            // distinct from the operator credited with performing it.
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['pallet_id', 'moved_at']);
            $table->index(['worker_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_movements');
    }
};
