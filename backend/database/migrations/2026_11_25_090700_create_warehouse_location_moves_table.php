<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_location_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->constrained('warehouse_locations')->restrictOnDelete();

            $table->string('item_type', 30);
            $table->unsignedBigInteger('item_id');
            $table->string('item_code', 80)->nullable();
            $table->string('item_name');
            $table->decimal('quantity', 15, 3)->default(0);
            $table->string('unit_of_measure', 16)->nullable();

            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->string('note', 160)->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'moved_at']);
            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_location_moves');
    }
};
