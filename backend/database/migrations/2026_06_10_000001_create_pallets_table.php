<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dedicated Postgres sequence backing the human-facing pallet_no.
        // The Pallet model draws nextval() on create and formats it as PAL-000001.
        // Other drivers (e.g. sqlite in tests) have no sequence; the model derives
        // the next number from the table instead.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS pallets_pallet_no_seq');
        }

        Schema::create('pallets', function (Blueprint $table) {
            $table->id();
            $table->string('pallet_no', 30)->unique()->comment('Unique human-facing number drawn from pallets_pallet_no_seq');
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0)->comment('Number of scanned pieces assigned to this pallet');
            $table->string('status', 20)->default('open')->comment('open | closed | shipped');
            $table->string('location', 100)->nullable();
            $table->string('erp_reference', 100)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('work_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallets');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS pallets_pallet_no_seq');
        }
    }
};
