<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One worker, at most one live account.
 *
 * An account can now be linked to a worker that already exists in HR, so the
 * one-to-one the Worker::user() relation assumes has to hold in the database,
 * not only in the form. Partial, like every unique index on a soft-deletable
 * table: a trashed account frees its worker for a new one.
 *
 * Installations where two live accounts already point at the same worker would
 * fail the index, so the newer accounts are unlinked first (the oldest keeps
 * the worker). The accounts and the worker themselves are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->whereNotNull('worker_id')
            ->whereNull('deleted_at')
            ->groupBy('worker_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('worker_id');

        foreach ($duplicates as $workerId) {
            $keep = DB::table('users')
                ->where('worker_id', $workerId)
                ->whereNull('deleted_at')
                ->min('id');

            DB::table('users')
                ->where('worker_id', $workerId)
                ->whereNull('deleted_at')
                ->where('id', '!=', $keep)
                ->update(['worker_id' => null]);
        }

        DB::statement(
            'CREATE UNIQUE INDEX users_worker_id_unique
             ON users (worker_id)
             WHERE worker_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_worker_id_unique');
    }
};
