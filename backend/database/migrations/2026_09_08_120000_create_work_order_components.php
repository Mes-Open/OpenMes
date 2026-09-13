<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->foreignId('component_template_id')->nullable()->constrained('process_templates')->restrictOnDelete();
        });
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('parent_work_order_id')->nullable()->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('root_work_order_id')->nullable()->constrained('work_orders')->restrictOnDelete();
            $table->json('component_plan')->nullable();
        });
        Schema::create('work_order_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('parent_work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('child_work_order_id')->nullable()->unique()->constrained('work_orders')->restrictOnDelete();
            $table->unsignedInteger('plan_version')->default(1);
            $table->string('path', 500);
            $table->json('specification');
            $table->decimal('required_qty', 16, 4);
            $table->decimal('planned_qty', 16, 4);
            $table->unsignedInteger('consuming_step_number')->nullable();
            $table->timestamps();
            $table->unique(['root_work_order_id', 'plan_version', 'path'], 'work_order_components_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_components');
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_work_order_id');
            $table->dropConstrainedForeignId('root_work_order_id');
            $table->dropColumn('component_plan');
        });
        Schema::table('bom_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('component_template_id'));
    }
};
