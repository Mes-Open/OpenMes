<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial-number sequences (#290) — parallel to lot_sequences, deliberately its
 * own table rather than reusing lot_sequences: a lot number identifies a
 * production run, a serial number identifies one physical piece, and the two
 * may want independent pattern/reset rules per product type. Shares
 * LotPatternFormatter for token rendering — that formatter has no lot-specific
 * behavior, it just renders [seq]/[date]/[product]/... tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->foreignId('product_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prefix', 20)->default('');
            $table->string('suffix', 20)->nullable();
            $table->string('pattern', 100)->nullable(); // token pattern; null = legacy prefix/suffix mode
            $table->bigInteger('next_number')->default(1);
            $table->integer('pad_size')->default(4);
            $table->boolean('year_prefix')->default(true);
            $table->string('reset_period', 10)->default('none'); // none|yearly|monthly|daily|hourly
            $table->string('last_reset_key', 20)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Partial unique: one sequence per (product_type, tenant) among live rows,
        // so a deleted sequence's slot frees up (hard rule 9).
        DB::statement(
            'CREATE UNIQUE INDEX serial_sequences_product_tenant_unique
             ON serial_sequences (product_type_id, tenant_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_sequences');
    }
};
