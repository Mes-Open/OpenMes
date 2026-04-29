<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_check_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_check_id')->constrained()->cascadeOnDelete();
            $table->integer('sample_number');
            $table->string('parameter_name', 100);
            $table->string('parameter_type', 20); // measurement, pass_fail
            $table->decimal('value_numeric', 12, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->boolean('is_passed')->nullable();

            $table->index('quality_check_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_check_samples');
    }
};
