<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('additional_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('PLN');
            $table->timestamps();
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_costs');
    }
};
