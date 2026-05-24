<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The initial users-table migration omitted `email_verified_at`, but a
     * later backfill migration (2026_05_21_140000_mark_existing_users_as_verified)
     * and the verification routes assume the column exists. Add it here so the
     * schema matches the application's assumptions.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('email_verified_at')->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('email_verified_at');
            });
        }
    }
};
