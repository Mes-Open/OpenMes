<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persistent audit trail for self-update runs.
     *
     * Each `UpdateApplier::run()` invocation creates exactly one row that is
     * transitioned through `queued → completed | failed | rolled_back`, so the
     * admin can see who applied which version, when, and how long it took —
     * independent of laravel.log retention.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_updates')) {
            return;
        }

        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_version', 50)->nullable();
            $table->string('to_version', 50);
            // queued | completed | failed | rolled_back
            $table->string('state', 20)->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('files_copied')->nullable();
            $table->text('error')->nullable();
            $table->boolean('composer_install_ran')->default(false);
            $table->boolean('checksum_verified')->default(false);
            $table->timestamps();

            $table->index('state');
            $table->index('started_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
