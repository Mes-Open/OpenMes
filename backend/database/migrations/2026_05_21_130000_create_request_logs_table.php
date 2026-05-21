<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);                  // GET/POST/PUT/PATCH/DELETE
            $table->string('path', 500);                   // /admin/work-orders/123/edit
            $table->string('route_name', 200)->nullable(); // admin.work-orders.edit (named route)
            $table->unsignedSmallInteger('status');        // 200/302/404/500
            $table->unsignedInteger('duration_ms');        // request handling time
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('sampled')->default(false);    // true for sampled GETs
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — request logs are immutable, audit-style data.

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index(['route_name', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
