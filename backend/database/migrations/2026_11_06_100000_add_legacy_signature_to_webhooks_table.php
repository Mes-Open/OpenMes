<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Timestamped webhook signatures. New endpoints sign "{timestamp}.{body}"; the
 * `legacy_signature` switch keeps the old body-only HMAC. Every endpoint that
 * exists when this runs is switched to legacy, so integrations already in
 * production keep verifying until their owners move them over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->boolean('legacy_signature')->default(false)->after('is_active');
        });

        // Includes soft-deleted rows, so a restored endpoint keeps working too.
        DB::table('webhooks')->update(['legacy_signature' => true]);
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn('legacy_signature');
        });
    }
};
