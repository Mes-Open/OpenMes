<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controlled production-change requests (#182).
 *
 * The point of the whole feature: when production has to stop because the product,
 * process or instructions must change, the change goes through a request that is
 * reviewed and approved BEFORE it touches anything — instead of somebody editing
 * the live work order and losing what the shop floor was actually building.
 *
 * `proposed` holds only the fields the request wants to change; `previous_values` is
 * the matching before-state captured at apply time, so the pair reads as a diff
 * without having to reconstruct history. `impact` is the analysis shown to the approver,
 * frozen as it was at that moment — recomputing it later would answer a different
 * question.
 *
 * Status is the audit trail (DRAFT → SUBMITTED → APPROVED → APPLIED, or REJECTED /
 * CANCELLED), so rows are never deleted and there are no soft deletes: a withdrawn
 * request is CANCELLED and stays readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_change_requests', function (Blueprint $table) {
            $table->id();

            // Human-facing identifier used in history and on the shop floor (CR/2026/0001).
            $table->string('code', 40);

            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            // The stop this change came out of, when it came out of one. Nullable:
            // a change can also be raised on an order that is not running yet.
            $table->foreignId('work_order_stop_id')->nullable();

            $table->string('title');
            $table->text('reason');

            // DRAFT | SUBMITTED | APPROVED | REJECTED | APPLIED | CANCELLED
            $table->string('status', 20)->default('DRAFT');

            // What the request wants to change, and what it replaced. Only keys the
            // request actually touches are present — an absent key means "leave it".
            // `previous_values`, not `previous`: Eloquent's Model already declares a
            // protected $previous, and a column of that name reads as an empty array
            // from inside the model while looking correct from outside.
            $table->json('proposed');
            $table->json('previous_values')->nullable();

            // Impact summary as shown to the approver (quantities, batches, steps,
            // materials, documents, warnings).
            $table->json('impact')->nullable();

            // From when the new configuration applies — see ChangeEffectivePoint.
            $table->string('effective_from', 30)->default('NEXT_BATCH');
            $table->foreignId('effective_from_batch_id')->nullable()->constrained('batches')->nullOnDelete();

            // What to do with what already exists. Free text on purpose: the real
            // dispositions are decided per case and belong in the audit trail, not
            // in an enum somebody has to extend for every plant.
            $table->text('produced_disposition')->nullable();
            $table->text('material_disposition')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('applied_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();

            // The snapshot version this request produced, once applied.
            $table->unsignedInteger('resulting_snapshot_version')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->index(['status']);
        });

        // COALESCE keeps single-tenant installs (tenant_id NULL) covered — a plain
        // unique would treat every NULL tenant as distinct.
        DB::statement(
            'CREATE UNIQUE INDEX work_order_change_requests_code_unique
             ON work_order_change_requests (code, COALESCE(tenant_id, 0))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_change_requests');
    }
};
