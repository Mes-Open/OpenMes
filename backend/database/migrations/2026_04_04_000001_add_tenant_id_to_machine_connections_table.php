<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('machine_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
