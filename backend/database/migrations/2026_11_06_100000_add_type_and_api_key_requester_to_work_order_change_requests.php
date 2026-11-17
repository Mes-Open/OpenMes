<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change requests raised by an ERP integration (P0-B).
 *
 * `type` separates an ordinary field change (CHANGE) from a request to cancel the
 * order (CANCEL); every existing row is a CHANGE. `requested_by_api_key_id` is the
 * requester when there is no user behind the request — the ERP's API key. Keys are
 * soft-deleted, so the requester's name survives revocation; the FK only nulls on a
 * hard delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_change_requests', function (Blueprint $table) {
            $table->string('type', 20)->default('CHANGE')->after('code');
            $table->foreignId('requested_by_api_key_id')
                ->nullable()
                ->after('requested_by_id')
                ->constrained('api_keys')
                ->nullOnDelete();

            $table->index(['work_order_id', 'type', 'status'], 'wocr_work_order_type_status_index');
            // The ERP change-request feed polls by updated_at.
            $table->index('updated_at', 'wocr_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_change_requests', function (Blueprint $table) {
            $table->dropIndex('wocr_updated_at_index');
            $table->dropIndex('wocr_work_order_type_status_index');
            $table->dropConstrainedForeignId('requested_by_api_key_id');
            $table->dropColumn('type');
        });
    }
};
