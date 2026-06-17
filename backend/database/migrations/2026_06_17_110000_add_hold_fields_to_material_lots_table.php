<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual quality hold/release on material lots. Until now a lot only reached
 * QUARANTINE via an inbound-inspection disposition; these fields let quality
 * put any lot on hold (and later release it), optionally linked to the Issue
 * (non-conformance) that triggered it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->foreignId('issue_id')->nullable()->after('inspection_id')->constrained()->nullOnDelete();
            $table->string('hold_reason', 255)->nullable()->after('status');
            $table->timestamp('held_at')->nullable()->after('hold_reason');
            $table->foreignId('held_by_id')->nullable()->after('held_at')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('held_by_id');
            $table->foreignId('released_by_id')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_id');
            $table->dropConstrainedForeignId('held_by_id');
            $table->dropConstrainedForeignId('released_by_id');
            $table->dropColumn(['hold_reason', 'held_at', 'released_at']);
        });
    }
};
