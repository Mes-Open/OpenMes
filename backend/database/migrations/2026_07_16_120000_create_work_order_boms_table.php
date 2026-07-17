<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot linking a work order to one or more BOMs (process templates).
     *
     * A work order can now reference several BOMs (e.g. variant / alternative
     * bills of materials) instead of a single fixed one. The `is_active` flag
     * lets the user switch which of the linked BOMs currently drive the order's
     * material requirements and consumption. Orders with no rows here keep the
     * legacy single-BOM behaviour (auto-picked active template).
     */
    public function up(): void
    {
        Schema::create('work_order_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['work_order_id', 'process_template_id']);
            $table->index(['work_order_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_boms');
    }
};
