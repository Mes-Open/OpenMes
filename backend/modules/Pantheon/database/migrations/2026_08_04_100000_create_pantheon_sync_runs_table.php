<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of Pantheon sync runs, owned by the module.
 *
 * A nightly integration is judged by what it could not apply, so each run keeps
 * its counts and its row errors. Without this the only record is a log file, which
 * nobody opens until a customer asks why a product is missing.
 *
 * Module-owned table: it is created by the normal `php artisan migrate` because the
 * provider registers this directory, and it is dropped again on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pantheon_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('sync', 40);                  // products, stock-documents, …
            // Which tenant this run belonged to. Nullable: a single-tenant install
            // has no tenant id, and the command runs once with a null context.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable(); // null = still running or crashed
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();           // the per-row reports, verbatim
            $table->text('failure')->nullable();          // set when the whole run threw
            $table->timestamps();

            $table->index(['sync', 'started_at']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pantheon_sync_runs');
    }
};
