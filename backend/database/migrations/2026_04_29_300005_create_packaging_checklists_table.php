<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('udi_readable')->default(false);
            $table->boolean('packaging_condition')->default(false);
            $table->boolean('labels_readable')->default(false);
            $table->boolean('label_matches_product')->default(false);
            $table->boolean('all_passed')->storedAs('udi_readable AND packaging_condition AND labels_readable AND label_matches_product');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_checklists');
    }
};
