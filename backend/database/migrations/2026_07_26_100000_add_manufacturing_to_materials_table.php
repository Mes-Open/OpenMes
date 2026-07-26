<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make-or-buy on the item master (#—, multi-level BOMs).
     *
     * A BOM line already points at a material; what distinguishes a purchased
     * component from an internally manufactured subassembly is a property of
     * the item itself, not of the line. Flagging it here means `bom_items`
     * keeps its existing shape — every single-level BOM and API client carries
     * on working untouched — while a manufactured material can be exploded one
     * level further through the template that produces it.
     *
     * It also lines up with the existing batch → material-lot link
     * (`material_lots.source_batch_id`): a manufactured material is exactly the
     * kind of item a batch produces and a parent order later consumes, so
     * stock, lots, allocation and genealogy all keep working as they are.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('is_manufactured')->default(false)->after('tracking_type')
                ->comment('Produced in-house (subassembly) rather than purchased');

            // The BOM/routing that produces this material. Nullable even when
            // is_manufactured is true: an item can be flagged as made in-house
            // before its process template exists. Explosion treats such a
            // material as a leaf until the template is set.
            $table->foreignId('producing_process_template_id')->nullable()
                ->after('is_manufactured')
                ->constrained('process_templates')
                ->nullOnDelete();

            $table->index('is_manufactured');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producing_process_template_id');
            $table->dropIndex(['is_manufactured']);
            $table->dropColumn('is_manufactured');
        });
    }
};
