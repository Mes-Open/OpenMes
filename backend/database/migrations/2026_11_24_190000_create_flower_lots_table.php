<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flower lots are time-window stock, not ordinary inventory.
 *
 * The four stage timestamps are the page: arrival → curing → usable → paid
 * (付窨). `usable_until` is the hard cutoff for allocation; a lot past it can
 * only be scrapped or downgraded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_lots', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50);
            $table->string('variety', 100);
            $table->string('origin', 100)->nullable();
            $table->string('supplier_name', 150)->nullable();

            $table->timestamp('picked_at')->nullable();
            $table->timestamp('arrived_at');
            $table->timestamp('usable_from')->nullable();
            $table->timestamp('usable_until');

            $table->decimal('weight_kg', 12, 3);
            $table->decimal('opening_rate', 5, 2)->nullable();
            $table->decimal('curing_temperature', 5, 2)->nullable();
            $table->string('storage_location', 100)->nullable();

            // ARRIVED | CURING | AVAILABLE | ALLOCATED | SCRAPPED | DOWNGRADED
            // EXPIRED is computed from usable_until, never stored, so the
            // cutoff stays truthful even if nobody visits the page.
            $table->string('status', 20)->default('ARRIVED');

            $table->text('notes')->nullable();

            $table->text('disposition_reason')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('disposed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
            $table->index(['status', 'usable_until']);
            $table->index(['arrived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_lots');
    }
};
