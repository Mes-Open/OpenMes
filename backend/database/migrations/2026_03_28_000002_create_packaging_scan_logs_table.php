<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->string('ean', 100);
            $table->string('product_name', 500)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index('scanned_at');
            $table->index(['work_order_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_scan_logs');
    }
};
