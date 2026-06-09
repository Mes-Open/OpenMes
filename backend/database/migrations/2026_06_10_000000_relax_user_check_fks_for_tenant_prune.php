<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `packaging_checklists.checked_by`, `quality_checks.checked_by` and
 * `process_confirmations.confirmed_by` referenced users with restrictOnDelete,
 * which blocked deleting a user — and therefore a whole tenant (tenant->users
 * cascades, but the user delete was rejected by these FKs). Demo tenant pruning
 * (tenants:prune) failed every minute with a 23503 foreign-key violation.
 *
 * These are audit "who checked/confirmed it" links: keep the record, null the
 * user reference on deletion — matching every other user FK in the schema.
 */
return new class extends Migration
{
    private array $targets = [
        ['table' => 'packaging_checklists', 'column' => 'checked_by'],
        ['table' => 'quality_checks', 'column' => 'checked_by'],
        ['table' => 'process_confirmations', 'column' => 'confirmed_by'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $t) {
            if (! Schema::hasTable($t['table'])) {
                continue;
            }

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                $table->dropForeign([$t['column']]);
            });

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                $table->unsignedBigInteger($t['column'])->nullable()->change();
            });

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                $table->foreign($t['column'])->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $t) {
            if (! Schema::hasTable($t['table'])) {
                continue;
            }

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                $table->dropForeign([$t['column']]);
                $table->foreign($t['column'])->references('id')->on('users')->restrictOnDelete();
            });
        }
    }
};
