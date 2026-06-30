<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective / preventive actions (CAPA) attached to an Issue (the
 * non-conformance / incident record). An issue can carry many actions; it can
 * only be CLOSED once every action is VERIFIED — enforced in IssueService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // corrective | preventive
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open'); // open | in_progress | done | verified
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['issue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_actions');
    }
};
