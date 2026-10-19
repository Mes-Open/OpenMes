<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The identity provider's stable user id (`sub` claim) for accounts that sign
 * in with single sign-on. Matching on it — not on a username or an email that
 * can be renamed — keeps an account bound to the same person at the provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_subject', 255)->nullable()->after('email');
        });

        // Partial: a soft-deleted account must not keep the identity, or the
        // person could never be linked to a new account again.
        DB::statement(
            'CREATE UNIQUE INDEX users_sso_subject_unique ON users (sso_subject)
             WHERE deleted_at IS NULL AND sso_subject IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_sso_subject_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sso_subject');
        });
    }
};
