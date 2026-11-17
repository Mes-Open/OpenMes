<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner of a webhook registered by an ERP integration over the API (T2a).
 * NULL = created by an administrator in the web UI or the session API. API keys
 * are soft-deleted, so the FK only matters on a hard delete (then the hook
 * falls back to admin ownership rather than disappearing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->foreignId('api_key_id')->nullable()->after('tenant_id')
                ->constrained('api_keys')->nullOnDelete();
            // Every ERP webhook query filters by owner.
            $table->index('api_key_id');
        });

        $this->restoreNameIndexOnSqlite();
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropIndex(['api_key_id']);
            $table->dropConstrainedForeignId('api_key_id');
        });

        $this->restoreNameIndexOnSqlite();
    }

    /**
     * SQLite cannot add a foreign key in place, so Laravel rebuilds the table —
     * and re-creates `webhooks_name_unique` as a plain UNIQUE(name), losing the
     * per-tenant, live-rows-only expression index. Put the real one back.
     * PostgreSQL alters in place and keeps the index untouched.
     */
    private function restoreNameIndexOnSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS webhooks_name_unique');
        DB::statement('CREATE UNIQUE INDEX webhooks_name_unique ON webhooks (COALESCE(tenant_id, 0), name) WHERE deleted_at IS NULL');
    }
};
