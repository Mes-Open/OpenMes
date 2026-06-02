<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrap_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrap_reason_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->foreignId('batch_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index('work_order_id');
            $table->index('scrap_reason_id');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_entries');
    }
};
