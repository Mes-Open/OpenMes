<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional product photo. The file itself lives on the private disk under a
 * server-generated name; only the path and the (re-encoded, known-safe) mime
 * type are stored here. Nothing is web-reachable — see the authenticated
 * `admin.product-types.image` stream endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('unit_of_measure');
            $table->string('image_mime', 64)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_mime']);
        });
    }
};
