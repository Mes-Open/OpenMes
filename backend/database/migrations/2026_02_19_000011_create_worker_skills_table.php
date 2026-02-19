<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worker_skills', function (Blueprint $table) {
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('level')->default(1)->comment('1=Basic, 2=Intermediate, 3=Expert');
            $table->primary(['worker_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_skills');
    }
};
