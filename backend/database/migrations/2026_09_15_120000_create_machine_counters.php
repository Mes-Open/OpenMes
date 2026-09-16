<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal source state and retained audit history; neither has a delete endpoint.
        Schema::create('machine_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('machine_tag_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('topic_mapping_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('workstation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('batch_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mode')->default('cumulative');
            $table->string('kind')->default('good');
            $table->string('source_fingerprint')->nullable();
            $table->decimal('last_raw', 18, 2)->nullable();
            $table->timestamp('last_read_at', 6)->nullable();
            $table->timestamp('configured_at', 6)->nullable();
            $table->boolean('is_simulated')->default(false);
            $table->boolean('reset_required')->default(false);
            $table->timestamps();
        });
        Schema::create('machine_counter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_counter_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_id', 160)->nullable();
            $table->json('payload')->nullable();
            $table->decimal('raw_value', 18, 2)->nullable();
            $table->decimal('delta', 18, 2)->default(0);
            $table->decimal('applied_qty', 12, 2)->default(0);
            $table->string('status');
            $table->timestamp('observed_at', 6);
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['machine_counter_id', 'event_id']);
            $table->index(['machine_counter_id', 'id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('machine_counters')->exists() || \Illuminate\Support\Facades\DB::table('machine_counter_readings')->exists()) {
            throw new \RuntimeException('Machine counter history exists. Use a forward migration; rollback would destroy baselines and deduplication history.');
        }
        Schema::dropIfExists('machine_counter_readings');
        Schema::dropIfExists('machine_counters');
    }
};
