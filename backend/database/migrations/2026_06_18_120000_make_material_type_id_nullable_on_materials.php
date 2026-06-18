<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material type becomes optional: a material no longer has to belong to a
 * material type. The foreign key (restrict-on-delete) is unchanged and still
 * applies when a type is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedBigInteger('material_type_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedBigInteger('material_type_id')->nullable(false)->change();
        });
    }
};
