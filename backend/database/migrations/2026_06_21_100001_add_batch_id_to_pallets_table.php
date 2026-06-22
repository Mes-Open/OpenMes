<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a pallet to the batch it holds (one batch per pallet). Nullable so
 * existing pallets keep working; nullOnDelete because batches use soft deletes
 * (the FK only fires on a hard delete) and traceability must survive otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('work_order_id')->constrained()->nullOnDelete();
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
