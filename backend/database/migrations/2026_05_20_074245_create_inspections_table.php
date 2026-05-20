<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();

            $table->string('lot_number', 100);
            $table->string('supplier_lot_ref', 100)->nullable();
            $table->decimal('quantity_received', 12, 3)->nullable();

            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // pending | pass | fail | conditional_pass
            $table->string('status', 20)->default('pending');

            $table->text('notes')->nullable();

            // When the inspection fails, the auto-created non-conformance issue
            // is linked here so the inspector can navigate to it.
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index('lot_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
