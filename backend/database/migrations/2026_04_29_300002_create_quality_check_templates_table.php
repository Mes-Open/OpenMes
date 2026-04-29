<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_check_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('min_checks_per_batch')->default(3);
            $table->integer('min_checks_per_day')->nullable();
            $table->integer('samples_per_check')->default(3);
            $table->json('parameters'); // [{name, type: "measurement"|"pass_fail", unit?, min?, max?}]
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_check_templates');
    }
};
