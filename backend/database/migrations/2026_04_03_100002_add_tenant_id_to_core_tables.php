<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'lines',
            'work_orders',
            'process_templates',
            'product_types',
            'issue_types',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $blueprint->index('tenant_id', "idx_{$table}_tenant_id");
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'lines',
            'work_orders',
            'process_templates',
            'product_types',
            'issue_types',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("idx_{$table}_tenant_id");
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
