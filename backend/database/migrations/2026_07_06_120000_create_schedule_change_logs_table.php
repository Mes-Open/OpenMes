<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only log of planner edits: full placement snapshots before and
        // after each write, so any entry can be undone by restoring `before`.
        Schema::create('schedule_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20)->default('reschedule'); // reschedule | undo
            $table->json('before');
            $table->json('after');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_logs');
    }
};
