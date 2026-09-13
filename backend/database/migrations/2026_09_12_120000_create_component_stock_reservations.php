<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->json('component_specification')->nullable();
            $table->string('component_status')->default('released');
        });
        Schema::table('work_order_components', function (Blueprint $table) {
            $table->decimal('stock_qty', 16, 4)->default(0);
        });
        Schema::create('component_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_component_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_stock_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->string('status')->default('held');
            $table->timestamp('needed_at')->nullable();
            $table->foreignId('stock_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['warehouse_stock_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_stock_reservations');
        Schema::table('work_order_components', fn (Blueprint $table) => $table->dropColumn('stock_qty'));
        Schema::table('warehouse_stocks', fn (Blueprint $table) => $table->dropColumn(['component_specification', 'component_status']));
    }
};
