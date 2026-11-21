<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Physical condition of a pallet (damaged wrap, crushed cartons), recorded
     * by the warehouse on receipt and on moves. Separate from quality_status,
     * which QC derives from the pallet's checks. The pallet row keeps the
     * current state; pallet_condition_events is the append-only trail.
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('condition', 20)->default('good')
                ->comment('Physical state: good | damaged (independent of quality_status)');
            $table->text('condition_note')->nullable()
                ->comment('What was found, or how it was put right, at the last condition record');
            $table->foreignId('condition_recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('condition_recorded_at')->nullable();
        });

        Schema::create('pallet_condition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained()->cascadeOnDelete();
            $table->string('condition', 20)->comment('good | damaged');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->string('source', 20)->comment('receipt | move | manual');
            // The move the condition was recorded with, when there was one.
            $table->foreignId('pallet_movement_id')->nullable()->constrained('pallet_movements')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['pallet_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_condition_events');

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condition_recorded_by_id');
            $table->dropColumn(['condition', 'condition_note', 'condition_recorded_at']);
        });
    }
};
