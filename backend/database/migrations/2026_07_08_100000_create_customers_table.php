<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers (master data for work orders). A customer carries a loyalty tier
 * and a few aggregate metrics (payment behaviour, order count, revenue) that
 * feed the automatic work-order priority scoring introduced in phase 2. Both
 * `total_orders`/`total_revenue` are maintained by the app on WO completion
 * (phase 3) and `payment_score` is a manual 0–100 rating for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Optional short business code (reusable after a soft delete via the
            // partial unique index below).
            $table->string('code', 50)->nullable();
            $table->enum('tier', ['bronze', 'silver', 'gold', 'vip'])->default('bronze');
            // Manual 0–100 payment-behaviour rating (auto-calculated in a later
            // phase once invoice/payment history exists).
            $table->unsignedTinyInteger('payment_score')->default(0)
                ->comment('Payment behaviour rating 0–100 (higher = pays reliably)');
            $table->unsignedInteger('total_orders')->default(0)
                ->comment('Completed work-order count, maintained by the app');
            $table->decimal('total_revenue', 12, 2)->default(0)
                ->comment('Accumulated revenue, maintained by the app');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['is_active', 'name']);
        });

        // Code is unique among live (non-deleted) rows only, so it can be reused
        // after a soft delete. NULL codes are allowed and never collide (NULLs
        // are distinct in a unique index). Partial indexes work on PostgreSQL
        // (prod) and SQLite (tests); MySQL is not a target here.
        DB::statement('CREATE UNIQUE INDEX customers_code_unique ON customers (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
