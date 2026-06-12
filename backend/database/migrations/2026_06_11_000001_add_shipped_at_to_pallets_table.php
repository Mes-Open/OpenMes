<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()
                ->comment('Set once when the pallet transitions to shipped; basis for shift-window attribution');
        });

        // Backfill already-shipped pallets so handover windows keep seeing them.
        DB::table('pallets')->where('status', 'shipped')->whereNull('shipped_at')
            ->update(['shipped_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('shipped_at');
        });
    }
};
