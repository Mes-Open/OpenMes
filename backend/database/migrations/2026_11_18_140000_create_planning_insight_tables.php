<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production Planner insights.
 *
 * planning_insight_acknowledgements: what a planner did about an insight —
 * acknowledged it, moved an order or brought in a substitute — with who and
 * when, so the drawer can list it under "Resolved today" and undo it. Undo is
 * a soft delete; the key is unique among live rows only, so the same insight
 * can be acknowledged again after an undo.
 *
 * work_order_staffing_snapshots: who staffed an order's lines, and who of them
 * was away, at the moment the order was last planned — what the planner
 * compares against today's staffing to say it changed. One row per order,
 * replaced on every placement; not something a user deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_insight_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->string('insight_key', 191);
            $table->string('insight_type', 40);
            // acknowledge | move | substitute
            $table->string('action', 20);
            $table->string('title', 255);
            $table->text('summary');
            // The facts the insight had when it was dealt with; it reopens when they change.
            $table->string('signature', 64)->nullable();
            // What undo needs: the planner change to revert, the operator to take off a line.
            $table->json('payload')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['created_at']);
            $table->index(['updated_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX planning_insight_acknowledgements_key_unique
             ON planning_insight_acknowledgements (insight_key, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );

        Schema::create('work_order_staffing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 32);
            // line id => person keys assigned to it
            $table->json('roster');
            // date => absent worker ids among those people
            $table->json('absences');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_staffing_snapshots');
        Schema::dropIfExists('planning_insight_acknowledgements');
    }
};
