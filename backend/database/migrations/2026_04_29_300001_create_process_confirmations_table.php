<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('confirmation_type', 20)->default('parameters'); // parameters, drying, custom
            $table->text('notes')->nullable();
            $table->string('value', 100)->nullable(); // e.g. "14" hours for drying
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'confirmation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_confirmations');
    }
};
