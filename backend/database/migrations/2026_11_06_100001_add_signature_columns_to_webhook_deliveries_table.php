<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how the last attempt of a delivery was signed: the unix timestamp
 * sent as X-OpenMES-Timestamp and the scheme (v1 = "{timestamp}.{body}",
 * legacy = body only). Shown in the admin delivery log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('signature_timestamp')->nullable()->after('attempts');
            $table->string('signature_scheme', 10)->nullable()->after('signature_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn(['signature_timestamp', 'signature_scheme']);
        });
    }
};
