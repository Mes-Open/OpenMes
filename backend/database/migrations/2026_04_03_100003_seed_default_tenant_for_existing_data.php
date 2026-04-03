<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('tenants')->insertGetId([
            'name'       => 'Default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['users', 'lines', 'work_orders', 'process_templates', 'product_types', 'issue_types'] as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — cannot safely reverse a data seeding migration
    }
};
