<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();

            $table->string('criterion_name', 150);
            $table->string('criterion_type', 30); // visual | measurement | functional | pass_fail
            $table->boolean('required')->default(true);
            $table->string('unit', 30)->nullable();
            $table->decimal('spec_min', 14, 4)->nullable();
            $table->decimal('spec_max', 14, 4)->nullable();

            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->text('value_text')->nullable();

            $table->boolean('is_passed')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
