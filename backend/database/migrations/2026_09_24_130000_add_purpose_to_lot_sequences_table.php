<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sequence issues one kind of number. Until now every sequence was a LOT
 * sequence and a product could have only one; a serialised product needs a
 * process-serial sequence and a unit-serial sequence beside it, so the
 * uniqueness moves to (product, purpose).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_sequences', function (Blueprint $table) {
            $table->string('purpose', 20)->default('lot')->after('product_type_id');
        });

        self::dropOldUnique();

        // Partial: sequences are soft-deleted, and a deleted one must not keep the
        // product's slot for its purpose (plain SQL - the Blueprint has no WHERE).
        DB::statement('CREATE UNIQUE INDEX lot_sequences_product_purpose_unique ON lot_sequences (product_type_id, purpose, tenant_id) WHERE deleted_at IS NULL');
    }

    /**
     * On PostgreSQL the old uniqueness may exist as a constraint or as a bare
     * unique index depending on how the database was built (migrate vs a
     * restored dump), and DROP CONSTRAINT fails on the latter - so both forms
     * are dropped if present. Other drivers take the Blueprint route.
     */
    private static function dropOldUnique(): void
    {
        $name = 'lot_sequences_product_type_id_tenant_id_unique';

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE lot_sequences DROP CONSTRAINT IF EXISTS {$name}");
            DB::statement("DROP INDEX IF EXISTS {$name}");

            return;
        }

        Schema::table('lot_sequences', function (Blueprint $table) use ($name) {
            $table->dropUnique($name);
        });
    }

    /**
     * The one-sequence-per-product uniqueness is not put back: a product that has
     * process- and unit-serial sequences by now would fail it.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lot_sequences_product_purpose_unique');

        Schema::table('lot_sequences', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
