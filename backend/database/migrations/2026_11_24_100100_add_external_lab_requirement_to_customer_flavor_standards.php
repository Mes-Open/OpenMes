<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_flavor_standards', function (Blueprint $table) {
            $table->boolean('requires_external_lab')->default(false)->after('foreign_matter_required');
        });
    }

    public function down(): void
    {
        Schema::table('customer_flavor_standards', function (Blueprint $table) {
            $table->dropColumn('requires_external_lab');
        });
    }
};
