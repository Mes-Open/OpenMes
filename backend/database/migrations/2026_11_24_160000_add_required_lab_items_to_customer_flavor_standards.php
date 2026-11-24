<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_flavor_standards', function (Blueprint $table) {
            $table->json('required_lab_items')->nullable()->after('requires_external_lab');
        });
    }

    public function down(): void
    {
        Schema::table('customer_flavor_standards', function (Blueprint $table) {
            $table->dropColumn('required_lab_items');
        });
    }
};
