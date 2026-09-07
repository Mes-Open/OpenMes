<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The unified importer (Admin → Import) runs every entity through one history
 * table. `csv_imports` was work-order-only, so it learns which entity a run
 * targeted, the options it ran with, where its file sits while queued, and the
 * per-outcome counters the progress bar reads while the job is still running.
 *
 * `tenant_id` lets the row be live-synced on the tenant's channel; without it
 * every tenant would receive every import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csv_imports', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('entity', 50)->default('work_orders')->after('user_id');
            $table->string('original_filename')->nullable()->after('filename');
            $table->string('file_path')->nullable()->after('original_filename');
            $table->json('options')->nullable()->after('import_strategy');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->unsignedInteger('created_rows')->default(0)->after('processed_rows');
            $table->unsignedInteger('updated_rows')->default(0)->after('created_rows');
            $table->unsignedInteger('skipped_rows')->default(0)->after('updated_rows');
            $table->index(['tenant_id', 'created_at']);
        });

        // Existing runs belong to the tenant of the user who started them. Left
        // NULL, TenantScope would hide the whole history from tenant users.
        DB::table('csv_imports')
            ->whereNull('tenant_id')
            ->whereNotNull('user_id')
            ->update(['tenant_id' => DB::raw('(select tenant_id from users where users.id = csv_imports.user_id)')]);

        Schema::table('csv_import_mappings', function (Blueprint $table) {
            $table->string('entity', 50)->default('work_orders')->after('name');
            $table->index(['entity', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('csv_import_mappings', function (Blueprint $table) {
            $table->dropIndex(['entity', 'user_id']);
            $table->dropColumn('entity');
        });

        Schema::table('csv_imports', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'entity', 'original_filename', 'file_path', 'options',
                'processed_rows', 'created_rows', 'updated_rows', 'skipped_rows',
            ]);
        });
    }
};
